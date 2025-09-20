<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
ini_set('log_errors',1);
error_reporting(E_ALL);

// also log fatals here:
$__errlog = __DIR__ . '/../app/php_errors.log';
ini_set('error_log', $__errlog);
register_shutdown_function(function () use ($__errlog) {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) {
    if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "FATAL: {$e['message']} in {$e['file']}:{$e['line']}\nLog: {$__errlog}\n";
  }
});

echo "STEP 1\n";
require_once __DIR__ . '/../app/session.php';
echo "STEP 2 session OK\n";

require_once __DIR__ . '/../app/db.php';
echo "STEP 3 db OK\n";

require_once __DIR__ . '/../app/helpers.php';
echo "STEP 4 helpers OK\n";

echo "DONE\n";
