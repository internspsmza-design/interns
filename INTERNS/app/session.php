<?php
declare(strict_types=1);

/* Start session early and safely */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* Find APP_BASE no matter how config is structured */
if (!function_exists('session_app_base')) {
  function session_app_base(): string {
    // If a constant APP_BASE exists, use it
    if (defined('APP_BASE')) {
      return rtrim((string)APP_BASE, '/');
    }
    // If a $config array exists in global scope, use it
    if (isset($GLOBALS['config']) && is_array($GLOBALS['config']) && isset($GLOBALS['config']['APP_BASE'])) {
      return rtrim((string)$GLOBALS['config']['APP_BASE'], '/');
    }
    // Last resort: assume project root folder name (adjust if your app is not in /INTERNS)
    return '/INTERNS';
  }
}

/* Optional tiny debug switch: set to true to see session info on every page (local only) */
$SESSION_DEBUG = false;  // change to true temporarily if you need

/* -----------------------------------------------
   Auth helpers
   ----------------------------------------------- */

if (!function_exists('require_login')) {
  function require_login(): void {
    $base = session_app_base();

    // Already logged in?
    if (!empty($_SESSION['user']) && isset($_SESSION['user']['id'])) {
      return;
    }

    // Not logged in → send to login with ?next=
    $here = $_SERVER['REQUEST_URI'] ?? ($base . '/');
    // Prevent header issues if output started accidental
    if (!headers_sent()) {
      header('Location: ' . $base . '/auth/login.php?next=' . urlencode($here));
    } else {
      echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($base . '/auth/login.php?next=' . urlencode($here), ENT_QUOTES, 'UTF-8') . '">';
    }
    exit;
  }
}

if (!function_exists('require_role')) {
  /**
   * Restrict page to specific roles.
   * Usage: require_role(['admin']); or require_role(['student','admin']);
   */
  function require_role(array $roles): void {
    require_login(); // ensure logged in first

    $role = (string)($_SESSION['user']['role'] ?? '');
    if (in_array($role, $roles, true)) {
      return; // allowed
    }

    // Logged in but not allowed → show a proper 403 page (no blank screen)
    http_response_code(403);
    $base = session_app_base();
    ?>
    <!doctype html>
    <html lang="en"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <title>403 · Access denied</title>
      <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/style.css">
      <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:2rem}</style>
    </head><body>
      <h1>Access denied</h1>
      <p>Your role <code><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></code> is not permitted to view this page.</p>
      <p><a class="btn" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/">Go home</a></p>
    </body></html>
    <?php
    exit;
  }
}

/* -----------------------------------------------
   Optional: a tiny session debug banner (local)
   ----------------------------------------------- */
if ($SESSION_DEBUG && php_sapi_name() !== 'cli') {
  $u = $_SESSION['user'] ?? null;
  $who = $u ? ('#'.($u['id']??'?').' · '.($u['role']??'?').' · '.($u['email']??'?')) : 'guest';
  echo '<div style="position:fixed;z-index:9999;left:8px;bottom:8px;padding:6px 10px;border-radius:8px;background:#111;color:#fff;opacity:.85;font:12px/1.2 system-ui">';
  echo 'SESSION: ' . htmlspecialchars($who, ENT_QUOTES, 'UTF-8');
  echo '</div>';
}
