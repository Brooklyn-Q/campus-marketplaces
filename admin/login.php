<?php
require_once '../includes/db.php';

if (isAdmin()) {
    redirect('index.php');
}

if (!empty($_SESSION['pending_admin_2fa_user_id'])) {
    redirect('verify_2fa.php');
}

if (isLoggedIn() && !isAdmin()) {
    redirect('../dashboard.php');
}

redirect('../login.php?mode=admin');
