<?php
declare(strict_types=1);
// Uses the same database settings and session role as the existing FoodCompass pages.
$host = getenv('FOODCOMPASS_DB_HOST') ?: '127.0.0.1';
$port = getenv('FOODCOMPASS_DB_PORT') ?: '3306';
$name = getenv('FOODCOMPASS_DB_NAME') ?: 'foodcompass';
$user = getenv('FOODCOMPASS_DB_USER') ?: 'root';
$password = getenv('FOODCOMPASS_DB_PASSWORD') ?: '';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $user, $password, $name, (int) $port);
$conn->set_charset('utf8mb4');
function require_retailer_login(): int {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'retail_store') {
        http_response_code(403);
        exit('Retail Store access required. Sign in at retailer-login.php.');
    }
    $_SESSION['retail_token'] ??= bin2hex(random_bytes(32));
    return (int) $_SESSION['user_id'];
}
function retail_form_token(): string {
    return htmlspecialchars($_SESSION['retail_token'], ENT_QUOTES, 'UTF-8');
}
function require_retail_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals($_SESSION['retail_token'], (string) ($_POST['retail_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid form token. Refresh the page.');
    }
}
function branch_owned_by(mysqli $conn, int $branch_id, int $retailer_id): ?array {
    $stmt = $conn->prepare('SELECT id, branch_name, area, address, status FROM branches WHERE id = ? AND retailer_id = ?');
    $stmt->bind_param('ii', $branch_id, $retailer_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
