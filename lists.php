<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: customer-account.php');
    exit;
}
require __DIR__ . '/db.php';
$customerId = (int) $_SESSION['user_id'];
$_SESSION['list_token'] ??= bin2hex(random_bytes(32));
function listText(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function ownedList(PDO $db, int $listId, int $customerId): bool {
    $stmt = $db->prepare('SELECT id FROM saved_lists WHERE id = ? AND customer_id = ?');
    $stmt->execute([$listId, $customerId]);
    return (bool) $stmt->fetchColumn();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['list_token'], (string) ($_POST['token'] ?? ''))) {
        http_response_code(403); exit('Invalid form. Refresh and try again.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $listId = filter_var($_POST['list_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'create_list' || $action === 'rename_list') {
        $name = trim((string) ($_POST['list_name'] ?? ''));
        if ($name === '' || strlen($name) > 120) $error = 'Enter a list name of at most 120 characters.';
        elseif ($action === 'create_list') {
            $stmt = $db->prepare('INSERT INTO saved_lists (customer_id, list_name) VALUES (?, ?)');
            $stmt->execute([$customerId, $name]);
        } elseif (!ownedList($db, $listId, $customerId)) $error = 'List not found.';
        else {
            $stmt = $db->prepare('UPDATE saved_lists SET list_name = ? WHERE id = ? AND customer_id = ?');
            $stmt->execute([$name, $listId, $customerId]);
        }
    } elseif ($action === 'delete_list' || $action === 'add_item' || $action === 'update_item' || $action === 'delete_item') {
        if (!ownedList($db, $listId, $customerId)) $error = 'List not found.';
        elseif ($action === 'delete_list') {
            // saved_list_items cascade; requests retain their saved snapshot and set list_id to NULL.
            $stmt = $db->prepare('DELETE FROM saved_lists WHERE id = ? AND customer_id = ?');
            $stmt->execute([$listId, $customerId]);
        } elseif ($action === 'add_item') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            if (!$quantity || $quantity < 1 || $quantity > 999) $error = 'Quantity must be between 1 and 999.';
            else {
                $stmt = $db->prepare("SELECT p.id FROM products p WHERE p.id = ? AND p.deleted_at IS NULL AND EXISTS
                  (SELECT 1 FROM product_submissions s WHERE s.product_id = p.id AND s.review_status = 'approved')");
                $stmt->execute([$productId]);
                if (!$stmt->fetchColumn()) $error = 'Select an approved product.';
                else {
                    try {
                        $stmt = $db->prepare('INSERT INTO saved_list_items (list_id, product_id, quantity) VALUES (?, ?, ?)');
                        $stmt->execute([$listId, $productId, $quantity]);
                    } catch (PDOException $exception) { $error = 'This product is already on the list.'; }
                }
            }
        } elseif ($action === 'update_item' || $action === 'delete_item') {
            if ($action === 'update_item' && (!$quantity || $quantity < 1 || $quantity > 999)) $error = 'Quantity must be between 1 and 999.';
            else {
                $sql = $action === 'delete_item'
                    ? 'DELETE i FROM saved_list_items i JOIN saved_lists l ON l.id = i.list_id WHERE i.id = ? AND i.list_id = ? AND l.customer_id = ?'
                    : 'UPDATE saved_list_items i JOIN saved_lists l ON l.id = i.list_id SET i.quantity = ? WHERE i.id = ? AND i.list_id = ? AND l.customer_id = ?';
                $stmt = $db->prepare($sql);
                $stmt->execute($action === 'delete_item' ? [$itemId, $listId, $customerId] : [$quantity, $itemId, $listId, $customerId]);
                if ($stmt->rowCount() === 0 && $action === 'delete_item') $error = 'Item not found.';
            }
        }
    } else $error = 'Unknown list action.';
    if ($error === '') {
        if ($action === 'add_item' && ($_POST['return_to'] ?? '') === 'products.php') {
            header('Location: products.php?added=1');
            exit;
        }
        header('Location: lists.php' . ($action === 'delete_list' || $action === 'create_list' ? '' : '?list_id=' . $listId));
        exit;
    }
}

