<?php
/**
 * Announcements Route
 * GET /announcements — Active announcements
 */

if ($method !== 'GET') jsonError('Method not allowed', 405);

$stmt = $pdo->query("SELECT id, message, type, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
jsonResponse(['announcements' => $stmt->fetchAll()]);
