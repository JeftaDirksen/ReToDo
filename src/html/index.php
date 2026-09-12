<?php

// Init
ini_set('include_path', '..');
define('DATA_DIR', '/data/');
define('SCHEME', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME']);
define('HOST', $_SERVER['HTTP_HOST']);
//define('CURRENT_FORMATTED_DATETIME', (new DateTime())->format(DateTime::ATOM));

// Requires
require 'mailer.php';

// Create & connect SQLite database
$db = new SQLite3(DATA_DIR . 'retodo.db');
$db->exec('CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)');
$db->exec('CREATE TABLE IF NOT EXISTS user (email TEXT PRIMARY KEY, token TEXT)');

// Db version 1
$db_version = $db->querySingle("SELECT value FROM config WHERE key = 'db_version'");
if (!$db_version) {
    $db->exec("INSERT INTO config (key, value) VALUES ('db_version', '1')");
    $db_version = '1';
}

// Db update v2
if ($db_version === '1') {
    //$db->exec("UPDATE config SET value = '2' WHERE key = 'db_version'");
    //$db_version = '2';
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
// Regenerate session ID if it's older than 24 hours
elseif (time() - $_SESSION['created'] > 24 * 60 * 60) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Handle forms
if (isset($_POST['form'])) {

    // Handle login form
    if ($_POST['form'] === 'login') {
        // Remember the email in a cookie for future logins
        setcookie('email', $_POST['email'], time() + (360 * 24 * 60 * 60), '/');

        // Generate a token and store it in the database
        $token = bin2hex(random_bytes(16));
        $stmt = $db->prepare("INSERT OR REPLACE INTO user (email, token) VALUES (:email, :token)");
        $stmt->bindValue(':email', $_POST['email'], SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->execute();

        // Generate and send the login link via email
        $login_link = SCHEME . '://' . HOST . '/?token=' . $token;
        send_email($_POST['email'], 'ReToDo login link', 'Click here to login: <a href="' . $login_link . '">Login</a>');
        header('Location: /?link_sent');
        exit;
    }
}

// Handle token login link
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $db->prepare("SELECT email FROM user WHERE token = :token AND token IS NOT NULL AND token != ''");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    if ($user) {
        $_SESSION['email'] = $user['email'];
    }
    header('Location: /');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

// Content for the page
$content = '';

// Login form
if (isset($_GET['login']) && !isset($_SESSION['email'])) {
    $email = $_COOKIE['email'] ?? '';
    $content = '<form method="POST">
        <input type="hidden" name="form" value="login">
        <input type="email" name="email" value="' . $email . '" size="20" placeholder="Email" required>
        <button type="submit">Send login link</button>
        </form>';
}

// Not logged in, show login link
elseif (!isset($_SESSION['email'])) {
    if (isset($_GET['link_sent'])) {
        $content = '<font color="green">A login link has been sent to your email, check your inbox and click the link to log in.</font><br><br>';
        $content .= '<a href="/">Continue</a>';
    } else {
        $content = '<a href="?login">Login</a>';
    }
}

// Logged in
elseif (isset($_SESSION['email'])) {

    // Add task form
    if (isset($_GET['add'])) {
        // Step 1
        if (!isset($_GET['step'])) {
            $content = '<form method="GET">
                <input type="hidden" name="add">
                <input type="hidden" name="step" value="2">
                <input type="text" name="task" size="20" placeholder="Task description" required><br>
                Repeat every <input type="number" name="interval" min="1" value="1" required> 
                <select name="recurrence">
                    <option value="daily">day(s)</option>
                    <option value="weekly">week(s)</option>
                    <option value="monthly">month(s)</option>
                    <option value="yearly">year(s)</option>
                </select><br>
                <input type="checkbox" name="repeat_after_completion" value="1" checked> Start new interval after last completion<br>
                Starting from <input type="date" name="start_date" value="' . date('Y-m-d') . '" required><br>
                <button type="submit">Add Task</button>
                </form>';
        } elseif (isset($_GET['step']) && $_GET['step'] === '2') {
            // Step 2

        }
    } else {
        $content = '<a href="?add">Add</a> ';
        $content .= '<a href="?logout">Logout</a><br><br>';
    }
}

session_write_close();
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