$stmt = $db->prepare('SELECT id, list_name FROM saved_lists WHERE customer_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$customerId]);
$lists = $stmt->fetchAll();
$selectedId = filter_input(INPUT_GET, 'list_id', FILTER_VALIDATE_INT) ?: (int) ($lists[0]['id'] ?? 0);
$selected = null;
foreach ($lists as $list) if ((int) $list['id'] === $selectedId) $selected = $list;
if ($selectedId && !$selected) { http_response_code(404); exit('List not found.'); }

$products = $db->query("SELECT p.id, s.name FROM products p JOIN product_submissions s ON s.id =
  (SELECT MAX(v.id) FROM product_submissions v WHERE v.product_id = p.id AND v.review_status = 'approved')
  WHERE p.deleted_at IS NULL ORDER BY s.name")->fetchAll();
$items = [];
if ($selected) {
    $stmt = $db->prepare("SELECT i.id, i.quantity, COALESCE(s.name, 'Product no longer public') AS name
       FROM saved_list_items i LEFT JOIN products p ON p.id = i.product_id
       LEFT JOIN product_submissions s ON s.id =
         (SELECT MAX(v.id) FROM product_submissions v WHERE v.product_id = p.id AND v.review_status = 'approved' AND p.deleted_at IS NULL)
       WHERE i.list_id = ? ORDER BY i.id");
    $stmt->execute([$selectedId]);
    $items = $stmt->fetchAll();
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My lists | FoodCompass</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f7faf8;color:#26352d;font:16px/1.5 system-ui,Arial,sans-serif}header{background:#fff;border-bottom:1px solid #dce8df;padding:18px max(20px,calc((100vw - 1100px)/2))}header a{color:#2f7d4a;font-weight:700;margin-right:22px}main{max-width:1100px;margin:40px auto;padding:0 20px}h1,h2{color:#183225}.card{background:#fff;border:1px solid #dce8df;border-radius:16px;padding:22px;margin:20px 0;box-shadow:0 6px 20px #1832250a}.row{display:flex;align-items:end;gap:12px;flex-wrap:wrap}label{font-weight:600}input,select{display:block;padding:10px;border:1px solid #b9d1c0;border-radius:8px;font:inherit;margin-top:6px;max-width:100%}button{padding:10px 14px;background:#28754d;color:#fff;border:0;border-radius:8px;font:inherit;cursor:pointer}button.danger{background:#b3372b}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #dce8df}.error{color:#b3372b}.muted{color:#62756b}.item-actions{display:flex!important;align-items:flex-end!important;gap:12px;flex-wrap:nowrap}.item-actions form{display:inline-flex!important;flex-direction:row!important;align-items:flex-end!important;gap:8px;margin:0!important;vertical-align:bottom}.item-actions button{min-height:46px;margin:0!important;white-space:nowrap}.item-actions input[type=number]{width:95px}</style><link rel="stylesheet" href="assets/customer-nav.css"></head><body>
<header><a href="index.html">FoodCompass</a><a href="products.php">Approved products</a><a href="lists.php" aria-current="page">My lists</a><a href="preferences.php">Recommended</a><a href="branch-search.php">Find a branch</a><a href="customer-account.php">My account</a></header>
<main><h1>My shopping lists</h1><p class="muted">Choose approved products and quantities. Availability is reported by each retailer branch separately.</p>
<?php if ($error): ?><p class="error" role="alert"><?= listText($error) ?></p><?php endif; ?>
<section class="card"><h2>Create a list</h2><form method="post" class="row"><input type="hidden" name="token" value="<?= listText($_SESSION['list_token']) ?>"><input type="hidden" name="action" value="create_list"><label>List name<input name="list_name" maxlength="120" required placeholder="Weekly groceries"></label><button>Create list</button></form></section>
<?php if ($lists): ?><nav class="card" aria-label="Your shopping lists"><?php foreach ($lists as $list): ?><a style="margin-right:20px;color:#28754d" href="lists.php?list_id=<?= (int) $list['id'] ?>"><?= listText($list['list_name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
<?php if ($selected): ?>
<section class="card"><h2><?= listText($selected['list_name']) ?></h2><p><a href="branch-search.php?list_id=<?= $selectedId ?>">Find a branch for this list →</a></p>
<form method="post" class="row"><input type="hidden" name="token" value="<?= listText($_SESSION['list_token']) ?>"><input type="hidden" name="action" value="rename_list"><input type="hidden" name="list_id" value="<?= $selectedId ?>"><label>Rename list<input name="list_name" maxlength="120" required value="<?= listText($selected['list_name']) ?>"></label><button>Save name</button></form>
<form method="post" onsubmit="return confirm('Delete this list?');"><input type="hidden" name="token" value="<?= listText($_SESSION['list_token']) ?>"><input type="hidden" name="action" value="delete_list"><input type="hidden" name="list_id" value="<?= $selectedId ?>"><button class="danger">Delete list</button></form></section>
<section class="card"><h2>Add an approved product</h2>
<?php if (!$products): ?><p>No approved products yet.</p><?php else: ?>
<form method="post" class="row"><input type="hidden" name="token" value="<?= listText($_SESSION['list_token']) ?>"><input type="hidden" name="action" value="add_item"><input type="hidden" name="list_id" value="<?= $selectedId ?>"><label>Product<select name="product_id" required><option value="">Choose...</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= listText($product['name']) ?></option><?php endforeach; ?></select></label><label>Quantity<input type="number" name="quantity" min="1" max="999" value="1" required></label><button>Add product</button></form><?php endif; ?></section>
<section class="card"><h2>List items</h2><?php if (!$items): ?><p>No products on this list yet.</p><?php else: ?><table><thead><tr><th>Product</th><th>Quantity</th><th>Action</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?= listText($item['name']) ?></td><td><?= (int) $item['quantity'] ?></td><td><div class="item-actions">
<form method="post"><input type="hidden" name="token" value="<?= listText($_SESSION['list_token']) ?>"><input type="hidden" name="action" value="update_item"><input type="hidden" name="list_id" value="<?= $selectedId ?>"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><label>New quantity<input type="number" name="quantity" min="1" max="999" required value="<?= (int) $item['quantity'] ?>"></label><button>Save</button></form>
<form method="post" onsubmit="return confirm('Remove this product?');"><input type="hidden" name="token" value="<?= listText($_SESSION['list_token']) ?>"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="list_id" value="<?= $selectedId ?>"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="danger">Remove</button></form>
</div></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php endif; ?></main></body></html>
