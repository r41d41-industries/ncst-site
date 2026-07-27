<?php

declare(strict_types=1);

/**
 * Shared PDO connection (MySQL/MariaDB).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_required('DB_ADDRESS');
    $port = getenv('DB_PORT') ?: '3306';
    $name = env_required('DB_NAME');
    $user = env_required('DB_USER');
    $pass = (string) (getenv('DB_PASSWORD') ?: '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
