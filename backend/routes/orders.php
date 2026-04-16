<?php
/**
 * Order Routes
 * GET    /orders                      - List user's orders
 * POST   /orders                      - Place order
 * PUT    /orders/:id/accept           - Seller accept + delivery note
 * PUT    /orders/:id/reject           - Seller reject order
 * PUT    /orders/:id/cancel           - Buyer cancel order
 * PUT    /orders/:id/confirm-sold     - Seller confirms sold
 * PUT    /orders/:id/confirm-received - Buyer confirms received
 * POST   /orders/:id/dispute          - File dispute
 */

$auth = authenticate();
$orderId = is_numeric($action) ? (int) $action : null;
$subAction = $param;

$saleDescription = static fn(int $id): string => "Sale of order #$id";

$hasSaleTransaction = static function (PDO $pdo, int $sellerId, int $orderId) use ($saleDescription): bool {
    $stmt = $pdo->prepare("
        SELECT id
        FROM transactions
        WHERE user_id = ?
          AND type = 'sale'
          AND status = 'completed'
          AND description = ?
        LIMIT 1
    ");
    $stmt->execute([$sellerId, $saleDescription($orderId)]);
    return (bool) $stmt->fetchColumn();
};

$insertSaleTransaction = static function (PDO $pdo, array $order, int $orderId) use ($hasSaleTransaction, $saleDescription): void {
    if ($hasSaleTransaction($pdo, (int) $order['seller_id'], $orderId)) {
        return;
    }

    $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status, reference, description)
        VALUES (?, 'sale', ?, 'completed', ?, ?)
    ")->execute([
        $order['seller_id'],
        $order['price'],
        generateRef('SAL'),
        $saleDescription($orderId)
    ]);
};

$restoreReservedStock = static function (PDO $pdo, array $order): void {
    if (!in_array($order['status'] ?? '', ['pending', 'ordered'], true)) {
        return;
    }

    $qty = max(1, (int) ($order['quantity'] ?? 1));

    $pdo->prepare("
        UPDATE products
        SET quantity = quantity + ?,
            status = CASE WHEN status = 'sold' THEN 'approved' ELSE status END
        WHERE id = ?
    ")->execute([$qty, $order['product_id']]);
};

// - LIST ORDERS -
if ($method === 'GET' && !$orderId) {
    $role = getQueryParam('view', 'buyer'); // 'buyer' or 'seller'
    $status = getQueryParam('status', '');

    if ($role === 'seller') {
        $where = "o.seller_id = ?";
    } else {
        $where = "o.buyer_id = ?";
    }
    $params = [$auth['user_id']];

    if ($status) {
        $where .= " AND o.status = ?";
        $params[] = $status;
    }

    $stmt = $pdo->prepare("
        SELECT o.*, p.title as product_title, p.price as product_price,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as product_image,
            buyer.username as buyer_name, buyer.profile_pic as buyer_pic, buyer.phone as buyer_phone,
            seller.username as seller_name, seller.profile_pic as seller_pic, seller.phone as seller_phone
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users buyer ON o.buyer_id = buyer.id
        JOIN users seller ON o.seller_id = seller.id
        WHERE $where
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);

    jsonResponse(['orders' => $stmt->fetchAll()]);
}

// - PLACE ORDER -
elseif ($method === 'POST' && !$orderId) {
    $body = getJsonBody();
    $productId = (int) ($body['product_id'] ?? 0);
    $qty = max(1, (int) ($body['quantity'] ?? 1));

    if (!$productId) {
        jsonError('Product ID is required');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'approved' FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            jsonError('Product not available', 404);
        }
        if ($product['user_id'] === $auth['user_id']) {
            $pdo->rollBack();
            jsonError('Cannot order your own product');
        }
        if ($product['quantity'] < $qty) {
            $pdo->rollBack();
            jsonError('Insufficient stock');
        }

        $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $productId]);
        $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ? AND quantity <= 0")->execute([$productId]);

        $pdo->prepare("
            INSERT INTO orders (product_id, buyer_id, seller_id, price, quantity, status)
            VALUES (?, ?, ?, ?, ?, 'ordered')
        ")->execute([$productId, $auth['user_id'], $product['user_id'], $product['price'], $qty]);

        $ordId = (int)$pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, reference_id)
            VALUES (?, 'new_order', ?, ?)
        ")->execute([$product['user_id'], "New order for \"{$product['title']}\"", $ordId]);

        $pdo->commit();

        jsonResponse(['success' => true, 'message' => 'Order placed', 'order_id' => $ordId], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonError('Failed to place order', 500);
    }
}

