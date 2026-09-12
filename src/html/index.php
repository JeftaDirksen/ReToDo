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
$db->exec('CREATE TABLE IF NOT EXISTS user (id INTEGER PRIMARY KEY, email TEXT UNIQUE COLLATE NOCASE, token TEXT)');
$db->exec('CREATE TABLE IF NOT EXISTS task (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    name TEXT,
    type TEXT,
    interval INTEGER,
    recurrence TEXT,
    repeat_after_completion BOOLEAN,
    start_date DATE,
    last_completed_date DATE,
    due_date DATE,
    FOREIGN KEY(user_id) REFERENCES user(id)
    )');

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

    // Login form
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

    // Handle secure forms
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 403 Forbidden');
        die('Unauthorized');
    }

    // Add task form
    if ($_POST['form'] === 'add_task') {
        $stmt = $db->prepare("INSERT INTO task (user_id, name, type, interval, recurrence, repeat_after_completion, start_date, due_date) VALUES (:user_id, :name, :type, :interval, :recurrence, :repeat_after_completion, :start_date, :due_date)");
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':name', $_POST['name'], SQLITE3_TEXT);
        $stmt->bindValue(':type', $_POST['type'], SQLITE3_TEXT);
        $stmt->bindValue(':interval', $_POST['interval'] ?? null, SQLITE3_INTEGER);
        $stmt->bindValue(':recurrence', $_POST['recurrence'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':repeat_after_completion', isset($_POST['repeat_after_completion']) ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':start_date', $_POST['start_date'], SQLITE3_TEXT);
        $stmt->bindValue(':due_date', $_POST['start_date'], SQLITE3_TEXT);
        $stmt->execute();
        header('Location: /');
        exit;
    }

}

// Handle token login link
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $db->prepare("SELECT id FROM user WHERE token = :token AND token IS NOT NULL AND token != ''");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        // Clear the token after successful login
        $stmt = $db->prepare("UPDATE user SET token = NULL WHERE id = :id");
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();
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
if (isset($_GET['login']) && !isset($_SESSION['user_id'])) {
    $email = $_COOKIE['email'] ?? '';
    $content = '<form method="POST">
        <input type="hidden" name="form" value="login">
        <input type="email" name="email" value="' . $email . '" size="20" placeholder="Email" required>
        <button type="submit">Send login link</button>
        </form>';
}

// Not logged in, show login link
elseif (!isset($_SESSION['user_id'])) {
    if (isset($_GET['link_sent'])) {
        $content = '<font color="green">A login link has been sent to your email, check your inbox and click the link to log in.</font><br><br>';
        $content .= '<a href="/">Continue</a>';
    } else {
        $content = '<a href="?login">Login</a>';
    }
}

// Logged in
elseif (isset($_SESSION['user_id'])) {

    // Add task form
    if (isset($_GET['add'])) {
        // Type
        if (!isset($_GET['type'])) {
            $content = '<form method="GET">
                <input type="hidden" name="add">
                <input type="text" name="name" size="20" placeholder="Task description" required><br>
                <select name="type">
                    <option value="static_interval">Repeat every # days/weeks/months</option>
                    <option value="day_number">Repeat on every #th of the month</option>
                    <option value="day_of_month">Repeat every #th ...day of the month</option>
                </select><br>
                <button type="submit">Next</button>';
        }

        // Static interval type
        elseif (isset($_GET['type']) && $_GET['type'] === 'static_interval') {
            $content = '<form method="POST">
                <input type="hidden" name="form" value="add_task">
                <input type="hidden" name="type" value="static_interval">
                <input type="text" name="name" size="20" value="' . $_GET['name'] . '" required readonly><br>
                Repeat every <input type="number" name="interval" min="1" value="1" required> 
                <select name="recurrence">
                    <option value="daily">day(s)</option>
                    <option value="weekly">week(s)</option>
                    <option value="monthly">month(s)</option>
                </select><br>
                <input type="checkbox" name="repeat_after_completion" value="1" checked> Start new interval after last completion<br>
                Starting from <input type="date" name="start_date" value="' . date('Y-m-d') . '" required><br>
                <button type="submit">Add Task</button>
                </form>';
        }
    } else {
        $content = '<a href="?add">Add</a> ';
        $content .= '<a href="?logout">Logout</a><br><br>';

        // List tasks
        $stmt = $db->prepare("SELECT *, strftime('%J', due_date) - strftime('%J', date('now')) AS due_in FROM task WHERE user_id = :user_id ORDER BY due_in ASC");
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $content .= '<table border="1" cellpadding="5" cellspacing="0">';
        $content .= '<tr><th>Name</th><th>Type</th><th>Interval</th><th>Recurrence</th><th>Repeat After Completion</th><th>Start Date</th><th>Last Completed Date</th><th>Due Date</th><th>Due In</th></tr>';
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $content .= '<tr>';
            $content .= '<td>' . htmlspecialchars($row['name']) . '</td>';
            $content .= '<td>' . htmlspecialchars($row['type']) . '</td>';
            $content .= '<td>' . htmlspecialchars($row['interval']) . '</td>';
            $content .= '<td>' . htmlspecialchars($row['recurrence']) . '</td>';
            $content .= '<td>' . ($row['repeat_after_completion'] ? 'Yes' : 'No') . '</td>';
            $content .= '<td>' . htmlspecialchars($row['start_date']) . '</td>';
            $content .= '<td>' . htmlspecialchars($row['last_completed_date'] ?? '') . '</td>';
            $content .= '<td>' . htmlspecialchars($row['due_date'] ?? '') . '</td>';
            $content .= '<td>' . htmlspecialchars($row['due_in']) . '</td>';
            $content .= '</tr>';
        }
        $content .= '</table>';
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