<?php

require '../init.php';

// Token login
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $db->prepare("SELECT id FROM user WHERE token = :token AND token IS NOT NULL AND token != ''");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        // Clear the token after successful login
        $stmt = $db->prepare("UPDATE user SET token = NULL, updated = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();
    }
    redirect('/');
}

// Login link request
if (isset($_POST['email'])) {
    // Remember the email in a cookie for future logins
    setcookie('email', $_POST['email'], time() + (360 * 24 * 60 * 60), '/');

    // Generate a token and store it in the database
    $token = bin2hex(random_bytes(16));
    $stmt = $db->prepare("INSERT INTO user (email, token) VALUES (:email, :token)
        ON CONFLICT(email) DO UPDATE SET token = :token, updated = CURRENT_TIMESTAMP");
    $stmt->bindValue(':email', $_POST['email'], SQLITE3_TEXT);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->execute();

    // Generate and send the login link via email
    $login_link = SCHEME . '://' . HOST . '/action.php?token=' . $token;
    send_email($_POST['email'], 'ReToDo login link', 'Click here to login: <a href="' . $login_link . '">Login</a>');
    redirect('/?link_sent');
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('/');
}

// =============== Authenticated actions below this line ===============

// Handle secure forms
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Unauthorized');
}

// Add task
if (isset($_POST['type'])) {
    $stmt = $db->prepare("INSERT INTO task (user_id, name, type, interval, recurrence, repeat_after_completion, start_date, complete_within, due_date)
        VALUES (:user_id, :name, :type, :interval, :recurrence, :repeat_after_completion, :start_date, :complete_within, date(:start_date, '+' || :complete_within || ' days'))");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':name', $_POST['name'], SQLITE3_TEXT);
    $stmt->bindValue(':type', $_POST['type'], SQLITE3_TEXT);
    $stmt->bindValue(':interval', $_POST['interval'] ?? null, SQLITE3_INTEGER);
    $stmt->bindValue(':recurrence', $_POST['recurrence'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':repeat_after_completion', isset($_POST['repeat_after_completion']) ? 1 : 0, SQLITE3_INTEGER);
    $stmt->bindValue(':start_date', $_POST['start_date'], SQLITE3_TEXT);
    $stmt->bindValue(':complete_within', $_POST['complete_within'] ?? 1, SQLITE3_INTEGER);
    $stmt->execute();
    redirect('/');
}

// Complete task
if (isset($_GET['complete'])) {
    $task_id = $_GET['complete'];
    $stmt = $db->prepare("SELECT id, repeat_after_completion FROM task WHERE id = :id AND user_id = :user_id");
    $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    if ($task) {
        if ($task['repeat_after_completion']) {
            $stmt = $db->prepare("UPDATE task
                SET last_completed_date = date('now'),
                start_date = date('now', '+' || interval || ' ' || recurrence),
                due_date = date(date('now', '+' || interval || ' ' || recurrence), '+' || complete_within || ' days'),
                updated = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("UPDATE task
                SET last_completed_date = date('now'),
                start_date = date(start_date, '+' || interval || ' ' || recurrence),
                due_date = date(date(start_date, '+' || interval || ' ' || recurrence), '+' || complete_within || ' days'),
                updated = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    redirect('/');
}
