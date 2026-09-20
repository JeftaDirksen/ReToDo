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

// Add/edit task

// Add/edit day_interval
if (@$_POST['type'] === 'day_interval') {
    $stmt = $db->prepare("INSERT INTO task (id, user_id, name, type, interval, recurrence, completion_based, start_date, due_within, due_date)
        VALUES (:id, :user_id, :name, :type, :interval, :recurrence, :completion_based, :start_date, :due_within, date(:start_date, '+' || :due_within || ' days'))
        ON CONFLICT(id) DO UPDATE SET name = :name, interval = :interval, recurrence = :recurrence, completion_based = :completion_based, start_date = :start_date, due_within = :due_within, due_date = date(:start_date, '+' || :due_within || ' days') WHERE id = :id AND user_id = :user_id");
    $stmt->bindValue(':id', empty($_POST['edit']) ? null : $_POST['edit'], SQLITE3_INTEGER);
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

// Add/edit date_interval
if (@$_POST['type'] === 'date_interval') {
    // Make sure start date is in a selected month
    $start_date = $_POST['start_date'];
    $selected_months = $_POST['months'] ?? [];
    while (!in_array(date('n', strtotime($start_date)), $selected_months)) {
        $start_date = date('Y-m-d', strtotime($start_date . ' +1 month'));
    }
    $stmt = $db->prepare("INSERT INTO task (id, user_id, name, type, selection, start_date, due_within, due_date)
        VALUES (:id, :user_id, :name, :type, :selection, :start_date, :due_within, date(:start_date, '+' || :due_within || ' days'))
        ON CONFLICT(id) DO UPDATE SET name = :name, selection = :selection, start_date = :start_date, due_within = :due_within, due_date = date(:start_date, '+' || :due_within || ' days') WHERE id = :id AND user_id = :user_id");
    $stmt->bindValue(':id', empty($_POST['edit']) ? null : $_POST['edit'], SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':name', $_POST['name'], SQLITE3_TEXT);
    $stmt->bindValue(':type', $_POST['type'], SQLITE3_TEXT);
    $stmt->bindValue(':selection', implode(',', $selected_months), SQLITE3_TEXT);
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':due_within', $_POST['due_within'] ?? 1, SQLITE3_INTEGER);
    $stmt->execute();
    redirect('/');
}

// Complete task
if (isset($_GET['complete'])) {
    $task_id = $_GET['complete'];
    $stmt = $db->prepare("SELECT * FROM task WHERE id = :id AND user_id = :user_id");
    $stmt->bindValue(':id', $task_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    if ($task) {

        // Day interval type
        if ($task['type'] === 'day_interval') {
            if ($task['completion_based']) {
                $start_date = strtotime(date('Y-m-d') . ' +' . $task['interval'] . ' ' . $task['recurrence']);
                $stmt = $db->prepare("UPDATE task
                    SET start_date = :start_date,
                    due_date = date(:start_date, '+' || due_within || ' days'),
                    updated = CURRENT_TIMESTAMP
                    WHERE id = :id");
            } else {
                $start_date = strtotime($task['start_date'] . ' +' . $task['interval'] . ' ' . $task['recurrence']);
                // Make sure the new start date is in the future
                while ($start_date < strtotime(date('Y-m-d'))) {
                    $start_date = strtotime(date('Y-m-d', $start_date) . ' +' . $task['interval'] . ' ' . $task['recurrence']);
                }
                $stmt = $db->prepare("UPDATE task
                    SET start_date = :start_date,
                    due_date = date(:start_date, '+' || due_within || ' days'),
                    updated = CURRENT_TIMESTAMP
                    WHERE id = :id");
            }
            $stmt->bindValue(':start_date', date('Y-m-d', $start_date), SQLITE3_TEXT);
            $stmt->bindValue(':id', $task['id'], SQLITE3_INTEGER);
            $stmt->execute();
        }

        // Date interval type
        elseif ($task['type'] === 'date_interval') {
            // Set new start date to be in the future and in a selected month
            $start_date = strtotime($task['start_date']);
            $selected_months = explode(',', $task['selection']);
            while ($start_date < strtotime(date('Y-m-d')) || !in_array(date('n', $start_date), $selected_months)) {
                $start_date = strtotime($start_date . ' +1 month');
            }
            $stmt = $db->prepare("UPDATE task
                SET start_date = :start_date,
                due_date = date(:start_date, '+' || due_within || ' days'),
                updated = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->bindValue(':id', $task['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':start_date', date('Y-m-d', $start_date), SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    redirect('/');
}
