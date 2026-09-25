<?php
require_once __DIR__ . '/../config/db.php';
$retailer_id = require_retailer_login();
require_retail_post();
$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    $name = trim((string) ($_POST['branch_name'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $addr = trim((string) ($_POST['address'] ?? ''));
    if ($name === '' || $area === '' || strlen($name) > 120 || strlen($area) > 120 || strlen($addr) > 255) {
        $err = 'Enter a branch name and area within the limits.';
    } else {
        $stmt = $conn->prepare("INSERT INTO branches (retailer_id, branch_name, area, address, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param('isss', $retailer_id, $name, $area, $addr);
        $stmt->execute();
        $ok = 'Branch added.';
    }
}
$stmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
$stmt->bind_param('i', $retailer_id); $stmt->execute();
$retailer = $stmt->get_result()->fetch_assoc();
$stmt = $conn->prepare('SELECT id, branch_name, area, status FROM branches WHERE retailer_id = ? ORDER BY branch_name');
$stmt->bind_param('i', $retailer_id); $stmt->execute();
$branches = $stmt->get_result(); $branch_count = $branches->num_rows;
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM branch_products bp JOIN branches b ON b.id = bp.branch_id WHERE b.retailer_id = ?');
$stmt->bind_param('i', $retailer_id); $stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM availability_requests ar JOIN branches b ON b.id = ar.branch_id WHERE b.retailer_id = ? AND ar.status = 'pending'");
$stmt->bind_param('i', $retailer_id); $stmt->execute();
$pending_requests = $stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Retailer Dashboard | FoodCompass</title>
<link rel="stylesheet" href="assets/retailer.css">
</head>
<body>
<nav>
    <div class="logo">FoodCompass</div>
    <div>
        <a href="../index.html">Home</a>
        <a href="../products.php">Products</a>
        <a href="../compare.html">Compare</a>
        <a class="active" href="retailer_dashboard.php">My branches</a>
        <a href="requests.php">Requests<?php if ($pending_requests) echo " (" . (int)$pending_requests . ")"; ?></a>
    </div>
</nav>
<div class="container">
    <div class="header">
        <div>
            <h1>Retailer Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($retailer['name']); ?></p>
        </div>
    </div>

    <div class="kpi-container">
        <div class="kpi-card"><div class="kpi"><?php echo (int)$branch_count; ?></div><p class="muted">Branches registered</p></div>
        <div class="kpi-card"><div class="kpi"><?php echo (int)$total_products; ?></div><p class="muted">Products listed, all branches</p></div>
        <div class="kpi-card"><div class="kpi"><?php echo (int)$pending_requests; ?></div><p class="muted">Availability requests awaiting a response</p></div>
    </div>

    <div class="section">
        <div class="section-head"><h2>My branches</h2></div>
        <?php if ($err): ?><p class="err"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>
        <?php if ($ok): ?><p class="ok"><?php echo htmlspecialchars($ok); ?></p><?php endif; ?>

        <table>
            <thead><tr><th>Branch</th><th>Area</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody>
            <?php if ($branches->num_rows > 0): while ($b = $branches->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($b['area']); ?></td>
                    <td><span class="status-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                    <td><a class="btn btn-sm" href="branch_products.php?branch_id=<?php echo (int)$b['id']; ?>">Availability</a>
                        <a class="btn btn-outline btn-sm" href="requests.php?branch_id=<?php echo (int)$b['id']; ?>">Requests</a>
                        <a class="btn btn-outline btn-sm" href="branch-details.php?branch_id=<?php echo (int)$b['id']; ?>">Edit branch</a></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="4" class="empty">No branches yet. Add your first one below.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Register a new branch</h2>
        <form class="inline" method="post"><input type="hidden" name="retail_token" value="<?php echo retail_form_token(); ?>">
            <div class="f"><label for="branch_name">Branch name</label><input id="branch_name" name="branch_name" required maxlength="120"></div>
            <div class="f"><label for="area">Area</label><input id="area" name="area" required maxlength="120" placeholder="e.g. Kalutara"></div>
            <div class="f"><label for="address">Address</label><input id="address" name="address" maxlength="255"></div>
            <button class="btn" type="submit" name="add_branch" value="1">Add branch</button>
        </form>
        <p class="muted" style="margin-top:10px">A new branch is active immediately. You can suspend it from Edit branch; past requests remain available.</p>
    </div>
</div>
<footer>© 2026 FoodCompass. All rights reserved.</footer>
</body>
</html>
