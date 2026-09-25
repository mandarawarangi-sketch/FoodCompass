<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $query = $db->prepare(
        "SELECT id, name, password_hash
         FROM users
         WHERE email = ? AND role = 'platform_administrator'
         LIMIT 1"
    );
    $query->execute([$email]);
    $admin = $query->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $admin['id'];
        $_SESSION['role'] = 'platform_administrator';
        $_SESSION['name'] = $admin['name'];

        header('Location: admin-reviews.php');
        exit;
    }

    $error = 'Invalid administrator email or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Administrator sign in | FoodCompass</title>
</head>
<body>
    <h1>Administrator sign in</h1>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Email <input type="email" name="email" required></label><br>
        <label>Password <input type="password" name="password" required></label><br>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>