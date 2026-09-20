<?php

require '../init.php';

$menu = [];
$content = '';
$row = [];

// Menu item 'Back' on subscreens
if (isset($_GET['add']) || isset($_GET['task']) || isset($_GET['type'])) {
    $menu[] = '<a href="javascript:history.back()">Back</a>';
}

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
        $menu[] = '<a href="?login">Login</a>';
    }
}

// Show task
elseif (isset($_GET['task'])) {
    $stmt = $db->prepare("SELECT * FROM task WHERE id = :id AND user_id = :user_id");
    $stmt->bindValue(':id', $_GET['task'], SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $menu[] = sprintf('<a href="?edit=%s&type=%s">Edit</a>', $_GET['task'], $row['type']);
        $content = '<h2>Task: ' . htmlspecialchars($row['name']) . '</h2>';
        $content .= '<p>Type: ' . htmlspecialchars($row['type']) . '</p>';
        $content .= '<p>Start date: ' . htmlspecialchars($row['start_date']) . '</p>';
        $content .= '<p>Due date: ' . htmlspecialchars($row['due_date']) . '</p>';
    } else {
        $content = '<font color="red">Task not found.</font>';
    }
}

// Add task form
elseif (isset($_GET['add'])) {
    $content = '<form method="GET">
        <input type="text" name="name" size="20" placeholder="Task description" required><br>
        <select name="type">
            <option value="day_interval">Repeat with daily intervals</option>
            <option value="date_interval">Repeat in months on specific dates</option>
            <option value="week_interval" disabled>Repeat on specific weekdays</option>
            <option value="weekday_interval" disabled>Repeat every #th weekday of the month</option>
        </select><br>
        <button type="submit">Next</button>';
}

