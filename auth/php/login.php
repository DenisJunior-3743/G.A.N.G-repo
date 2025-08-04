<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    try {
        // Fetch the user by username only
        $stmt = $pdo->prepare("SELECT 
                                    u.id, 
                                    u.username, 
                                    u.password, 
                                    u.role_id, 
                                    u.member_id,
                                    r.role_name, 
                                    m.first_name, 
                                    m.second_name, 
                                    m.third_name, 
                                    m.email, 
                                    m.gender,
                                    m.picture
                               FROM users u
                               JOIN roles r ON u.role_id = r.id
                               LEFT JOIN members m ON u.member_id = m.id
                               WHERE u.username = :username");

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Password correct: Start session
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role']        = $user['role_name'];
            $_SESSION['member_id']   = $user['member_id'];
            $_SESSION['first_name']  = $user['first_name'];
            $_SESSION['second_name'] = $user['second_name'];
            $_SESSION['third_name']  = $user['third_name'];
            $_SESSION['email']       = $user['email'];
            $_SESSION['gender']      = $user['gender'];
            $_SESSION['picture']     = $user['picture'];

            // Log session data
            file_put_contents(__DIR__ . '/login_debug.json', json_encode($_SESSION, JSON_PRETTY_PRINT));

            // Redirection
            switch ($user['role_name']) {
                case 'admin':
                case 'mobilizer':
                    $redirect = '/G.A.N.G/discussion/discussion.php';
                    break;
                case 'member':
                    $redirect = '../../sermon.html';
                    break;
                default:
                    $redirect = '../../index.html';
            }

            echo json_encode(['success' => true, 'redirect' => $redirect]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}
