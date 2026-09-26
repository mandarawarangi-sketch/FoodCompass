<?php
require_once "../config/db.php";
$retailer_id = require_retailer_login();
require_retail_post();

$branch_filter = (int) ($_GET['branch_id'] ?? 0);
if ($branch_filter && !branch_owned_by($conn, $branch_filter, $retailer_id)) {
    die("Branch not found, or it does not belong to your account.");
}

$sql = "SELECT ar.id, ar.status, ar.requested_at, ar.responded_at,
               b.branch_name, ar.list_name, u.name AS customer_name
        FROM availability_requests ar
        JOIN branches b     ON b.id = ar.branch_id
        JOIN users u        ON u.id = ar.customer_id
        WHERE b.retailer_id = ?" . ($branch_filter ? " AND b.id = ?" : "") . "
        ORDER BY (ar.status = 'pending') DESC, ar.requested_at DESC";
$stmt = $conn->prepare($sql);
if ($branch_filter) { $stmt->bind_param("ii", $retailer_id, $branch_filter); }
else { $stmt->bind_param("i", $retailer_id); }
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Availability Requests | FoodCompass</title>
<link rel="stylesheet" href="assets/retailer.css">
</head>
<body>
<nav>
    <div class="logo">FoodCompass</div>
    <div>
        <a href="../index.html">Home</a>
        <a href="../products.php">Products</a>
        <a href="retailer_dashboard.php">My branches</a>
        <a class="active" href="requests.php">Requests</a>
    </div>
</nav>
<div class="container">
    <div class="header">
        <div>
            <h1>Availability requests</h1>
            <p>Customers who compared their list against your reported availability and chose one of your branches.</p>
        </div>
        <?php if ($branch_filter): ?><a class="btn btn-outline" href="requests.php">Show all branches</a><?php endif; ?>
    </div>

    <div class="section">
        <table>
            <thead><tr><th>Customer</th><th>List</th><th>Branch</th><th>Requested</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if ($requests->num_rows > 0): while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['list_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['branch_name']); ?></td>
                    <td class="muted"><?php echo htmlspecialchars($r['requested_at']); ?></td>
                    <td><span class="status-<?php echo $r['status']; ?>"><?php echo $r['status'] === 'pending' ? 'Awaiting response' : 'Responded'; ?></span></td>
                    <td><a class="btn btn-sm" href="respond.php?id=<?php echo (int)$r['id']; ?>">
                        <?php echo $r['status'] === 'pending' ? 'Respond' : 'View response'; ?></a></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6" class="empty">No availability requests yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<footer>© 2026 FoodCompass. All rights reserved.</footer>
</body>
</html>
