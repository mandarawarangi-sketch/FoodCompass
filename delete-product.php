<?php
declare(strict_types=1);

require __DIR__ . '/owner-auth.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$token = $_POST['delete_token'] ?? '';
if (
    !isset($_SESSION['delete_token']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['delete_token'], $token)
) {
    http_response_code(403);
    exit('Invalid form submission.');
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(404);
    exit('Product not found.');
}

$statement = $db->prepare(
    'UPDATE products
     SET deleted_at = CURRENT_TIMESTAMP
     WHERE id = ? AND owner_id = ? AND deleted_at IS NULL'
);
$statement->execute([$productId, $ownerId]);

if ($statement->rowCount() !== 1) {
    http_response_code(404);
    exit('Product not found.');
}

$_SESSION['delete_token'] = bin2hex(random_bytes(32));
header('Location: my-submissions.php');
exit;