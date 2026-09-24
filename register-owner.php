<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $message = 'Enter your name, a valid email, and a password of at least 8 characters.';
    } else {
        try {
            $statement = $db->prepare(
                "INSERT INTO users (name, email, password_hash, role)
                 VALUES (?, ?, ?, 'product_owner')"
            );
            $statement->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            $message = 'Product Owner account created successfully.';
        } catch (PDOException $error) {
            $message = 'Could not create the account. This email may already be registered.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create Product Owner account</title>
</head>
<body>
    <h1>Create Product Owner account</h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

    <form method="post">
        <label>Name <input name="name" required></label><br>
        <label>Email <input name="email" type="email" required></label><br>
        <label>Password <input name="password" type="password" minlength="8" required></label><br>
        <button type="submit">Create account</button>
    </form>
</body>
</html>