<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
$error = ''; $success = '';

require_once 'includes/header.php';
?>

<div class="glass form-container fade-in" style="max-width:480px; margin:4rem auto; padding:2.5rem;">
    <div style="text-align:center; margin-bottom:2rem;">
        <div style="width:64px; height:64px; background:rgba(0,113,227,0.1); color:var(--primary); border-radius:18px; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem;">₵</div>
        <h2 style="font-weight:800; letter-spacing:-0.03em;">Deposit Funds</h2>
        <p class="text-muted" style="font-size:0.9rem;">Add funds to your wallet using Paystack.</p>
    </div>

    <div id="depositForm">
        <div class="form-group mb-4">
            <label style="font-weight:600; font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Amount to Deposit (₵)</label>
            <input type="number" id="depositAmount" step="0.01" min="1" class="form-control" required placeholder="0.00" style="font-size:1.5rem; font-weight:700; padding:1rem; height:auto; text-align:center; border-radius:16px;">
        </div>
        
        <button type="button" onclick="startDeposit()" class="btn btn-primary" style="width:100%; justify-content:center; padding:1rem; font-size:1.1rem; font-weight:700; border-radius:16px; box-shadow:0 10px 20px rgba(0,113,227,0.2);">
            Initialize Payment
        </button>
    </div>

    <div id="processingArea" style="display:none; text-align:center; padding:2rem 0;">
        <div class="spinner mb-3" style="margin:0 auto;"></div>
        <p style="font-weight:600;">Verifying payment... Please wait.</p>
    </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function startDeposit() {
    const amount = document.getElementById('depositAmount').value;
    if(!amount || amount <= 0) {
        alert("Please enter a valid amount.");
        return;
    }

    const handler = PaystackPop.setup({
        key: '<?= get_env_var("PAYSTACK_PUBLIC_KEY") ?>',
        email: '<?= $user["email"] ?>',
        amount: amount * 100,
        currency: 'GHS',
        callback: function(response) {
            verifyDeposit(response.reference);
        },
        onClose: function() {
            alert('Transaction cancelled.');
        }
    });
    handler.openIframe();
}

async function verifyDeposit(reference) {
    document.getElementById('depositForm').style.display = 'none';
    document.getElementById('processingArea').style.display = 'block';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('api/paystack_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reference: reference, type: 'deposit', csrf_token: csrfToken })
        });
        const data = await res.json();
        if(data.status === 'success') {
            alert(data.message);
            window.location.href = 'dashboard.php';
        } else {
            alert('Error: ' + data.message);
            document.getElementById('depositForm').style.display = 'block';
            document.getElementById('processingArea').style.display = 'none';
        }
    } catch(e) {
        alert('Critical error verifying deposit.');
        window.location.reload();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
