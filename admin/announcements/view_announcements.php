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

$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$priority_filter = trim($_GET['priority'] ?? '');
$status_filter = trim($_GET['status'] ?? 'active');

$where_conditions = ["a.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(a.topic LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($priority_filter)) {
    $where_conditions[] = "a.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

try {
    // Get total count
    $count_query = "SELECT COUNT(*) FROM announcements a $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_announcements = $count_stmt->fetchColumn();
    $total_pages = ceil($total_announcements / $per_page);

    // Get announcements
    $announcements_query = "
        SELECT a.*, m.first_name, m.second_name, m.third_name, m.picture as member_picture, m.gender
        FROM announcements a
        LEFT JOIN members m ON a.member_id = m.id
        $where_clause
        ORDER BY 
            CASE a.priority 
                WHEN 'high' THEN 1 
                WHEN 'normal' THEN 2 
                WHEN 'low' THEN 3 
            END,
            a.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $announcements_stmt = $pdo->prepare($announcements_query);
    
    foreach ($params as $i => $param) {
        $announcements_stmt->bindValue($i + 1, $param);
    }
    $announcements_stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
    $announcements_stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $announcements_stmt->execute();
    $announcements = $announcements_stmt->fetchAll();

    // Clean up expired announcements
    $cleanup_stmt = $pdo->prepare("UPDATE announcements SET status = 'expired' WHERE expiry_date < NOW() AND status = 'active'");
    $cleanup_stmt->execute();

} catch (PDOException $e) {
    error_log("Database error in view_announcements.php: " . $e->getMessage());
    $announcements = [];
    $total_pages = 0;
    $error_message = "Database error occurred. Please try again.";
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

function getPriorityBadge($priority) {
    $classes = [
        'high' => 'badge bg-danger',
        'normal' => 'badge bg-primary',
        'low' => 'badge bg-secondary'
    ];
    $labels = [
        'high' => 'High Priority',
        'normal' => 'Normal Priority',
        'low' => 'Low Priority'
    ];
    
    $class = $classes[$priority] ?? $classes['normal'];
    $label = $labels[$priority] ?? $labels['normal'];
    
    return "<span class='$class'>$label</span>";
}

function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return "Just now";
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else {
        $days = floor($time / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | G.A.N.G</title>
    
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
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .search-filters {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .announcement-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            position: relative;
        }

        .announcement-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .announcement-card.high-priority {
            border-left: 5px solid #dc3545;
        }

        .announcement-card.normal-priority {
            border-left: 5px solid #007bff;
        }

        .announcement-card.low-priority {
            border-left: 5px solid #6c757d;
        }

        .announcement-header {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .announcement-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .announcement-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .announcement-body {
            padding: 25px;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
        }

        .author-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .author-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .author-details .text-muted {
            font-size: 0.8rem;
        }

        .announcement-content {
            line-height: 1.6;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .event-date {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 15px;
        }

        .no-announcements {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .pagination {
            justify-content: center;
            margin-top: 40px;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 4px;
            border: none;
            color: #667eea;
        }

        .pagination .page-link:hover,
        .pagination .page-item.active .page-link {
            background: var(--primary-gradient);
            color: white;
        }

        .badge {
            font-size: 0.75rem;
            padding: 5px 10px;
        }

        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 20px;
            margin-bottom: 30px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .announcement-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .author-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="bi bi-megaphone me-3"></i>Announcements</h1>
                    <p class="mb-0">Stay updated with the latest community announcements</p>
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
        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-search me-2"></i>Search Announcements</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by topic or description..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-flag me-2"></i>Priority</label>
                        <select name="priority" class="form-select">
                            <option value="">All Priorities</option>
                            <option value="high" <?php echo ($priority_filter === 'high') ? 'selected' : ''; ?>>High Priority</option>
                            <option value="normal" <?php echo ($priority_filter === 'normal') ? 'selected' : ''; ?>>Normal Priority</option>
                            <option value="low" <?php echo ($priority_filter === 'low') ? 'selected' : ''; ?>>Low Priority</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if ($search || $priority_filter): ?>
                    <div class="mt-3">
                        <a href="?" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle me-2"></i>Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Admin/Mobilizer Actions -->
        <?php if ($role === 'Admin' || $role === 'admin' || $role === 'Mobilizer' || $role === 'mobilizer'): ?>
            <div class="mb-4">
                <a href="/G.A.N.G/admin/announcements/create_announcement.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-2"></i>Create New Announcement
                </a>
            </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($announcements)): ?>
            <div class="no-announcements">
                <i class="bi bi-megaphone" style="font-size: 4rem; color: #ccc; margin-bottom: 20px;"></i>
                <h3>No Announcements Found</h3>
                <p class="text-muted">
                    <?php if ($search || $priority_filter): ?>
                        No announcements match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        No active announcements at the moment. Check back later for updates.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-card <?php echo $announcement['priority']; ?>-priority">
                    <div class="announcement-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="announcement-title"><?php echo htmlspecialchars($announcement['topic']); ?></div>
                                <div class="announcement-meta">
                                    <span><i class="bi bi-calendar-event me-1"></i>Event: <?php echo date('M j, Y', strtotime($announcement['event_date'])); ?></span>
                                    <span><i class="bi bi-clock me-1"></i>Posted: <?php echo formatTimeAgo($announcement['created_at']); ?></span>
                                    <span><?php echo getPriorityBadge($announcement['priority']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="announcement-body">
                        <!-- Author Info -->
                        <div class="author-info">
                            <img src="<?php echo getPreacherAvatar($announcement['member_picture'], $announcement['gender'], $announcement['first_name']); ?>" 
                                 alt="Author Avatar" class="author-avatar">
                            <div class="author-details">
                                <h6><?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['second_name'] . ' ' . $announcement['third_name']); ?></h6>
                                <div class="text-muted">Posted by Admin</div>
                            </div>
                        </div>

                        <!-- Announcement Content -->
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['description'])); ?>
                        </div>

                        <!-- Event Date Badge -->
                        <div class="event-date">
                            <i class="bi bi-calendar-check me-1"></i>
                            Event Date: <?php echo date('F j, Y', strtotime($announcement['event_date'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Announcements pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 