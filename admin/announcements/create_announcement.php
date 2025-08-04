<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['member_id'])) {
    header('Location: /G.A.N.G/auth/login.html');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$member_id = $_SESSION['member_id'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$second_name = $_SESSION['second_name'] ?? '';
$third_name = $_SESSION['third_name'] ?? '';
$email = $_SESSION['email'] ?? '';
$gender = $_SESSION['gender'] ?? '';
$user_picture = $_SESSION['picture'] ?? '';

// Check if user has admin or mobilizer privileges
if ($role !== 'Admin' && $role !== 'admin' && $role !== 'Mobilizer' && $role !== 'mobilizer') {
    header('Location: /G.A.N.G/admin/sermon/view_sermons.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_date = trim($_POST['event_date'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_days = (int)($_POST['duration_days'] ?? 30);
    $priority = trim($_POST['priority'] ?? 'normal');
    
    // Validation
    if (empty($event_date) || empty($topic) || empty($description)) {
        $error_message = "Please fill in all required fields.";
    } elseif ($duration_days < 1 || $duration_days > 365) {
        $error_message = "Duration must be between 1 and 365 days.";
    } else {
        try {
            // Calculate expiry date
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
            
            $stmt = $pdo->prepare("
                INSERT INTO announcements (
                    member_id, event_date, topic, description, priority, 
                    duration_days, expiry_date, created_at, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
            ");
            
            $stmt->execute([
                $member_id, $event_date, $topic, $description, 
                $priority, $duration_days, $expiry_date
            ]);
            
            $success_message = "Announcement created successfully!";
            
            // Clear form data after successful submission
            $_POST = array();
            
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

function getDefaultAvatar($gender, $name) {
    $initial = strtoupper(substr($name, 0, 1));
    $bg_color = $gender === 'Female' ? '#e91e63' : '#2196f3';
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='$bg_color'/%3E%3Ctext x='50' y='65' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3E$initial%3C/text%3E%3C/svg%3E";
}

function getPreacherAvatar($picture, $gender, $name) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/G.A.N.G/registration/profile_pics/' . basename($picture);
    if (!empty($picture) && file_exists($path)) {
        return '/G.A.N.G/registration/profile_pics/' . basename($picture);
    }
    return getDefaultAvatar($gender, $name);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Announcement | G.A.N.G Admin</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --hover-shadow: 0 15px 40px rgba(0,0,0,0.15);
            --border-radius: 15px;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .form-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-high {
            background: #ffebee;
            color: #c62828;
        }

        .priority-normal {
            background: #e3f2fd;
            color: #1565c0;
        }

        .priority-low {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .user-info {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
        }

        .form-floating {
            margin-bottom: 20px;
        }

        .form-floating > .form-control {
            height: 120px;
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
        }
    </style>
</head>

<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="bi bi-megaphone me-3"></i>Create Announcement</h1>
                    <p class="mb-0">Share important announcements with the community</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end gap-3">
                        <img src="<?php echo getPreacherAvatar($user_picture, $gender, $first_name); ?>" 
                             alt="User Avatar" class="rounded-circle" width="50" height="50">
                        <div class="text-end">
                            <div><strong><?php echo htmlspecialchars($first_name . ' ' . $second_name); ?></strong></div>
                            <small class="opacity-75"><?php echo htmlspecialchars($role); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- User Info -->
        <div class="user-info">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <img src="<?php echo getPreacherAvatar($user_picture, $gender, $first_name); ?>" 
                         alt="User Avatar" class="user-avatar">
                </div>
                <div class="col-md-10">
                    <h5 class="mb-1"><?php echo htmlspecialchars($first_name . ' ' . $second_name . ' ' . $third_name); ?></h5>
                    <p class="mb-1 text-muted">Creating announcement as: <strong><?php echo htmlspecialchars($role); ?></strong></p>
                    <small class="text-muted">Date: <?php echo date('F j, Y g:i A'); ?></small>
                </div>
            </div>
        </div>

        <!-- Announcement Form -->
        <div class="form-card">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="event_date" class="form-label">
                                <i class="bi bi-calendar-event me-2"></i>Event Date *
                            </label>
                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                   value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="priority" class="form-label">
                                <i class="bi bi-flag me-2"></i>Priority
                            </label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>
                                    Low Priority
                                </option>
                                <option value="normal" <?php echo ($_POST['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>
                                    Normal Priority
                                </option>
                                <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>
                                    High Priority
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="topic" class="form-label">
                        <i class="bi bi-chat-square-text me-2"></i>Topic/Title *
                    </label>
                    <input type="text" class="form-control" id="topic" name="topic" 
                           placeholder="Enter announcement topic or title..." 
                           value="<?php echo htmlspecialchars($_POST['topic'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">
                        <i class="bi bi-card-text me-2"></i>Description/Details *
                    </label>
                    <textarea class="form-control" id="description" name="description" rows="6" 
                              placeholder="Enter detailed description of the announcement..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="duration_days" class="form-label">
                        <i class="bi bi-clock me-2"></i>Duration (Days)
                    </label>
                    <select class="form-select" id="duration_days" name="duration_days">
                        <option value="7" <?php echo ($_POST['duration_days'] ?? '') === '7' ? 'selected' : ''; ?>>7 days</option>
                        <option value="14" <?php echo ($_POST['duration_days'] ?? '') === '14' ? 'selected' : ''; ?>>14 days</option>
                        <option value="30" <?php echo ($_POST['duration_days'] ?? '30') === '30' ? 'selected' : ''; ?>>30 days</option>
                        <option value="60" <?php echo ($_POST['duration_days'] ?? '') === '60' ? 'selected' : ''; ?>>60 days</option>
                        <option value="90" <?php echo ($_POST['duration_days'] ?? '') === '90' ? 'selected' : ''; ?>>90 days</option>
                    </select>
                    <small class="text-muted">Announcement will be automatically removed after this period</small>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>Create Announcement
                    </button>
                    <a href="/G.A.N.G/admin/announcements/view_announcements.php" class="btn btn-outline-secondary">
                        <i class="bi bi-eye me-2"></i>View All Announcements
                    </a>
                    <a href="/G.A.N.G/admin/sermon/view_sermons.php" class="btn btn-outline-info">
                        <i class="bi bi-arrow-left me-2"></i>Back to Sermons
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;
        
        // Auto-resize textarea
        const textarea = document.getElementById('description');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html> 