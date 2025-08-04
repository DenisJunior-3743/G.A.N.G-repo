<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: /G.A.N.G/auth/login.html');
    exit;
}

$user_id     = $_SESSION['user_id'] ?? null;
$username    = $_SESSION['username'] ?? '';
$role        = $_SESSION['role'] ?? '';
$member_id   = $_SESSION['member_id'] ?? '';
$first_name  = $_SESSION['first_name'] ?? '';
$second_name = $_SESSION['second_name'] ?? '';
$third_name  = $_SESSION['third_name'] ?? '';
$email       = $_SESSION['email'] ?? '';
$gender      = $_SESSION['gender'] ?? '';
$user_picture = $_SESSION['picture'] ?? '';

$page = (int)($_GET['page'] ?? 1);
$per_page = 6;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$speaker_filter = trim($_GET['speaker'] ?? '');
$date_filter = trim($_GET['date'] ?? '');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.title LIKE ? OR s.speaker LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($speaker_filter)) {
    $where_conditions[] = "s.speaker = ?";
    $params[] = $speaker_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(s.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $count_query = "SELECT COUNT(*) FROM sermons s $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_sermons = $count_stmt->fetchColumn();
    $total_pages = ceil($total_sermons / $per_page);

    $sermons_query = "
        SELECT s.*, m.first_name, m.second_name, m.third_name, m.picture as member_picture, m.gender
        FROM sermons s
        LEFT JOIN members m ON s.member_id = m.id
        $where_clause
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $sermons_stmt = $pdo->prepare($sermons_query);

    foreach ($params as $i => $param) {
        $sermons_stmt->bindValue($i + 1, $param);
    }
    $sermons_stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
    $sermons_stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $sermons_stmt->execute();
    $sermons = $sermons_stmt->fetchAll();

    foreach ($sermons as &$sermon) {
        $sermon_id = $sermon['id'];

        $chunks_stmt = $pdo->prepare("SELECT filename, file_size, chunk_index FROM sermon_chunks WHERE sermon_id = ? ORDER BY chunk_index ASC");
        $chunks_stmt->execute([$sermon_id]);
        $sermon['audio_chunks'] = $chunks_stmt->fetchAll();

        $slides_stmt = $pdo->prepare("SELECT original_filename, saved_filename, file_size, file_type FROM sermon_slides WHERE sermon_id = ? ORDER BY uploaded_at ASC");
        $slides_stmt->execute([$sermon_id]);
        $sermon['slides'] = $slides_stmt->fetchAll();

        $sermon['total_audio_size'] = array_sum(array_column($sermon['audio_chunks'], 'file_size'));
        $sermon['total_slides_size'] = array_sum(array_column($sermon['slides'], 'file_size'));
        $sermon['chunk_count'] = count($sermon['audio_chunks']);
        $sermon['slide_count'] = count($sermon['slides']);
    }

    $speakers_stmt = $pdo->query("SELECT DISTINCT speaker FROM sermons WHERE speaker IS NOT NULL AND speaker != '' ORDER BY speaker");
    $speakers = $speakers_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Database error in view_sermons.php: " . $e->getMessage());
    $sermons = [];
    $total_pages = 0;
    $speakers = [];
    $error_message = "Database error occurred. Please try again.";
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function formatDuration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
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
    <title>Sermon Library | G.A.N.G Admin</title>
    
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

        .sermon-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
        }

        .sermon-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }

        .sermon-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }

        .sermon-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .sermon-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .sermon-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .sermon-body {
            padding: 25px;
        }

        .preacher-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
        }

        .preacher-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .preacher-info h6 {
            margin: 0;
            font-weight: bold;
            color: var(--text-primary);
        }

        .preacher-info .text-muted {
            font-size: 0.85rem;
        }

        .content-section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .audio-list, .slides-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .audio-item, .slide-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: between;
            align-items: center;
            transition: background-color 0.2s;
        }

        .audio-item:hover, .slide-item:hover {
            background-color: #e3f2fd;
        }

        .audio-item:last-child, .slide-item:last-child {
            border-bottom: none;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .file-size {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .download-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn-download {
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .no-sermons {
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .sermon-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .preacher-profile {
                flex-direction: column;
                text-align: center;
            }
            
            .download-actions {
                flex-direction: column;
            }
            
            .stats-row {
                flex-wrap: wrap;
                gap: 15px;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sermon-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .sermon-card:nth-child(2) { animation-delay: 0.1s; }
        .sermon-card:nth-child(3) { animation-delay: 0.2s; }
        .sermon-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>

<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="bi bi-collection-play me-3"></i>Sermon Library</h1>
                    <p class="mb-0">Browse and download recorded sermons with audio chunks and presentation slides</p>
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
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-search me-2"></i>Search Sermons</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by title or speaker..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-person me-2"></i>Speaker</label>
                        <select name="speaker" class="form-select">
                            <option value="">All Speakers</option>
                            <?php foreach ($speakers as $speaker): ?>
                                <option value="<?php echo htmlspecialchars($speaker); ?>" 
                                        <?php echo ($speaker_filter === $speaker) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($speaker); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-calendar me-2"></i>Date</label>
                        <input type="date" name="date" class="form-control" 
                               value="<?php echo htmlspecialchars($date_filter); ?>">
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
                
                <?php if ($search || $speaker_filter || $date_filter): ?>
                    <div class="mt-3">
                        <a href="?" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle me-2"></i>Clear Filters
                        </a>
                        <span class="ms-3 text-muted">
                            Found <?php echo $total_sermons; ?> sermon(s)
                        </span>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Sermons List -->
        <?php if (empty($sermons)): ?>
            <div class="no-sermons">
                <i class="bi bi-collection display-1 text-muted mb-4"></i>
                <h3>No Sermons Found</h3>
                <p class="text-muted">
                    <?php if ($search || $speaker_filter || $date_filter): ?>
                        No sermons match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        There are no recorded sermons available at the moment.
                    <?php endif; ?>
                </p>
                <div class="mt-4">
                    <a href="/G.A.N.G/admin/sermon/record-sermon.php" class="btn btn-primary btn-lg me-3">
                        <i class="bi bi-mic-fill me-2"></i>Record New Sermon
                    </a>
                    <?php if ($search || $speaker_filter || $date_filter): ?>
                        <a href="?" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-clockwise me-2"></i>Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Sermons Grid -->
            <div class="row">
                <?php foreach ($sermons as $sermon): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="sermon-card" data-sermon-id="<?php echo $sermon['id']; ?>">
                            <!-- Sermon Header -->
                            <div class="sermon-header">
                                <div class="sermon-title"><?php echo htmlspecialchars($sermon['title']); ?></div>
                                <div class="sermon-meta">
                                    <span><i class="bi bi-calendar3 me-1"></i><?php echo date('M j, Y', strtotime($sermon['created_at'])); ?></span>
                                    <span><i class="bi bi-clock me-1"></i><?php echo formatDuration($sermon['total_duration_seconds'] ?? 0); ?></span>
                                    <span><i class="bi bi-hdd me-1"></i><?php echo formatFileSize($sermon['total_size_bytes'] ?? 0); ?></span>
                                </div>
                            </div>

                            <!-- Sermon Body -->
                            <div class="sermon-body">
                                <!-- Preacher Profile -->
                                <div class="preacher-profile">
                                    <img src="<?php echo getPreacherAvatar($sermon['member_picture'], $sermon['gender'], $sermon['first_name']); ?>" 
                                         alt="Preacher Avatar" class="preacher-avatar">
                                    <div class="preacher-info">
                                        <h6><?php echo htmlspecialchars(trim($sermon['first_name'] . ' ' . $sermon['second_name'])); ?></h6>
                                        <div class="text-muted">
                                            Speaker: <?php echo htmlspecialchars($sermon['speaker'] ?? 'Unknown'); ?>
                                        </div>
                                        <div class="text-muted">
                                            Status: <span class="badge bg-<?php echo $sermon['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($sermon['status'] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistics -->
                                <div class="stats-row">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $sermon['chunk_count']; ?></div>
                                        <div class="stat-label">Audio Files</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $sermon['slide_count']; ?></div>
                                        <div class="stat-label">Slides</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo formatFileSize($sermon['total_audio_size'] + $sermon['total_slides_size']); ?></div>
                                        <div class="stat-label">Total Size</div>
                                    </div>
                                </div>

                                <!-- Audio Files Section -->
                                <?php if (!empty($sermon['audio_chunks'])): ?>
                                    <div class="content-section">
                                        <div class="section-title">
                                            <i class="bi bi-music-note-beamed text-success"></i>
                                            Audio Files (<?php echo count($sermon['audio_chunks']); ?>)
                                        </div>
                                        <div class="audio-list">
                                            <?php foreach ($sermon['audio_chunks'] as $index => $chunk): ?>
                                                <div class="audio-item">
                                                    <div class="file-info">
                                                        <div class="file-name">
                                                            <i class="bi bi-file-earmark-music me-2"></i>
                                                            Chunk <?php echo $index + 1; ?> - <?php echo htmlspecialchars($chunk['filename']); ?>
                                                        </div>
                                                        <div class="file-size"><?php echo formatFileSize($chunk['file_size']); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Slides Section -->
                                <?php if (!empty($sermon['slides'])): ?>
                                    <div class="content-section">
                                        <div class="section-title">
                                            <i class="bi bi-file-earmark-slides text-info"></i>
                                            Presentation Slides (<?php echo count($sermon['slides']); ?>)
                                        </div>
                                        <div class="slides-list">
                                            <?php foreach ($sermon['slides'] as $slide): ?>
                                                <div class="slide-item">
                                                    <div class="file-info">
                                                        <div class="file-name">
                                                            <i class="bi bi-file-earmark-<?php echo strpos($slide['file_type'], 'pdf') !== false ? 'pdf' : 'image'; ?> me-2"></i>
                                                            <?php echo htmlspecialchars($slide['original_filename']); ?>
                                                        </div>
                                                        <div class="file-size"><?php echo formatFileSize($slide['file_size']); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Download Actions -->
                                <div class="download-actions">
                                    <?php if (!empty($sermon['audio_chunks'])): ?>
                                        <a href="/G.A.N.G/admin/sermon/download.php?type=audio&sermon_id=<?php echo $sermon['id']; ?>" 
                                           class="btn btn-success btn-download">
                                            <i class="bi bi-download me-2"></i>Download Audio
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sermon['slides'])): ?>
                                        <a href="/G.A.N.G/admin/sermon/download.php?type=slides&sermon_id=<?php echo $sermon['id']; ?>" 
                                           class="btn btn-info btn-download">
                                            <i class="bi bi-download me-2"></i>Download Slides
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sermon['audio_chunks']) && !empty($sermon['slides'])): ?>
                                        <a href="/G.A.N.G/admin/sermon/download.php?type=complete&sermon_id=<?php echo $sermon['id']; ?>" 
                                           class="btn btn-primary btn-download">
                                            <i class="bi bi-download me-2"></i>Download Complete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Sermon pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $speaker_filter ? '&speaker=' . urlencode($speaker_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $speaker_filter ? '&speaker=' . urlencode($speaker_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $speaker_filter ? '&speaker=' . urlencode($speaker_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $speaker_filter ? '&speaker=' . urlencode($speaker_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $speaker_filter ? '&speaker=' . urlencode($speaker_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Footer Actions -->
        <div class="text-center mt-5 mb-4">
            <a href="/G.A.N.G/admin/sermon/record-sermon.php" class="btn btn-primary btn-lg me-3">
                <i class="bi bi-mic-fill me-2"></i>Record New Sermon
            </a>
            <a href="/G.A.N.G/admin/dashboard.php" class="btn btn-outline-secondary btn-lg">
                <i class="bi bi-house me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Enhanced functionality for sermon display page
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for audio and slide lists
            const scrollableElements = document.querySelectorAll('.audio-list, .slides-list');
            scrollableElements.forEach(element => {
                element.addEventListener('scroll', function() {
                    this.classList.add('scrolling');
                    clearTimeout(this.scrollTimer);
                    this.scrollTimer = setTimeout(() => {
                        this.classList.remove('scrolling');
                    }, 150);
                });
            });

            // Download progress indication
            const downloadButtons = document.querySelectorAll('.btn-download');
            downloadButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const originalText = this.innerHTML;
                    
                    // Show loading state
                    this.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Preparing Download...';
                    this.disabled = true;
                    
                    // Reset after 3 seconds (download should start by then)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 3000);
                });
            });

            // Search input enhancement
            let searchTimeout;
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 3 || this.value.length === 0) {
                            // Auto-submit after 1 second of no typing (optional)
                            // this.closest('form').submit();
                        }
                    }, 1000);
                });
            }

            // Sermon card hover effects
            const sermonCards = document.querySelectorAll('.sermon-card');
            sermonCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const avatar = this.querySelector('.preacher-avatar');
                    if (avatar) {
                        avatar.style.transform = 'scale(1.1)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    const avatar = this.querySelector('.preacher-avatar');
                    if (avatar) {
                        avatar.style.transform = 'scale(1)';
                    }
                });
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput) {
                        searchInput.focus();
                    }
                }
                
                // ESC to clear search
                if (e.key === 'Escape') {
                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.focus();
                    }
                }
            });

            // Touch gestures for mobile
            let startX, startY, endX, endY;
            
            sermonCards.forEach(card => {
                card.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                });

                card.addEventListener('touchend', function(e) {
                    endX = e.changedTouches[0].clientX;
                    endY = e.changedTouches[0].clientY;
                    
                    const deltaX = endX - startX;
                    const deltaY = endY - startY;
                    
                    // Swipe left to download audio
                    if (deltaX < -100 && Math.abs(deltaY) < 50) {
                        const audioBtn = this.querySelector('.btn-download');
                        if (audioBtn) {
                            audioBtn.click();
                        }
                    }
                    
                    // Swipe right to download complete
                    if (deltaX > 100 && Math.abs(deltaY) < 50) {
                        const completeBtn = this.querySelector('.btn-primary.btn-download');
                        if (completeBtn) {
                            completeBtn.click();
                        }
                    }
                });
            });

            // Statistics animation on scroll
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px'
            };

            const statObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const statNumbers = entry.target.querySelectorAll('.stat-number');
                        statNumbers.forEach((statNumber, index) => {
                            setTimeout(() => {
                                statNumber.style.transform = 'scale(1.2)';
                                statNumber.style.color = '#667eea';
                                setTimeout(() => {
                                    statNumber.style.transform = 'scale(1)';
                                }, 200);
                            }, index * 100);
                        });
                        statObserver.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.stats-row').forEach(function(row) {
                statObserver.observe(row);
            });

            // Card fade-in animation observer
            const cardObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        cardObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            // Apply initial styles and observe cards
            sermonCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                card.style.transitionDelay = `${index * 0.1}s`;
                cardObserver.observe(card);
            });
        });

        // Utility functions
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Error handling for downloads
        window.addEventListener('error', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('btn-download')) {
                showNotification('Download failed. Please try again.', 'danger');
            }
        });

        // Print sermon functionality
        function printSermon(sermonId) {
            const sermonCard = document.querySelector(`[data-sermon-id="${sermonId}"]`);
            if (!sermonCard) return;
            
            const printWindow = window.open('', '_blank');
            const sermonTitle = sermonCard.querySelector('.sermon-title').textContent;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Sermon: ${sermonTitle}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                        .sermon-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #333; }
                        .section { margin-bottom: 20px; }
                        .section-title { font-size: 18px; font-weight: bold; color: #667eea; margin-bottom: 10px; }
                        .file-list { margin-left: 20px; }
                        .file-item { margin-bottom: 5px; }
                        .preacher-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
                        @media print {
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="sermon-title">${sermonTitle}</div>
                    ${sermonCard.innerHTML.replace(/<button[^>]*>.*?<\/button>/g, '').replace(/<a[^>]*class="btn[^"]*"[^>]*>.*?<\/a>/g, '')}
                    <div style="margin-top: 30px; font-size: 12px; color: #666;">
                        Printed on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }
    </script>
</body>
</html>