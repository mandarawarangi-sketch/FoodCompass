<?php
declare(strict_types=1);
// Run locally from a terminal. Web requests cannot execute this script.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/db.php';
function prompt(string $label): string {
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}
$name = prompt('Administrator name: ');
$email = prompt('Administrator email: ');
$password = prompt('Temporary password (visible while typing): ');
if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($password) < 12) {
    fwrite(STDERR, "Name, valid email, and password of at least 12 characters are required.\n");
    exit(1);
}
try {
    $statement = $db->prepare(
        "INSERT INTO users (name, email, password_hash, role)
         VALUES (?, ?, ?, 'platform_administrator')"
    );
    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Administrator created. Delete this setup script after use.\n");
} catch (PDOException $error) {
    fwrite(STDERR, "Could not create administrator. Check the database connection and email uniqueness.\n");
    exit(1);
}
