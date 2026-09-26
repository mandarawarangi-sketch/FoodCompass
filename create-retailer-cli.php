<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/db.php';
function ask(string $label): string { fwrite(STDOUT, $label); return trim((string) fgets(STDIN)); }
$name = ask('Retail Store name: ');
$email = ask('Retail Store email: ');
$password = ask('Password (visible while typing): ');
if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Enter a name, valid email and password of at least 12 characters.\n"); exit(1);
}
try {
    $insert = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'retail_store')");
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Retail Store account created. Remove this script from the web folder after use.\n");
} catch (PDOException $error) {
    fwrite(STDERR, "Could not create account; check connection and email uniqueness.\n"); exit(1);
}
