<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['logout'])) {
    session_start();
    $_SESSION = [];
    session_destroy();
    header('Location: /index.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['type']) && $input['type'] === 'logout') {
        session_start();
        $_SESSION = [];
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit;
    }
}

require_once 'server.php';
?>