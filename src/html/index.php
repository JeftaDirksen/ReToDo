<?php

require '../init.php';

$content = '';

// Login form
if (isset($_GET['login']) && !isset($_SESSION['user_id'])) {
    $email = $_COOKIE['email'] ?? '';
    $content = '<form method="POST" action="action.php">
            <input type="email" name="email" value="' . $email . '" size="20" placeholder="Email" required>
            <button type="submit">Send login link</button>
        </form>';
}

// Not logged in, show login link
elseif (!isset($_SESSION['user_id'])) {
    if (isset($_GET['link_sent'])) {
        $content = '<font color="green">A login link has been sent to your email, check your inbox and click the link to log in.</font>
            <br><br><a href="/">Continue</a>';
    } else {
        $content = '<a href="?login">Login</a>';
    }
}

// Add task form
elseif (isset($_GET['add']) && !isset($_GET['type'])) {
    $content = '<form method="GET">
        <input type="text" name="name" size="20" placeholder="Task description" required><br>
        <select name="type">
            <option value="static_interval">Repeat every # days/weeks/months</option>
            <option value="day_number">Repeat on every #th of the month</option>
            <option value="day_of_month">Repeat every #th ...day of the month</option>
        </select><br>
        <button type="submit">Next</button>';
}

// Static interval type
elseif (@$_GET['type'] === 'static_interval') {
    $content = '<form method="POST" action="action.php">
        <input type="hidden" name="type" value="static_interval">
        <input type="text" name="name" size="20" value="' . $_GET['name'] . '" required readonly><br>
        Repeat every <input type="number" name="interval" min="1" value="1" required> 
        <select name="recurrence">
            <option value="days">day(s)</option>
            <option value="weeks">week(s)</option>
            <option value="months">month(s)</option>
        </select><br>
        <input type="checkbox" name="repeat_after_completion" value="1" checked> Start new interval from last completion date (otherwise from last start date)<br>
        Needs to be completed within <input type="number" name="complete_within" min="1" value="1" required> day(s)<br>
        Starting from <input type="date" name="start_date" value="' . date('Y-m-d') . '" required><br>
        <button type="submit">Add Task</button>
        </form>';
}

// Tasks list
else {
    $content = '<a href="?add">Add</a> ';
    $content .= '<a href="action.php?logout">Logout</a><br>';

    // List tasks
    $stmt = $db->prepare("SELECT id, name, start_date, due_date,
        strftime('%J', start_date) - strftime('%J', date('now')) AS start_in,
        strftime('%J', due_date) - strftime('%J', date('now')) AS due_in
        FROM task
        WHERE user_id = :user_id
        ORDER BY due_in ASC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $content .= '<table border="0" cellpadding="5" cellspacing="0">
            <tr><th>Complete</th><th>Start</th><th>Time left</th><th>Name</th></tr>';
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Start
        if ($row['start_in'] <= 0) {
            $start = "Now";
        } else {
            $start = "In {$row['start_in']} day(s)";
        }
        // Time left
        if ($row['due_in'] < 0) {
            $time_left = abs($row['due_in']) . " day(s) overdue";
        } else {
            $time_left = $row['due_in'] . " day(s) left";
        }
        $content .= '<tr>
            <td><a href="action.php?complete=' . $row['id'] . '">&#9989;</a></td>
            <td>' . $start . '</td>
            <td>' . $time_left . '</td>
            <td>' . $row['name'] . '</td>
            </tr>';
    }
    $content .= '</table>';
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
    <p><?php echo $content; ?></p>
</body>

</html>