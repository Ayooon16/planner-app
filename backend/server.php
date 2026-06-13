<?php
session_start();
require_once 'db_config.php';

$conn = getMysqliConnection();
if ($conn->connect_error) {
    apiResponse(['error' => 'Database connection failed'], 500);
}

function apiResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function getInput() {
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $input = json_decode($json, true) ?: [];
        }
        if (empty($input)) {
            $input = $_POST;
        }
    }
    return $input;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'register') {
    $name = $input['username'] ?? '';
    $pass = $input['password'] ?? '';
    
    if (empty($name) || empty($pass)) {
        apiResponse(['error' => 'Username and password required'], 400);
    }
    
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("SELECT id FROM users WHERE name=?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        apiResponse(['error' => 'User already exists'], 400);
    }
    
    $stmt = $conn->prepare("INSERT INTO users (name, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $hash);
    $stmt->execute();
    
    apiResponse(['success' => true, 'message' => 'User created successfully']);
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'login') {
    $name = $input['username'] ?? '';
    $pass = $input['password'] ?? '';
    
    if (empty($name) || empty($pass)) {
        apiResponse(['error' => 'Username and password required'], 400);
    }
    
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE name=?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        apiResponse(['error' => 'Wrong credentials'], 401);
    }
    
    $row = $result->fetch_assoc();
    if (!password_verify($pass, $row['password'])) {
        apiResponse(['error' => 'Wrong credentials'], 401);
    }
    
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $name;
    
    apiResponse([
        'success' => true, 
        'user_id' => $row['id'], 
        'username' => $name,
        'message' => 'Login successful'
    ]);
}

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getTasks') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $id = $_SESSION['user_id'];
    $showOld = isset($_GET['showOld']) ? $_GET['showOld'] === 'true' : true;
    $todayDate = date("Y-m-d");
    
    if ($showOld) {
        $result = $conn->query("SELECT * FROM tasks WHERE user = $id AND start_date >= '$todayDate' ORDER BY start_date DESC");
    } else {
        $result = $conn->query("SELECT * FROM tasks WHERE user = $id ORDER BY start_date DESC");
    }
    
    if (!$result) {
        apiResponse(['error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
    
    $result = $conn->query("SELECT * FROM repeatable WHERE user = $id ORDER BY start_date DESC");
    $repeatable = $result->fetch_all(MYSQLI_ASSOC);
    
    apiResponse(['success' => true, 'tasks' => $tasks, 'repeatable' => $repeatable]);
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'addTask') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $name = $input['taskName'] ?? '';
    $desc = $input['taskDescription'] ?? '';
    $date = $input['taskDate'] ?? '';
    $private = isset($input['taskPrivate']) ? (int)$input['taskPrivate'] : 0;
    $date = str_replace('T', ' ', $date);
    
    if (empty($name) || empty($date)) {
        apiResponse(['error' => 'Task name and date required'], 400);
    }
    
    if (!empty($input['taskRepeatable'])) {
        if (!empty($input['repeatType']) && $input['repeatType'] === "daily") {
            $weekday = null;
            $daily = 1;
        } else {
            $dt = new DateTime($date);
            $weekday = (int)$dt->format('N');
            $daily = 0;
        }
        $active = 1;
        
        $stmt = $conn->prepare("INSERT INTO repeatable (name, description, start_date, weekday, daily, active, user, private) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiiiii", $name, $desc, $date, $weekday, $daily, $active, $_SESSION['user_id'], $private);
        $stmt->execute();
        
        apiResponse(['success' => true, 'message' => 'Recurring task added', 'repeatable_id' => $conn->insert_id]);
    } else {
        $status = "A";
        $stmt = $conn->prepare("INSERT INTO tasks (name, description, start_date, status, user, private) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $name, $desc, $date, $status, $_SESSION['user_id'], $private);
        $stmt->execute();
        
        apiResponse(['success' => true, 'message' => 'Task added', 'task_id' => $conn->insert_id]);
    }
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'editTask') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $taskId = $input['taskId'] ?? 0;
    $name = $input['taskName'] ?? '';
    $description = $input['taskDescription'] ?? '';
    $date = $input['taskDate'] ?? '';
    $status = $input['taskStatus'] ?? 'A';
    $endDate = !empty($input['editTaskendDate']) ? $input['editTaskendDate'] : null;
    $private = isset($input['taskPrivate']) ? 1 : 0;
    
    if (empty($taskId) || empty($name)) {
        apiResponse(['error' => 'Task ID and name required'], 400);
    }
    
    $stmt = $conn->prepare("UPDATE tasks SET name=?, description=?, start_date=?, end_date=?, status=?, private=? WHERE id=?");
    $stmt->bind_param("sssssii", $name, $description, $date, $endDate, $status, $private, $taskId);
    $stmt->execute();
    
    apiResponse(['success' => true, 'message' => 'Task updated']);
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'changeStatus') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $taskId = $input['taskId'] ?? 0;
    $status = $input['taskStatus'] ?? '';
    $endDate = ($status == "F") ? date("Y-m-d H:i:s") : null;
    
    if (empty($taskId) || empty($status)) {
        apiResponse(['error' => 'Task ID and status required'], 400);
    }
    
    $stmt = $conn->prepare("UPDATE tasks SET status=?, end_date=? WHERE id=?");
    $stmt->bind_param("ssi", $status, $endDate, $taskId);
    $stmt->execute();
    
    apiResponse(['success' => true, 'message' => 'Status updated']);
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'createAndChangeStatus') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $repeatableId = $input['repeatable_id'] ?? 0;
    $name = $input['taskName'] ?? '';
    $desc = $input['taskDescription'] ?? '';
    $date = $input['taskDate'] ?? '';
    $status = $input['taskStatus'] ?? 'I';
    $endDate = ($status == "F") ? date("Y-m-d H:i:s") : null;
    $private = $input['taskPrivate'] ?? 0;
    $user = $input['taskUser'] ?? $_SESSION['user_id'];
    
    if (empty($repeatableId) || empty($name)) {
        apiResponse(['error' => 'Repeatable ID and name required'], 400);
    }
    
    $stmt = $conn->prepare("INSERT INTO tasks (name, description, start_date, end_date, status, user, private, repeatable_id, occurrence_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssiiis", $name, $desc, $date, $endDate, $status, $user, $private, $repeatableId, $date);
    $stmt->execute();
    
    apiResponse(['success' => true, 'message' => 'Task created from recurring', 'task_id' => $conn->insert_id]);
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'editRecurrent') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $taskId = $input['taskId'] ?? 0;
    $name = $input['taskName'] ?? '';
    $description = $input['taskDescription'] ?? '';
    $date = $input['taskDate'] ?? '';
    $status = ($input['taskStatus'] == 'A') ? 1 : 0;
    $weekday = !empty($input['taskWeekday']) ? $input['taskWeekday'] : null;
    $daily = ($weekday == null) ? 1 : 0;
    $private = isset($input['taskPrivate']) ? 1 : 0;
    $taskUser = $input['taskUser'] ?? $_SESSION['user_id'];
    
    if (empty($taskId) || empty($name)) {
        apiResponse(['error' => 'Task ID and name required'], 400);
    }
    
    $stmt = $conn->prepare("UPDATE repeatable SET name=?, description=?, start_date=?, weekday=?, daily=?, active=?, user=?, private=? WHERE id=?");
    $stmt->bind_param("sssiiiiii", $name, $description, $date, $weekday, $daily, $status, $taskUser, $private, $taskId);
    $stmt->execute();
    
    apiResponse(['success' => true, 'message' => 'Recurring task updated']);
}

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getPublicTasks') {
    if (empty($_SESSION['user_id'])) {
        apiResponse(['error' => 'Unauthorized'], 401);
    }
    
    $id = $_SESSION['user_id'];
    $showOld = isset($_GET['showOld']) ? $_GET['showOld'] === 'true' : true;
    $todayDate = date("Y-m-d");
    
    if ($showOld) {
        $result = $conn->query("SELECT * FROM tasks WHERE user != $id AND private = 0 AND start_date >= '$todayDate' ORDER BY start_date DESC");
    } else {
        $result = $conn->query("SELECT * FROM tasks WHERE user != $id AND private = 0 ORDER BY start_date DESC");
    }
    
    $publicTasks = $result->fetch_all(MYSQLI_ASSOC);
    
    $usersResult = $conn->query("SELECT id, name FROM users");
    $users = [];
    while ($row = $usersResult->fetch_assoc()) {
        $users[$row['id']] = $row['name'];
    }
    
    apiResponse(['success' => true, 'tasks' => $publicTasks, 'users' => $users]);
}

if ($method === 'POST' && isset($input['type']) && $input['type'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    apiResponse(['success' => true, 'message' => 'Logged out']);
}

apiResponse(['error' => 'Endpoint not found'], 404);
?>