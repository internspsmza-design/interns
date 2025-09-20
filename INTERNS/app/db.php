<?php
$config = require __DIR__.'/config.php';
$dsn = "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
  $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "DB CONNECTION FAILED\n".$e->getMessage()."\n\nDSN: {$dsn}\nUSER: {$config['DB_USER']}\n";
  exit;
}
