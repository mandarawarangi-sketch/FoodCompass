<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/product-labels.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$submissionId = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
$type = (string) ($_GET['type'] ?? '');
if (!$submissionId || !array_key_exists($type, UPLOAD_PHOTO_FIELDS)) {
    http_response_code(404); exit('Photo not found.');
}
$query = $db->prepare("SELECT s.id, s.review_status, s.ingredients_photo, s.allergen_photo, s.nutrition_photo, s.product_photo, p.owner_id
    FROM product_submissions s JOIN products p ON p.id = s.product_id
    WHERE s.id = ? AND p.deleted_at IS NULL");
$query->execute([$submissionId]);
$row = $query->fetch();
if (!$row || empty($row[$type])) { http_response_code(404); exit('Photo not found.'); }
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');
$canReview = $userId > 0 && $role === 'platform_administrator';
$owns = $userId > 0 && $role === 'product_owner' && $userId === (int) $row['owner_id'];
$isPublicApproved = false;
if ($row['review_status'] === 'approved') {
    $latest = $db->prepare("SELECT id FROM product_submissions WHERE product_id =
       (SELECT product_id FROM product_submissions WHERE id = ?) AND review_status = 'approved'
       ORDER BY id DESC LIMIT 1");
    $latest->execute([$submissionId]);
    $isPublicApproved = (int) $latest->fetchColumn() === $submissionId;
}
if (!$canReview && !$owns && !$isPublicApproved) {
    http_response_code(404); exit('Photo not found.');
}
$filename = (string) $row[$type];
if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/', $filename)) {
    http_response_code(404); exit('Photo not found.');
}
$path = __DIR__ . '/uploads/product-labels/' . $filename;
if (!is_file($path)) { http_response_code(404); exit('Photo not found.'); }
$extension = pathinfo($filename, PATHINFO_EXTENSION);
header('Content-Type: ' . ['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$extension]);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="label.' . $extension . '"');
header('Cache-Control: private, no-store');
readfile($path);
