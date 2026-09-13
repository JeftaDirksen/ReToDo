<?php

// Static variables
define('DATA_DIR', '/data/');
define('SCHEME', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME']);
define('HOST', $_SERVER['HTTP_HOST']);

// Requires
require 'functions.php';

// Create & connect SQLite database
$db = new SQLite3(DATA_DIR . 'retodo.db');
$db->exec('CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)');
$db->exec('CREATE TABLE IF NOT EXISTS user (id INTEGER PRIMARY KEY, email TEXT UNIQUE COLLATE NOCASE, token TEXT, created DATETIME DEFAULT CURRENT_TIMESTAMP, updated DATETIME DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE IF NOT EXISTS task (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    name TEXT,
    type TEXT,
    interval INTEGER,
    recurrence TEXT,
    repeat_after_completion BOOLEAN,
    start_date DATE,
    complete_within INTEGER,
    last_completed_date DATE,
    due_date DATE,
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME DEFAULT CURRENT_TIMESTAMP,
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
