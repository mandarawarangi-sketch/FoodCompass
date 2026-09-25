<?php
declare(strict_types=1);

$host = getenv('FOODCOMPASS_DB_HOST') ?: '127.0.0.1';
$port = getenv('FOODCOMPASS_DB_PORT') ?: '3306';
$name = getenv('FOODCOMPASS_DB_NAME') ?: 'foodcompass';
$user = getenv('FOODCOMPASS_DB_USER') ?: 'root';
$password = getenv('FOODCOMPASS_DB_PASSWORD') ?: '';

$db = new PDO(
    "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);