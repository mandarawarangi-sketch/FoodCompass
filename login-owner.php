<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $db->prepare(
        "SELECT id, name, password_hash
         FROM users
         WHERE email = ? AND role = 'product_owner'"
    );
    $statement->execute([$email]);
    $owner = $statement->fetch();

    if ($owner && password_verify($password, $owner['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $owner['id'];
        $_SESSION['role'] = 'product_owner';
        $_SESSION['name'] = $owner['name'];

        $message = 'Signed in as ' . $owner['name'];
    } else {
        $message = 'Incorrect email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Product Owner sign in</title>
</head>
<body>
    <h1>Product Owner sign in</h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

    <form method="post">
        <label>Email <input name="email" type="email" required></label><br>
        <label>Password <input name="password" type="password" required></label><br>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>