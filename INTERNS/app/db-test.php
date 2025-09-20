<?php
$config = require __DIR__.'/config.php';
$dsn = "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  echo "DB OK on DSN: $dsn";
} catch (Throwable $e) {
  header('Content-Type: text/plain; charset=utf-8', true, 500);
  echo "DB FAIL: ".$e->getMessage()."\nDSN: $dsn\n";
}
