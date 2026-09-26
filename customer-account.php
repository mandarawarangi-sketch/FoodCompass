<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/db.php';
$_SESSION['customer_token'] ??= bin2hex(random_bytes(32));
$error = '';
$notice = isset($_GET['saved']) ? 'Account updated.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['customer_token'], (string) ($_POST['token'] ?? ''))) {
        http_response_code(403);
        exit('Form expired. Refresh this page and try again.');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: customer-account.php');
        exit;
    }
    if ($action === 'register') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 255 || strlen($password) < 8) {
            $error = 'Enter a name, a valid email, and a password of at least eight characters.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $db->lastInsertId();
                $_SESSION['role'] = 'customer';
                $_SESSION['name'] = $name;
                header('Location: lists.php');
                exit;
            } catch (PDOException $exception) {
                $error = 'Could not create this account. The email may already be registered.';
            }
        }
    } elseif ($action === 'login') {
        $stmt = $db->prepare("SELECT id, name, password_hash FROM users WHERE email = ? AND role = 'customer'");
        $stmt->execute([trim((string) ($_POST['email'] ?? ''))]);
        $user = $stmt->fetch();
        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = 'customer';
            $_SESSION['name'] = $user['name'];
            header('Location: lists.php');
            exit;
        }
        $error = 'Invalid customer email or password.';
    } elseif ($action === 'update') {
        if (($_SESSION['role'] ?? '') !== 'customer' || empty($_SESSION['user_id'])) {
            http_response_code(403);
            exit('Customer sign-in required.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || strlen($name) > 100) {
            $error = 'Enter a name of at most 100 characters.';
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ? WHERE id = ? AND role = 'customer'");
            $stmt->execute([$name, (int) $_SESSION['user_id']]);
            $_SESSION['name'] = $name;
            header('Location: customer-account.php?saved=1');
            exit;
        }
    } else {
        http_response_code(400);
        exit('Unknown account action.');
    }
}

$customer = null;
if (($_SESSION['role'] ?? '') === 'customer' && !empty($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $customer = $stmt->fetch() ?: null;
}
function customerText(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer account | FoodCompass</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f7faf8;color:#26352d;font:16px/1.5 system-ui,Arial,sans-serif}header{background:#fff;border-bottom:1px solid #dce8df;padding:18px max(20px,calc((100vw - 1000px)/2))}header a{color:#2f7d4a;font-weight:700;margin-right:24px}main{max-width:1000px;margin:44px auto;padding:0 20px}h1,h2{color:#183225}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}.card{background:#fff;border:1px solid #dce8df;border-radius:16px;padding:24px;box-shadow:0 6px 20px #1832250a}label{display:block;font-weight:600;margin:14px 0}input{display:block;width:100%;font:inherit;padding:11px;margin-top:6px;border:1px solid #b9d1c0;border-radius:8px}button,.button{display:inline-block;background:#28754d;color:#fff;border:0;border-radius:8px;padding:11px 17px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}.muted{color:#62756b}.error{color:#a32d24}</style><link rel="stylesheet" href="assets/customer-nav.css"></head><body>
<header><a href="index.html">FoodCompass</a><a href="products.php">Products</a><a href="lists.php">My lists</a><a href="customer-account.php" aria-current="page">My account</a></header>
<main><h1>Customer account</h1>
<?php if ($error): ?><p class="error" role="alert"><?= customerText($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?= customerText($notice) ?></p><?php endif; ?>
<?php if ($customer): ?>
<div class="card"><h2>My profile</h2><p><strong>Email:</strong> <?= customerText($customer['email']) ?></p>
<form method="post"><input type="hidden" name="token" value="<?= customerText($_SESSION['customer_token']) ?>"><input type="hidden" name="action" value="update"><label>Name<input name="name" maxlength="100" required value="<?= customerText($customer['name']) ?>"></label><button>Save name</button></form>
<p><a class="button" href="lists.php">Open my lists</a> <a class="button" href="preferences.php">My food preferences</a></p>
<form method="post"><input type="hidden" name="token" value="<?= customerText($_SESSION['customer_token']) ?>"><input type="hidden" name="action" value="logout"><button>Sign out</button></form></div>
<?php else: ?>
<p class="muted">Sign in to manage your own shopping lists.</p><div class="grid">
<section class="card"><h2>Sign in</h2><form method="post"><input type="hidden" name="token" value="<?= customerText($_SESSION['customer_token']) ?>"><input type="hidden" name="action" value="login"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button>Sign in</button></form></section>
<section class="card"><h2>Create a customer account</h2><form method="post"><input type="hidden" name="token" value="<?= customerText($_SESSION['customer_token']) ?>"><input type="hidden" name="action" value="register"><label>Name<input name="name" maxlength="100" required></label><label>Email<input type="email" name="email" required></label><label>Password (at least eight characters)<input type="password" name="password" minlength="8" required></label><button>Create account</button></form></section>
</div><?php endif; ?></main></body></html>
