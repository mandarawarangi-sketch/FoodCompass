<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = $db->prepare("SELECT id, name, password_hash FROM users WHERE email = ? AND role = 'retail_store'");
    $query->execute([trim((string) ($_POST['email'] ?? ''))]);
    $retailer = $query->fetch();
    if ($retailer && password_verify((string) ($_POST['password'] ?? ''), $retailer['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $retailer['id'];
        $_SESSION['role'] = 'retail_store';
        $_SESSION['name'] = $retailer['name'];
        header('Location: retailer/retailer_dashboard.php');
        exit;
    }
    $error = 'Invalid Retail Store email or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Retail Store sign in | FoodCompass</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7faf8;color:#26352d;font:16px system-ui;padding:24px}main{width:min(440px,100%);background:#fff;border:1px solid #dce8df;border-radius:18px;padding:32px;box-shadow:0 10px 30px #18322514}a{color:#2f7d4a}h1{color:#183225}label{display:block;margin:16px 0 6px;font-weight:700}input{width:100%;padding:12px;border:1px solid #dce8df;border-radius:9px;font:inherit}button{width:100%;margin-top:22px;padding:12px;border:0;border-radius:9px;background:#2f7d4a;color:#fff;font:inherit;font-weight:700;cursor:pointer}.error{color:#b3372b}</style>
</head><body><main><a href="index.html">🧭 FoodCompass</a><h1>Retail Store sign in</h1>
<?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button>Sign in</button></form></main></body></html>
