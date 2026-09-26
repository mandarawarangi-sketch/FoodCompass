<?php
require_once __DIR__ . '/../config/db.php';
$retailer_id = require_retailer_login(); require_retail_post();
$request_id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT ar.id, ar.status, ar.requested_at, ar.branch_id,
    b.branch_name, ar.list_name, u.name AS customer_name
    FROM availability_requests ar JOIN branches b ON b.id = ar.branch_id
    JOIN users u ON u.id = ar.customer_id WHERE ar.id = ? AND b.retailer_id = ?');
$stmt->bind_param('ii', $request_id, $retailer_id); $stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
if (!$request) { http_response_code(404); exit('Request not found for your branches.'); }
$ok = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $query = $conn->prepare('SELECT id FROM request_items WHERE request_id = ? ORDER BY id');
        $query->bind_param('i', $request_id); $query->execute();
        $ids = array_map('intval', array_column($query->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        if (!$ids) throw new RuntimeException('Request has no items.');
        $responses = $_POST['response'] ?? [];
        if (!is_array($responses) || count($responses) !== count($ids)) throw new RuntimeException('Confirm every requested item.');
        foreach ($ids as $item_id) {
            $response = $responses[$item_id] ?? '';
            $note = trim((string) (($_POST['note'] ?? [])[$item_id] ?? ''));
            if (!in_array($response, ['available','unavailable','partial'], true) || strlen($note) > 255) {
                throw new RuntimeException('Choose a confirmation for each item and keep notes under 255 characters.');
            }
            $stmt = $conn->prepare('UPDATE request_items SET response = ?, note = ? WHERE id = ? AND request_id = ?');
            $stmt->bind_param('ssii', $response, $note, $item_id, $request_id); $stmt->execute();
        }
        $stmt = $conn->prepare("UPDATE availability_requests SET status = 'responded', responded_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $request_id); $stmt->execute();
        $conn->commit(); $request['status'] = 'responded';
        $ok = 'Confirmation saved. The customer can view this response.';
    } catch (Throwable $error) {
        $conn->rollback(); $err = $error->getMessage();
    }
}
$stmt = $conn->prepare('SELECT id, response, note, product_name, requested_quantity
    FROM request_items WHERE request_id = ? ORDER BY id');
$stmt->bind_param('i', $request_id); $stmt->execute(); $items = $stmt->get_result();
$labels = ['available'=>'Available','unavailable'=>'Unavailable','partial'=>'Partially available','unknown'=>'Not yet checked'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Respond to Request | FoodCompass</title>
<link rel="stylesheet" href="assets/retailer.css">
</head>
<body>
<nav>
    <div class="logo">FoodCompass</div>
    <div>
        <a href="../index.html">Home</a>
        <a href="retailer_dashboard.php">My branches</a>
        <a class="active" href="requests.php">Requests</a>
    </div>
</nav>
<div class="container">
    <div class="header">
        <div>
            <h1>Respond to <?php echo htmlspecialchars($request['customer_name']); ?>'s request</h1>
            <p>"<?php echo htmlspecialchars($request['list_name']); ?>" · sent to <?php echo htmlspecialchars($request['branch_name']); ?> · <?php echo htmlspecialchars($request['requested_at']); ?></p>
        </div>
        <a class="btn btn-outline" href="requests.php">← All requests</a>
    </div>

    <?php if ($ok): ?><p class="ok"><?php echo htmlspecialchars($ok); ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>

    <div class="section">
        <form method="post"><input type="hidden" name="retail_token" value="<?php echo retail_form_token(); ?>">
            <table>
                <thead><tr><th>Product</th><th>Requested qty</th><th>Your response</th><th>Note (optional)</th></tr></thead>
                <tbody>
                <?php while ($it = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                        <td><?php echo (int) $it['requested_quantity']; ?></td>
                        <td>
                            <select name="response[<?php echo (int)$it['id']; ?>]" class="stock">
                                <?php foreach ($labels as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $it['response'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" maxlength="255" style="width:100%"
                                name="note[<?php echo (int)$it['id']; ?>]"
                                value="<?php echo htmlspecialchars($it['note']); ?>"
                                placeholder="e.g. only 1 left"></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <div style="margin-top:18px"><button class="btn" type="submit">Save response</button></div>
        </form>
    </div>
</div>
<footer>© 2026 FoodCompass. All rights reserved.</footer>
</body>
</html>
