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

// Day interval type
if (@$_POST['type'] === 'day_interval') {
    $stmt = $db->prepare("INSERT INTO task (user_id, name, type, interval, recurrence, completion_based, start_date, due_within, due_date)
        VALUES (:user_id, :name, :type, :interval, :recurrence, :completion_based, :start_date, :due_within, date(:start_date, '+' || :due_within || ' days'))");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':name', $_POST['name'], SQLITE3_TEXT);
    $stmt->bindValue(':type', $_POST['type'], SQLITE3_TEXT);
    $stmt->bindValue(':interval', $_POST['interval'] ?? null, SQLITE3_INTEGER);
    $stmt->bindValue(':recurrence', $_POST['recurrence'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':completion_based', isset($_POST['completion_based']) ? 1 : 0, SQLITE3_INTEGER);
    $stmt->bindValue(':start_date', $_POST['start_date'], SQLITE3_TEXT);
    $stmt->bindValue(':due_within', $_POST['due_within'] ?? 1, SQLITE3_INTEGER);
    $stmt->execute();
    redirect('/');
}

// Date interval type
if (@$_POST['type'] === 'date_interval') {
    // Set start date to be in the future and in a selected month
    $start_date = $_POST['start_date'];
    $selected_months = $_POST['months'] ?? [];
    while ($start_date < date('Y-m-d') || !in_array(date('n', strtotime($start_date)), $selected_months)) {
        $start_date = date('Y-m-d', strtotime($start_date . ' +1 month'));
    }
    $stmt = $db->prepare("INSERT INTO task (user_id, name, type, months, start_date, due_within, due_date)
        VALUES (:user_id, :name, :type, :months, :start_date, :due_within, date(:start_date, '+' || :due_within || ' days'))");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':name', $_POST['name'], SQLITE3_TEXT);
    $stmt->bindValue(':type', $_POST['type'], SQLITE3_TEXT);
    $stmt->bindValue(':months', implode(',', $selected_months), SQLITE3_TEXT);
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':due_within', $_POST['due_within'] ?? 1, SQLITE3_INTEGER);
    $stmt->execute();
    redirect('/');
}

// Complete task
if (isset($_GET['complete'])) {
    $task_id = $_GET['complete'];
    $stmt = $db->prepare("SELECT id, completion_based FROM task WHERE id = :id AND user_id = :user_id");
    $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    if ($task) {
        if ($task['completion_based']) {
            $stmt = $db->prepare("UPDATE task
                SET start_date = date('now', '+' || interval || ' ' || recurrence),
                due_date = date('now', '+' || interval || ' ' || recurrence, '+' || due_within || ' days'),
                updated = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("UPDATE task
                SET start_date = date(start_date, '+' || interval || ' ' || recurrence),
                due_date = date(start_date, '+' || interval || ' ' || recurrence, '+' || due_within || ' days'),
                updated = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    redirect('/');
}