// - SELLER ACCEPT -
elseif ($method === 'PUT' && $orderId && $subAction === 'accept') {
    $body = getJsonBody();
    $note = trim($body['delivery_note'] ?? '');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND seller_id = ? FOR UPDATE");
        $stmt->execute([$orderId, $auth['user_id']]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            jsonError('Order not found', 404);
        }
        if (
            (int) $order['seller_confirmed'] === 1 ||
            (int) $order['buyer_confirmed'] === 1 ||
            in_array($order['status'], ['completed', 'disputed'], true)
        ) {
            $pdo->rollBack();
            jsonError('Order can no longer be accepted', 409);
        }

        // Release legacy reserved stock from older pending orders before moving them
        // into the current ordered -> seller_seen flow.
        $restoreReservedStock($pdo, $order);

        $pdo->prepare("
            UPDATE orders
            SET status = 'seller_seen', delivery_note = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$note, $orderId]);

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, reference_id)
            VALUES (?, 'order_accepted', 'Seller has accepted your order', ?)
        ")->execute([$order['buyer_id'], $orderId]);

        $pdo->commit();
        jsonSuccess('Order accepted');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to accept order', 500);
    }
}

// - SELLER REJECT -
elseif ($method === 'PUT' && $orderId && $subAction === 'reject') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND seller_id = ? FOR UPDATE");
        $stmt->execute([$orderId, $auth['user_id']]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            jsonError('Order not found', 404);
        }
        if ((int) $order['seller_confirmed'] === 1 || (int) $order['buyer_confirmed'] === 1 || in_array($order['status'], ['completed', 'disputed'], true)) {
            $pdo->rollBack();
            jsonError('Order can no longer be rejected', 409);
        }

        $restoreReservedStock($pdo, $order);

        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, reference_id)
            VALUES (?, 'order_rejected', 'Seller rejected your order', ?)
        ")->execute([$order['buyer_id'], $orderId]);

        $pdo->commit();
        jsonSuccess('Order rejected');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to reject order', 500);
    }
}

// - BUYER CANCEL -
elseif ($method === 'PUT' && $orderId && $subAction === 'cancel') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE");
        $stmt->execute([$orderId, $auth['user_id']]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            jsonError('Order not found', 404);
        }
        if ((int) $order['seller_confirmed'] === 1 || (int) $order['buyer_confirmed'] === 1 || in_array($order['status'], ['completed', 'disputed'], true)) {
            $pdo->rollBack();
            jsonError('Order can no longer be cancelled', 409);
        }

        $restoreReservedStock($pdo, $order);

        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, reference_id)
            VALUES (?, 'order_cancelled', 'Buyer cancelled the order', ?)
        ")->execute([$order['seller_id'], $orderId]);

        $pdo->commit();
        jsonSuccess('Order cancelled');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to cancel order', 500);
    }
}

