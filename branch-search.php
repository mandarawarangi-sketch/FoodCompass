<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: customer-account.php'); exit;
}
require __DIR__ . '/db.php';
$customerId = (int) $_SESSION['user_id'];
$_SESSION['branch_token'] ??= bin2hex(random_bytes(32));
function branchText(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$error = '';
$notice = isset($_GET['sent']) ? 'Request sent. The branch can now respond.' : '';
$listId = filter_var($_POST['list_id'] ?? $_GET['list_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$stmt = $db->prepare('SELECT id, list_name FROM saved_lists WHERE customer_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$customerId]);
$lists = $stmt->fetchAll();
if (!$listId) $listId = (int)($lists[0]['id'] ?? 0);
$selected = null;
foreach ($lists as $list) if ((int)$list['id'] === $listId) $selected = $list;
if ($listId && !$selected) { http_response_code(404); exit('List not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['branch_token'], (string)($_POST['token'] ?? ''))) {
        http_response_code(403); exit('Invalid form. Refresh and try again.');
    }
    $branchId = filter_var($_POST['branch_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    if (!$selected || !$branchId) $error = 'Choose a list and branch.';
    else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT id, list_name FROM saved_lists WHERE id = ? AND customer_id = ? FOR UPDATE');
            $stmt->execute([$listId, $customerId]);
            $lockedList = $stmt->fetch();
            $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
            $stmt->execute([$branchId]);
            if (!$lockedList || !$stmt->fetchColumn()) throw new RuntimeException('List or active branch not found.');
            $stmt = $db->prepare("SELECT i.product_id, i.quantity, s.name FROM saved_list_items i
              JOIN products p ON p.id = i.product_id AND p.deleted_at IS NULL
              JOIN product_submissions s ON s.id = (SELECT MAX(v.id) FROM product_submissions v
                WHERE v.product_id = p.id AND v.review_status = 'approved')
              WHERE i.list_id = ? ORDER BY i.id");
            $stmt->execute([$listId]);
            $items = $stmt->fetchAll();
            $count = $db->prepare('SELECT COUNT(*) FROM saved_list_items WHERE list_id = ?');
            $count->execute([$listId]);
            if (!$items || count($items) !== (int)$count->fetchColumn()) {
                throw new RuntimeException('The list must contain approved, public products. Remove any withdrawn products first.');
            }
            $stmt = $db->prepare('INSERT INTO availability_requests (list_id, branch_id, customer_id, list_name) VALUES (?, ?, ?, ?)');
            $stmt->execute([$listId, $branchId, $customerId, $lockedList['list_name']]);
            $requestId = (int)$db->lastInsertId();
            $stmt = $db->prepare('INSERT INTO request_items (request_id, product_id, product_name, requested_quantity) VALUES (?, ?, ?, ?)');
            foreach ($items as $item) $stmt->execute([$requestId, $item['product_id'], $item['name'], $item['quantity']]);
            $db->commit();
            header('Location: branch-search.php?list_id=' . $listId . '&sent=1'); exit;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not send the request. Please try again.';
        }
    }
}

$search = trim((string)($_GET['q'] ?? ''));
if (strlen($search) > 120) $search = substr($search, 0, 120);
$stmt = $db->prepare('SELECT id, branch_name, area, address, retailer_id FROM branches WHERE status = \'active\' AND
 (area LIKE ? OR branch_name LIKE ? OR EXISTS (SELECT 1 FROM users u WHERE u.id = branches.retailer_id AND u.name LIKE ?))
 ORDER BY area, branch_name, id LIMIT 60');
$term = '%' . addcslashes($search, '%_\\') . '%';
$stmt->execute([$term, $term, $term]);
$branches = $stmt->fetchAll();
$items = [];
if ($selected) {
    $stmt = $db->prepare("SELECT i.product_id, i.quantity, COALESCE(s.name, 'Product no longer public') AS name
      FROM saved_list_items i LEFT JOIN products p ON p.id = i.product_id LEFT JOIN product_submissions s ON s.id =
      (SELECT MAX(v.id) FROM product_submissions v WHERE v.product_id = p.id AND v.review_status = 'approved' AND p.deleted_at IS NULL)
      WHERE i.list_id = ? ORDER BY i.id");
    $stmt->execute([$listId]); $items = $stmt->fetchAll();
}
$reported = [];
if ($items && $branches) {
    $branchIds = array_column($branches, 'id');
    $productIds = array_column($items, 'product_id');
    $sql = 'SELECT branch_id, product_id, stock_status, price, last_reported_at FROM branch_products WHERE branch_id IN ('
      . implode(',', array_fill(0, count($branchIds), '?')) . ') AND product_id IN ('
      . implode(',', array_fill(0, count($productIds), '?')) . ')';
    $stmt = $db->prepare($sql); $stmt->execute(array_merge($branchIds, $productIds));
    foreach ($stmt->fetchAll() as $row) $reported[(int)$row['branch_id']][(int)$row['product_id']] = $row;
}
$stmt = $db->prepare('SELECT r.id, r.list_name, r.status, r.requested_at, r.responded_at,
 b.branch_name, b.area, b.address FROM availability_requests r JOIN branches b ON b.id = r.branch_id
 WHERE r.customer_id = ? ORDER BY r.requested_at DESC, r.id DESC LIMIT 50');
$stmt->execute([$customerId]); $requests = $stmt->fetchAll();
$requestItems = [];
if ($requests) {
    $ids = array_column($requests, 'id');
    $stmt = $db->prepare('SELECT request_id, product_name, requested_quantity, response, note FROM request_items WHERE request_id IN ('
      . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) $requestItems[(int)$row['request_id']][] = $row;
}
$stockLabels = ['available'=>'Available','out_of_stock'=>'Out of stock','unknown'=>'Unknown'];
$responseLabels = ['available'=>'Available','unavailable'=>'Unavailable','partial'=>'Partially available','unknown'=>'Awaiting confirmation'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Find a branch | FoodCompass</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f7faf8;color:#26352d;font:16px/1.5 system-ui,Arial,sans-serif}header{background:#fff;border-bottom:1px solid #dce8df;padding:18px max(20px,calc((100vw - 1100px)/2))}header a{color:#2f7d4a;font-weight:700;margin-right:22px}main{max-width:1100px;margin:40px auto;padding:0 20px}h1,h2,h3{color:#183225}.card{background:#fff;border:1px solid #dce8df;border-radius:16px;padding:22px;margin:20px 0;box-shadow:0 6px 20px #1832250a}.row{display:flex;align-items:end;gap:12px;flex-wrap:wrap}label{font-weight:600}input,select{display:block;padding:10px;border:1px solid #b9d1c0;border-radius:8px;font:inherit;margin-top:6px;max-width:100%}button{padding:10px 14px;background:#28754d;color:#fff;border:0;border-radius:8px;font:inherit;cursor:pointer}.muted{color:#62756b}.error{color:#b3372b}.list{padding-left:22px}.list li{padding:4px 0}details{margin-top:12px}summary{cursor:pointer;font-weight:600}@media(max-width:600px){main{margin-top:20px}}</style></head><body>
<header><a href="index.html">FoodCompass</a><a href="products.php">Approved products</a><a href="lists.php">My lists</a><a href="customer-account.php">My account</a></header>
<main><h1>Find a branch</h1><p class="muted">Compare each branch’s last reported availability for your list, then ask the branch to confirm. Sending a request does not reserve stock or place an order.</p>
<?php if ($error): ?><p class="error" role="alert"><?= branchText($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?= branchText($notice) ?></p><?php endif; ?>
<section class="card"><h2>Search participating branches</h2>
<?php if (!$lists): ?><p>Create a shopping list first. <a href="lists.php">My lists</a></p><?php else: ?>
<form method="get" class="row"><label>Shopping list<select name="list_id"><?php foreach ($lists as $list): ?><option value="<?= (int)$list['id'] ?>" <?= (int)$list['id'] === $listId ? 'selected' : '' ?>><?= branchText($list['list_name']) ?></option><?php endforeach; ?></select></label><label>Area, branch or retailer name<input name="q" value="<?= branchText($search) ?>" maxlength="120" placeholder="e.g. Moratuwa"></label><button>Search</button></form>
<?php if (!$items): ?><p class="muted">Add approved products to this list before sending a request.</p><?php endif; ?>
<?php endif; ?></section>
<?php foreach ($branches as $branch): ?><section class="card"><h2><?= branchText($branch['branch_name']) ?></h2><p><?= branchText($branch['area']) ?> · <?= branchText($branch['address']) ?></p>
<?php if ($items): ?><h3>Last reported for this list</h3><ul class="list">
<?php foreach ($items as $item): $entry = $reported[(int)$branch['id']][(int)$item['product_id']] ?? null; ?>
<li><?= branchText($item['name']) ?> × <?= (int)$item['quantity'] ?>: <strong><?= $entry ? branchText($stockLabels[$entry['stock_status']] ?? 'Unknown') : 'No report' ?></strong>
<?php if ($entry): ?><?php if ($entry['price'] !== null): ?> · Rs. <?= branchText($entry['price']) ?><?php endif; ?> · reported <?= branchText($entry['last_reported_at']) ?><?php endif; ?></li>
<?php endforeach; ?></ul>
<form method="post" onsubmit="return confirm('Send your current list to this branch for confirmation?');"><input type="hidden" name="token" value="<?= branchText($_SESSION['branch_token']) ?>"><input type="hidden" name="list_id" value="<?= $listId ?>"><input type="hidden" name="branch_id" value="<?= (int)$branch['id'] ?>"><button>Ask this branch to confirm</button></form>
<?php endif; ?></section><?php endforeach; ?>
<?php if (!$branches): ?><p>No active branches matched your search.</p><?php endif; ?>
<section class="card"><h2>My availability requests</h2><?php if (!$requests): ?><p>No requests sent yet.</p><?php else: ?>
<?php foreach ($requests as $request): ?><details><summary><?= branchText($request['branch_name']) ?> · <?= branchText($request['list_name']) ?> · <?= branchText($request['status'] === 'responded' ? 'Responded' : 'Awaiting response') ?> (<?= branchText($request['requested_at']) ?>)</summary>
<p class="muted"><?= branchText($request['area']) ?> · <?= branchText($request['address']) ?><?php if ($request['responded_at']): ?> · confirmed <?= branchText($request['responded_at']) ?><?php endif; ?></p><ul class="list">
<?php foreach ($requestItems[(int)$request['id']] ?? [] as $item): ?><li><?= branchText($item['product_name']) ?> × <?= (int)$item['requested_quantity'] ?>: <?= branchText($request['status'] === 'responded' ? ($responseLabels[$item['response']] ?? 'Unknown') : 'Awaiting confirmation') ?><?php if ($request['status'] === 'responded' && $item['note'] !== ''): ?> · <?= branchText($item['note']) ?><?php endif; ?></li><?php endforeach; ?></ul></details><?php endforeach; ?>
<?php endif; ?></section></main></body></html>
