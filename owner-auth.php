<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'product_owner'
) {
    header('Location: login-owner.php');
    exit;
}

$ownerId = (int) $_SESSION['user_id'];