// - SELLER CONFIRM SOLD -
elseif ($method === 'PUT' && $orderId && $subAction === 'confirm-sold') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND seller_id = ? FOR UPDATE");
        $stmt->execute([$orderId, $auth['user_id']]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            jsonError('Order not found', 404);
        }
        if ($order['status'] === 'disputed') {
            $pdo->rollBack();
            jsonError('Disputed orders cannot be confirmed', 409);
        }
        if ((int) $order['seller_confirmed'] === 1) {
            $pdo->commit();
            jsonSuccess('Sale already confirmed');
        }

        $qty = max(1, (int) ($order['quantity'] ?? 1));

        if (($order['status'] ?? '') === 'pending') {
            $productStmt = $pdo->prepare("SELECT id, quantity FROM products WHERE id = ? FOR UPDATE");
            $productStmt->execute([$order['product_id']]);
            $product = $productStmt->fetch();

            if (!$product) {
                $pdo->rollBack();
                jsonError('Product not found', 404);
            }
            if ((int) $product['quantity'] < $qty) {
                $pdo->rollBack();
                jsonError('Insufficient stock to confirm this order', 409);
            }

            $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?")
                ->execute([$qty, $order['product_id']]);
            $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ? AND quantity <= 0")
                ->execute([$order['product_id']]);
        }

        $nextStatus = (int) $order['buyer_confirmed'] === 1 ? 'completed' : 'delivered';

        $pdo->prepare("
            UPDATE orders
            SET seller_confirmed = 1, status = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$nextStatus, $orderId]);

        if ((int) $order['buyer_confirmed'] === 1) {
            $insertSaleTransaction($pdo, $order, $orderId);
        }

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, reference_id)
            VALUES (?, 'seller_confirmed', 'Seller confirmed the sale', ?)
        ")->execute([$order['buyer_id'], $orderId]);

        $pdo->commit();
        jsonSuccess('Sale confirmed');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to confirm sale', 500);
    }
}

// - BUYER CONFIRM RECEIVED -
elseif ($method === 'PUT' && $orderId && $subAction === 'confirm-received') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE");
        $stmt->execute([$orderId, $auth['user_id']]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            jsonError('Order not found', 404);
        }
        if ($order['status'] === 'disputed') {
            $pdo->rollBack();
            jsonError('Disputed orders cannot be confirmed', 409);
        }
        if ((int) $order['buyer_confirmed'] === 1) {
            $pdo->commit();
            jsonSuccess('Receipt already confirmed');
        }

        $nextStatus = (int) $order['seller_confirmed'] === 1 ? 'completed' : 'delivered';

        $pdo->prepare("
            UPDATE orders
            SET buyer_confirmed = 1, status = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$nextStatus, $orderId]);

        if ((int) $order['seller_confirmed'] === 1) {
            $insertSaleTransaction($pdo, $order, $orderId);
        }

        $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, reference_id)
            VALUES (?, 'buyer_confirmed', 'Buyer confirmed receipt', ?)
        ")->execute([$order['seller_id'], $orderId]);

        $pdo->commit();
        jsonSuccess('Receipt confirmed');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to confirm receipt', 500);
    }
}

// - FILE DISPUTE -
elseif ($method === 'POST' && $orderId && $subAction === 'dispute') {
    $body = getJsonBody();
    $reason = trim($body['reason'] ?? '');

    if (!$reason) {
        jsonError('Reason is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
    $stmt->execute([$orderId, $auth['user_id'], $auth['user_id']]);
    $order = $stmt->fetch();
    if (!$order) {
        jsonError('Order not found', 404);
    }

    $targetId = $auth['user_id'] === $order['buyer_id'] ? $order['seller_id'] : $order['buyer_id'];

    $pdo->prepare("INSERT INTO disputes (order_id, complainant_id, target_id, reason) VALUES (?, ?, ?, ?)")
        ->execute([$orderId, $auth['user_id'], $targetId, $reason]);

    $pdo->prepare("UPDATE orders SET status = 'disputed', updated_at = NOW() WHERE id = ?")
        ->execute([$orderId]);

    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (1, 'dispute', ?, ?)")
        ->execute(["Dispute filed on order #$orderId", $orderId]);

    jsonSuccess('Dispute filed. Admin will review.');
}

else {
    jsonError('Order endpoint not found', 404);
}
