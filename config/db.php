<?php
/**
 * Simple PDO bootstrap.
 * Configure via environment variables or adjust the defaults below.
 */

declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3307'; // XAMPP MySQL port
$dbName = getenv('DB_NAME') ?: 'agenda_servicos';
$dbUser = getenv('DB_USER') ?: 'admin';
$dbPass = getenv('DB_PASS') ?: 'admin';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
