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

        header('Location: product-owner.php');
        exit;
    } else {
        $message = 'Incorrect email or password.';
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Product Owner sign in | FoodCompass</title>
<style>
:root{--bg:#f2f5f1;--panel:#fff;--ink:#14231c;--mute:#5c6b63;--line:#dfe6e0;--accent:#1f6f5b;--soft:#e3f0ea}
@media(prefers-color-scheme:dark){:root{--bg:#0f1713;--panel:#17221c;--ink:#e8f0eb;--mute:#93a59b;--line:#26352d;--accent:#5cc4a4;--soft:#1d3129}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,Arial,sans-serif;display:grid;place-items:center;padding:24px}
main{width:min(440px,100%);background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:32px;box-shadow:0 10px 35px #0001}
.brand{color:var(--accent);font-weight:800;font-size:20px;text-decoration:none}h1{margin:20px 0 4px;font-size:30px}p{color:var(--mute);margin:0 0 22px}
label{display:block;font-size:14px;font-weight:600;margin:16px 0 6px}input{width:100%;padding:12px;font:inherit;color:var(--ink);background:var(--bg);border:1px solid var(--line);border-radius:9px}
button{width:100%;margin-top:22px;padding:12px;border:0;border-radius:9px;background:var(--accent);color:var(--bg);font:inherit;font-weight:700;cursor:pointer}
input:focus,button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}.notice{background:var(--soft);padding:12px;border-radius:8px;color:var(--ink)}
.footer{display:block;text-align:center;margin-top:22px;color:var(--accent)}
</style></head><body><main><a class="brand" href="index.html">🧭 FoodCompass</a><h1>Product Owner sign in</h1><p>Access your FoodCompass workspace.</p>
<?php if ($message !== ''): ?><p class="notice" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required>
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Sign in</button></form><a class="footer" href="index.html">Back to home</a></main></body></html>
