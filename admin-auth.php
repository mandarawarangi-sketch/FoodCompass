<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'platform_administrator'
) {
    http_response_code(403);
    exit('Administrator access required.');
}

$adminId = (int) $_SESSION['user_id'];