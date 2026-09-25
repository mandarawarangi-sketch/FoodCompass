<?php
require_once __DIR__ . '/../config/db.php';
$retailer_id = require_retailer_login(); require_retail_post();
$branch_id = (int) ($_GET['branch_id'] ?? 0);
$branch = branch_owned_by($conn, $branch_id, $retailer_id);
if (!$branch) { http_response_code(404); exit('Branch not found.'); }
$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock = (string) ($_POST['stock_status'] ?? 'unknown');
    $priceText = (string) ($_POST['price'] ?? '');
    $price = $priceText === '' ? null : (float) $priceText;
    if ((isset($_POST['add_product']) || isset($_POST['update_id'])) &&
        (!in_array($stock, ['available','out_of_stock','unknown'], true) ||
         ($priceText !== '' && (!is_numeric($priceText) || $price < 0 || $price > 99999999.99)))) {
        $err = 'Enter a valid availability and non-negative price.';
    } elseif (isset($_POST['add_product'])) {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $check = $conn->prepare("SELECT p.id FROM products p WHERE p.id = ? AND p.deleted_at IS NULL AND EXISTS
            (SELECT 1 FROM product_submissions s WHERE s.product_id = p.id AND s.review_status = 'approved')");
        $check->bind_param('i', $product_id); $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $err = 'Choose an approved, active product.';
        } else {
            $stmt = $conn->prepare('INSERT INTO branch_products (branch_id, product_id, price, stock_status)
                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price), stock_status = VALUES(stock_status), last_reported_at = NOW()');
            $stmt->bind_param('iids', $branch_id, $product_id, $price, $stock);
            $stmt->execute(); $ok = 'Availability saved for this branch.';
        }
    } elseif (isset($_POST['update_id']) && !$err) {
        $id = (int) $_POST['update_id'];
        $stmt = $conn->prepare('UPDATE branch_products SET price = ?, stock_status = ?, last_reported_at = NOW() WHERE id = ? AND branch_id = ?');
        $stmt->bind_param('dsii', $price, $stock, $id, $branch_id);
        $stmt->execute(); $ok = 'Availability updated.';
    } elseif (isset($_POST['delete_id'])) {
        $id = (int) $_POST['delete_id'];
        $stmt = $conn->prepare('DELETE FROM branch_products WHERE id = ? AND branch_id = ?');
        $stmt->bind_param('ii', $id, $branch_id);
        $stmt->execute(); $ok = 'Availability entry removed.';
    }
}
$approvedName = "SELECT s.name FROM product_submissions s WHERE s.product_id = p.id
    AND s.review_status = 'approved' ORDER BY s.id DESC LIMIT 1";
$stmt = $conn->prepare("SELECT bp.id, bp.price, bp.stock_status, bp.last_reported_at,
    p.id AS product_id, ($approvedName) AS product_name
    FROM branch_products bp JOIN products p ON p.id = bp.product_id
    WHERE bp.branch_id = ? AND p.deleted_at IS NULL AND EXISTS
    (SELECT 1 FROM product_submissions x WHERE x.product_id = p.id AND x.review_status = 'approved')
    ORDER BY bp.last_reported_at DESC");
$stmt->bind_param('i', $branch_id); $stmt->execute(); $listed = $stmt->get_result();
$stmt = $conn->prepare("SELECT p.id, ($approvedName) AS name FROM products p
    WHERE p.deleted_at IS NULL AND EXISTS
    (SELECT 1 FROM product_submissions x WHERE x.product_id = p.id AND x.review_status = 'approved')
    AND NOT EXISTS (SELECT 1 FROM branch_products bp WHERE bp.branch_id = ? AND bp.product_id = p.id)
    ORDER BY name");
$stmt->bind_param('i', $branch_id); $stmt->execute(); $available_products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Availability | <?php echo htmlspecialchars($branch['branch_name']); ?> | FoodCompass</title>
<link rel="stylesheet" href="assets/retailer.css">
</head>
<body>
<nav>
    <div class="logo">FoodCompass</div>
    <div>
        <a href="../index.html">Home</a>
        <a href="../products.php">Products</a>
        <a class="active" href="retailer_dashboard.php">My branches</a>
        <a href="requests.php">Requests</a>
    </div>
</nav>
<div class="container">
    <div class="header">
        <div>
            <h1><?php echo htmlspecialchars($branch['branch_name']); ?></h1>
            <p><?php echo htmlspecialchars($branch['area']); ?> · <span class="status-<?php echo $branch['status']; ?>"><?php echo ucfirst($branch['status']); ?></span></p>
        </div>
        <a class="btn btn-outline" href="retailer_dashboard.php">← All branches</a>
    </div>

    <?php if ($err): ?><p class="err"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="ok"><?php echo htmlspecialchars($ok); ?></p><?php endif; ?>

    <div class="section">
        <h2>List a product here</h2>
        <?php if ($available_products->num_rows > 0): ?>
        <form class="inline" method="post"><input type="hidden" name="retail_token" value="<?php echo retail_form_token(); ?>">
            <div class="f"><label for="product_id">Product</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Choose…</option>
                    <?php while ($p = $available_products->fetch_assoc()): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="f"><label for="price">Price (Rs.)</label><input id="price" name="price" type="number" step="0.01" min="0"></div>
            <div class="f"><label for="stock_status">Availability</label>
                <select id="stock_status" name="stock_status">
                    <option value="available">Available</option>
                    <option value="out_of_stock">Out of stock</option>
                    <option value="unknown" selected>Unknown</option>
                </select>
            </div>
            <button class="btn" type="submit" name="add_product" value="1">Add to this branch</button>
        </form>
        <?php else: ?>
            <p class="empty">Every approved product is already listed at this branch.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Last reported availability</h2>
        <p class="muted" style="margin-top:-12px;margin-bottom:16px">This is what customers see as "last reported". Keep it current — it can go stale between updates.</p>
        <table>
            <thead><tr><th>Product</th><th>Price</th><th>Availability</th><th>Last reported</th><th></th></tr></thead>
            <tbody>
            <?php if ($listed->num_rows > 0): while ($row = $listed->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['product_name']); ?>
                        <form id="update-<?php echo (int)$row['id']; ?>" method="post">
                          <input type="hidden" name="retail_token" value="<?php echo retail_form_token(); ?>">
                          <input type="hidden" name="update_id" value="<?php echo (int)$row['id']; ?>">
                        </form>
                    </td>
                    <td>
                        Rs. <input form="update-<?php echo (int)$row['id']; ?>" name="price" type="number" step="0.01" min="0" style="width:90px"
                            value="<?php echo $row['price'] !== null ? htmlspecialchars($row['price']) : ''; ?>">
                    </td>
                    <td>
                        <select form="update-<?php echo (int)$row['id']; ?>" name="stock_status" class="stock">
                            <?php foreach (['available' => 'Available', 'out_of_stock' => 'Out of stock', 'unknown' => 'Unknown'] as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $row['stock_status'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="muted"><?php echo htmlspecialchars($row['last_reported_at']); ?></td>
                    <td>
                        <button form="update-<?php echo (int)$row['id']; ?>" class="btn btn-sm" type="submit">Save</button>
                        <form method="post" onsubmit="return confirm('Remove this product from this branch?');" style="display:inline">
                            <input type="hidden" name="retail_token" value="<?php echo retail_form_token(); ?>"><input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" class="empty">No products listed at this branch yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<footer>© 2026 FoodCompass. All rights reserved.</footer>
</body>
</html>
