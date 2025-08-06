<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $uploadDir = __DIR__ . '/../profile_pics/';
        $picturePath = null;

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['picture']['tmp_name'];
            $fileName = uniqid('profile_', true);
            $fileExt = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            // Validate extension
            if (!in_array($fileExt, $allowedExts)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid image format. Only JPG, PNG, GIF allowed.']);
                exit;
            }

            // Validate size
            if ($_FILES['picture']['size'] > $maxSize) {
                echo json_encode(['status' => 'error', 'message' => 'Image size must be less than 2MB.']);
                exit;
            }

            $newFileName = $fileName . '.' . $fileExt;
            $destPath = $uploadDir . $newFileName;

            if (!move_uploaded_file($fileTmpPath, $destPath)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded image.']);
                exit;
            }

            $picturePath = '/G.A.N.G/registration/profile_pics/' . $newFileName;
        }

        // Prepare and bind
        $stmt = $pdo->prepare("
            INSERT INTO members (
                first_name, second_name, third_name, gender, dob, phone, email, gift, year_joined, address, occupation, picture
            ) VALUES (
                :first_name, :second_name, :third_name, :gender, :dob, :phone, :email, :gift, :year_joined, :address, :occupation, :picture
            )
        ");

        $stmt->execute([
            ':first_name'   => $_POST['first_name'],
            ':second_name'  => $_POST['second_name'],
            ':third_name'   => $_POST['third_name'] ?? null,
            ':gender'       => $_POST['gender'],
            ':dob'          => $_POST['dob'],
            ':phone'        => $_POST['phone'],
            ':email'        => $_POST['email'],
            ':gift'         => $_POST['gift'] ?? null,
            ':year_joined'  => $_POST['year_joined'],
            ':address'      => $_POST['address'],
            ':occupation'   => $_POST['occupation'] ?? null,
            ':picture'      => $picturePath
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Registration successful.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