// Add/Edit day_interval
elseif (@$_GET['type'] === 'day_interval') {
    $button = 'Add Task';

    // Edit mode
    if (isset($_GET['edit'])) {
        $stmt = $db->prepare("SELECT * FROM task WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $_GET['edit'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) die('Task not found');
        $button = 'Update Task';
    }
    // Values/defaults
    $edit = $row['id'] ?? '';
    $name = $row['name'] ?? $_GET['name'] ?? '';
    $interval = $row['interval'] ?? 1;
    $recurrence = $row['recurrence'] ?? 'days';
    $completion_based = $row['completion_based'] ?? 1;
    $due_within = $row['due_within'] ?? 1;
    $start_date = $row['start_date'] ?? date('Y-m-d');

    $content = '<form method="POST" action="action.php">
        <input type="hidden" name="type" value="day_interval">
        <input type="hidden" name="edit" value="' . $edit . '">
        <input type="text" name="name" size="20" value="' . $name . '" required><br>
        Repeat every <input type="number" name="interval" min="1" value="' . $interval . '" required> 
        <select name="recurrence">
            <option value="days"' . ($recurrence === 'days' ? ' selected' : '') . '>day(s)</option>
            <option value="weeks"' . ($recurrence === 'weeks' ? ' selected' : '') . '>week(s)</option>
            <option value="months"' . ($recurrence === 'months' ? ' selected' : '') . '>month(s)</option>
        </select><br>
        <input type="checkbox" name="completion_based" value="1"' . ($completion_based ? ' checked' : '') . '> New start date based on completion date (otherwise on start date)<br>
        Due within <input type="number" name="due_within" min="1" value="' . $due_within . '" required> day(s)<br>
        Starting from <input type="date" name="start_date" value="' . $start_date . '" required><br>
        <button type="submit">' . $button . '</button>
        </form>';
}

// Add/Edit date_interval
elseif (@$_GET['type'] === 'date_interval') {
    $button = 'Add Task';

    // Edit mode
    if (isset($_GET['edit'])) {
        $stmt = $db->prepare("SELECT * FROM task WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $_GET['edit'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) die('Task not found');
        $button = 'Update Task';
    }
    // Values/defaults
    $edit = $row['id'] ?? '';
    $name = $row['name'] ?? $_GET['name'] ?? '';
    $start_date = $row['start_date'] ?? date('Y-m-d');
    $selection = explode(',', $row['selection'] ?? '1,2,3,4,5,6,7,8,9,10,11,12');
    $due_within = $row['due_within'] ?? 1;

    $content = '<form method="POST" action="action.php">
        <input type="hidden" name="type" value="date_interval">
        <input type="hidden" name="edit" value="' . $edit . '">
        <input type="text" name="name" size="20" value="' . $name . '" required><br>
        Starting date <input type="date" name="start_date" value="' . $start_date . '" required><br>
        Repeat on months:<br>
        <select name="months[]" multiple size="12" required>
            <option value="1"' . (in_array('1', $selection) ? ' selected' : '') . '>January</option>
            <option value="2"' . (in_array('2', $selection) ? ' selected' : '') . '>February</option>
            <option value="3"' . (in_array('3', $selection) ? ' selected' : '') . '>March</option>
            <option value="4"' . (in_array('4', $selection) ? ' selected' : '') . '>April</option>
            <option value="5"' . (in_array('5', $selection) ? ' selected' : '') . '>May</option>
            <option value="6"' . (in_array('6', $selection) ? ' selected' : '') . '>June</option>
            <option value="7"' . (in_array('7', $selection) ? ' selected' : '') . '>July</option>
            <option value="8"' . (in_array('8', $selection) ? ' selected' : '') . '>August</option>
            <option value="9"' . (in_array('9', $selection) ? ' selected' : '') . '>September</option>
            <option value="10"' . (in_array('10', $selection) ? ' selected' : '') . '>October</option>
            <option value="11"' . (in_array('11', $selection) ? ' selected' : '') . '>November</option>
            <option value="12"' . (in_array('12', $selection) ? ' selected' : '') . '>December</option>
        </select><br>
        Due within <input type="number" name="due_within" min="1" value="' . $due_within . '" required> day(s)<br>
        <button type="submit">' . $button . '</button>
        </form>';
}

// Tasks list
else {
    $menu[] = '<a href="?add">Add</a>';
    // List tasks
    $stmt = $db->prepare("SELECT id, name, start_date, due_date,
        strftime('%J', start_date) - strftime('%J', date('now')) AS start_in,
        strftime('%J', due_date) - strftime('%J', date('now')) AS due_in
        FROM task
        WHERE user_id = :user_id
        ORDER BY due_in ASC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $content = '<table border="0" cellpadding="5" cellspacing="0">
            <tr><th>Complete</th><th>Name</th><th>Start</th><th>Time left</th></tr>';
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
            <td><a href="?task=' . $row['id'] . '">' . $row['name'] . '</a></td>
            <td>' . $start . '</td>
            <td>' . $time_left . '</td>
            </tr>';
    }
    $content .= '</table>';
}

// 'Delete' when editing a task
if (isset($_GET['edit'])) {
    $menu[] = sprintf(
        '<a href="action.php?delete=%d" onclick="return confirm(\'Delete task \\\'%s\\\'?\');">Delete</a>',
        $_GET['edit'],
        $row['name']
    );
}

// 'Logout' when logged in
if (isset($_SESSION['user_id'])) {
    $menu[] = '<a href="action.php?logout" onclick="return confirm(\'Logout?\');">Logout</a>';
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
    <script>
        async function updateAppBadge() {
            if (!('setAppBadge' in navigator)) {
                return;
            }

            try {
                const response = await fetch('badge.php', {cache: 'no-store'});
                if (!response.ok) {
                    if ('clearAppBadge' in navigator) {
                        await navigator.clearAppBadge();
                    }
                    return;
                }

                const data = await response.json();
                if (data.count > 0) {
                    await navigator.setAppBadge(data.count);
                } else if ('clearAppBadge' in navigator) {
                    await navigator.clearAppBadge();
                }
            } catch (error) {
                // Badge updates are best effort.
            }
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js', {scope: './'}).then(function() {
                    updateAppBadge();
                });
            });

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    updateAppBadge();
                    if (navigator.serviceWorker.controller) {
                        navigator.serviceWorker.controller.postMessage({type: 'update-badge'});
                    }
                }
            });
        }
    </script>
</head>

<body>
    <h1>ReToDo</h1>
    <p>Welcome to ReToDo! This is a simple web application for managing your recurring tasks.</p>
    <p><?php echo implode(' &nbsp; ', $menu); ?></p>
    <p><?php echo $content; ?></p>
</body>

</html>