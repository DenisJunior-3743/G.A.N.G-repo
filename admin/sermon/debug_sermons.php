<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Debug: Check if session variables exist
echo "<!-- Debug Info -->\n";
echo "<!-- Session member_id: " . ($_SESSION['member_id'] ?? 'NOT SET') . " -->\n";
echo "<!-- Session first_name: " . ($_SESSION['first_name'] ?? 'NOT SET') . " -->\n";

// Check authentication - simplified for debugging
if (!isset($_SESSION['member_id'])) {
    die("Authentication Error: Please log in. Session member_id not found.");
}

// Database connection - add error handling
try {
    require_once dirname(__DIR__, 2) . '/config/database.php';
    echo "<!-- Database connection successful -->\n";
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get basic sermon count first
try {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM sermons");
    $total_sermons = $count_stmt->fetchColumn();
    echo "<!-- Total sermons in database: $total_sermons -->\n";
} catch (Exception $e) {
    die("Database query failed: " . $e->getMessage());
}

// Get user session data with defaults
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Unknown User';
$role = $_SESSION['role'] ?? 'Member';
$member_id = $_SESSION['member_id'] ?? '';
$first_name = $_SESSION['first_name'] ?? 'Unknown';
$second_name = $_SESSION['second_name'] ?? '';
$third_name = $_SESSION['third_name'] ?? '';
$email = $_SESSION['email'] ?? '';
$gender = $_SESSION['gender'] ?? 'Male';
$user_picture = $_SESSION['picture'] ?? '';

// Simple pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Search parameters
$search = trim($_GET['search'] ?? '');
$speaker_filter = trim($_GET['speaker'] ?? '');
$date_filter = trim($_GET['date'] ?? '');

// Build query
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
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM sermons s $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $filtered_total = $count_stmt->fetchColumn();
    $total_pages = ceil($filtered_total / $per_page);
    
    echo "<!-- Filtered total: $filtered_total -->\n";

    // Main sermons query
    $sermons_query = "
        SELECT s.*, 
               m.first_name, m.second_name, m.third_name, 
               m.picture as member_picture, m.gender
        FROM sermons s
        LEFT JOIN members m ON s.member_id = m.id
        $where_clause
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $query_params = array_merge($params, [$per_page, $offset]);
    $sermons_stmt = $pdo->prepare($sermons_query);
    $sermons_stmt->execute($query_params);
    $sermons = $sermons_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<!-- Found " . count($sermons) . " sermons for current page -->\n";

    // Get detailed data for each sermon
    foreach ($sermons as &$sermon) {
        $sermon_id = $sermon['id'];
        
        // Get audio chunks
        $chunks_stmt = $pdo->prepare("
            SELECT filename, file_size, chunk_index 
            FROM sermon_chunks 
            WHERE sermon_id = ? 
            ORDER BY chunk_index ASC
        ");
        $chunks_stmt->execute([$sermon_id]);
        $sermon['audio_chunks'] = $chunks_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get slides
        $slides_stmt = $pdo->prepare("
            SELECT original_filename, saved_filename, file_size, file_type 
            FROM sermon_slides 
            WHERE sermon_id = ? 
            ORDER BY uploaded_at ASC
        ");
        $slides_stmt->execute([$sermon_id]);
        $sermon['slides'] = $slides_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $sermon['total_audio_size'] = array_sum(array_column($sermon['audio_chunks'], 'file_size'));
        $sermon['total_slides_size'] = array_sum(array_column($sermon['slides'], 'file_size'));
        $sermon['chunk_count'] = count($sermon['audio_chunks']);
        $sermon['slide_count'] = count($sermon['slides']);
        
        echo "<!-- Sermon ID {$sermon_id}: {$sermon['chunk_count']} chunks, {$sermon['slide_count']} slides -->\n";
    }

    // Get unique speakers for filter
    $speakers_stmt = $pdo->query("SELECT DISTINCT speaker FROM sermons WHERE speaker IS NOT NULL AND speaker != '' ORDER BY speaker");
    $speakers = $speakers_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper functions
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
    if (!empty($picture) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/G.A.N.G/' . $picture)) {
        return '/G.A.N.G/' . $picture;
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

        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 0.9rem;
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
            justify-content: space-between;
            align-items: center;
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
    </style>
</head>

<body>
    <!-- Debug Information -->
    <div class="container mt-3">
        <div class="debug-info">
            <strong>Debug Information:</strong><br>
            Total Sermons in Database: <?php echo $total_sermons; ?><br>
            Filtered Results: <?php echo $filtered_total ?? 0; ?><br>
            Current Page: <?php echo $page; ?><br>
            User: <?php echo htmlspecialchars($first_name . ' ' . $second_name); ?><br>
            Member ID: <?php echo htmlspecialchars($member_id); ?><br>
            Search: <?php echo htmlspecialchars($search); ?><br>
            Speaker Filter: <?php echo htmlspecialchars($speaker_filter); ?><br>
            Date Filter: <?php echo htmlspecialchars($date_filter); ?><br>
        </div>
    </div>

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
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>