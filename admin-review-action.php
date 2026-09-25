<?php
declare(strict_types=1);

require __DIR__ . '/admin-auth.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('This action requires a form submission.');
}

$token = (string) ($_POST['review_token'] ?? '');
if (
    empty($_SESSION['review_token']) ||
    !hash_equals($_SESSION['review_token'], $token)
) {
    http_response_code(403);
    exit('Invalid review form. Reload the review page and try again.');
}

$submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
$decision = (string) ($_POST['decision'] ?? '');
$note = trim((string) ($_POST['review_note'] ?? ''));

if (
    !$submissionId ||
    !in_array($decision, ['approved', 'rejected'], true) ||
    strlen($note) > 500 ||
    ($decision === 'rejected' && $note === '')
) {
    http_response_code(400);
    exit('Invalid review details. A rejection requires a note.');
}

try {
    $db->beginTransaction();

    // Lock the product while checking which submission is the newest.
    $findProduct = $db->prepare(
        'SELECT p.id, p.owner_id
         FROM products AS p
         JOIN product_submissions AS s ON s.product_id = p.id
         WHERE s.id = ? AND p.deleted_at IS NULL
         FOR UPDATE'
    );
    $findProduct->execute([$submissionId]);
    $product = $findProduct->fetch();

    if (!$product || (int) $product['owner_id'] === $adminId) {
        $db->rollBack();
        http_response_code(403);
        exit('You cannot review this submission.');
    }

    $findLatest = $db->prepare(
        'SELECT id, review_status
         FROM product_submissions
         WHERE product_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $findLatest->execute([(int) $product['id']]);
    $latest = $findLatest->fetch();

    if (
        !$latest ||
        (int) $latest['id'] !== $submissionId ||
        $latest['review_status'] !== 'pending'
    ) {
        $db->rollBack();
        http_response_code(409);
        exit('This submission is no longer awaiting review. Reload the page.');
    }

    $update = $db->prepare(
        'UPDATE product_submissions
         SET review_status = ?, review_note = ?,
             reviewed_by = ?, reviewed_at = NOW()
         WHERE id = ? AND review_status = ?'
    );
    $update->execute([
        $decision,
        $note,
        $adminId,
        $submissionId,
        'pending'
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('The review could not be saved.');
    }

    $db->commit();
    header('Location: admin-reviews.php');
    exit;
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    exit('The review could not be saved.');
}