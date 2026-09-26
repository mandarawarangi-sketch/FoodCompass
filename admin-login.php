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
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administrator sign in | FoodCompass</title>
<style>
* { box-sizing: border-box; }
body { margin: 0; min-height: 100vh; padding: 24px; display: grid; place-items: center; background: #f7faf8; color: #26352d; font: 16px/1.5 Inter, system-ui, Arial, sans-serif; }
main { width: min(440px, 100%); padding: 32px; background: #fff; border: 1px solid #dce8df; border-radius: 18px; box-shadow: 0 10px 30px rgba(24, 50, 37, .08); }
.brand { color: #2f7d4a; font-size: 20px; font-weight: 800; text-decoration: none; }
h1 { margin: 20px 0 4px; color: #183225; font-size: 30px; }
p { margin: 0 0 22px; color: #6d7b73; }
label { display: block; margin: 16px 0 6px; font-size: 14px; font-weight: 700; }
input { width: 100%; padding: 12px; color: #26352d; background: #fff; border: 1px solid #dce8df; border-radius: 9px; font: inherit; }
button { width: 100%; margin-top: 22px; padding: 12px; color: #fff; background: #2f7d4a; border: 0; border-radius: 9px; font: inherit; font-weight: 700; cursor: pointer; }
input:focus, button:focus-visible { outline: 2px solid #2f7d4a; outline-offset: 2px; }
.notice { padding: 12px; color: #26352d; background: #eaf7ef; border-radius: 8px; }
.footer { display: block; margin-top: 22px; color: #2f7d4a; text-align: center; }
</style></head><body><main><a class="brand" href="index.html">🧭 FoodCompass</a><h1>Administrator sign in</h1><p>Access your FoodCompass workspace.</p>
<?php if ($error !== ''): ?><p class="notice" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required>
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Sign in</button></form><a class="footer" href="index.html">Back to home</a></main></body></html>
