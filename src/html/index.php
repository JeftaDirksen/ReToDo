<?php

// Init
ini_set('include_path', '..');
define('SCHEME', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME']);
define('HOST', $_SERVER['HTTP_HOST']);
define('DATA_DIR', '/data/');
define('CURRENT_FORMATTED_DATETIME', (new DateTime())->format(DateTime::ATOM));

// Create & connect SQLite database
$db = new SQLite3(DATA_DIR . 'retodo.db');
$db->exec('CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)');

// Db version 1
$db_version = $db->querySingle("SELECT value FROM config WHERE key = 'db_version'");
if (!$db_version) {
    $db->exec("INSERT INTO config (key, value) VALUES ('db_version', '1')");
    $db_version = '1';
}

// Db update v2
if ($db_version === '1') {
    #$db->exec("UPDATE config SET value = '2' WHERE key = 'db_version'");
    #$db_version = '2';
}

// Add salt to config if not already present
$result = $db->query("SELECT value FROM config WHERE key = 'salt'");
if ($result->fetchArray() === false) {
    $salt = bin2hex(random_bytes(16));
    $db->exec("INSERT INTO config (key, value) VALUES ('salt', '$salt')");
}

// Get config from database
$r = $db->query("SELECT * FROM config");
$config = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $config[$row['key']] = $row['value'];
}

// Start session
$session_days = 30; // 30 days session lifetime
$sessions_dir = DATA_DIR . 'sessions';
if (!is_dir($sessions_dir)) mkdir($sessions_dir, 0700, true);
session_save_path($sessions_dir);
ini_set('session.gc_maxlifetime', $session_days * 24 * 60 * 60);
session_set_cookie_params($session_days * 24 * 60 * 60);
session_start();
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
}
// Regenerate session every 10% of the session lifetime
elseif (time() - $_SESSION['created'] > 0.1 * $session_days * 24 * 60 * 60) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Handle POST requests
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        setcookie('email', $_POST['email'], time() + (360 * 24 * 60 * 60), '/');
        die('Not implemented yet');
    }
}

// Content for the page
$content = '';
if (isset($_GET['login'])) {
    $content = '<form method="POST">';
    $content .= '<input type="hidden" name="action" value="login">';
    $email = $_COOKIE['email'] ?? '';
    $content .= '<input type="email" name="email" value="' . $email . '" size="20" placeholder="Email" required> ';
    $content .= '<button type="submit">Send login link</button>';
    $content .= '</form>';
} else {
    $content = '<a href="?login">Login</a>';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReToDo</title>
    <!--<link rel="stylesheet" href="style.css">-->
    <link type="image/png" sizes="16x16" rel="icon" href="icon-16.png">
    <link type="image/png" sizes="72x72" rel="icon" href="icon-72.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="manifest.json">
</head>

<body>
    <h1>ReToDo</h1>
    <p>Welcome to ReToDo! This is a simple web application for managing your recurring tasks.</p>
    <p><?php echo $content ?></p>
</body>

</html>