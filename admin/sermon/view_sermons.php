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
        SELECT s.*, s.folder_name as sermon_folder_name, m.first_name, m.second_name, m.third_name, m.picture as member_picture, m.gender
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
    <link href="/G.A.N.G/includes/css/main.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/header.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/footer.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/layout.css" rel="stylesheet">
    <link href="/G.A.N.G/admin/css/view_sermons.css" rel="stylesheet">

</head>

<body>

<div id="header"></div>
<?php include dirname(__DIR__, 2) . '/includes/welcome_section.php'; ?>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-2"><i class="bi bi-collection-play me-3"></i>Sermon Library</h1>
                    <p class="mb-0">Browse and listen to recorded sermons with audio chunks and presentation slides</p>
                </div>
                <div class="col-md-3 text-center">
                    <a href="/G.A.N.G/admin/sermon/record-sermon.php" class="btn btn-light btn-lg">
                        <i class="bi bi-mic-fill me-2"></i>Record Sermon
                    </a>
                    <a href="/G.A.N.G/admin/announcements/view_announcements.php" class="btn btn-outline-light btn-lg ms-2">
                        <i class="bi bi-megaphone me-2"></i>Announcements
                    </a>
                </div>
                <div class="col-md-3 text-md-end">
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
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Sermons List -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($sermons)): ?>
            <div class="no-sermons">
                <i class="bi bi-collection-play" style="font-size: 4rem; color: #ccc; margin-bottom: 20px;"></i>
                <h3>No Sermons Found</h3>
                <p class="text-muted">
                    <?php if ($search || $speaker_filter || $date_filter): ?>
                        No sermons match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        No sermons have been uploaded yet. Check back later for new content.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($sermons as $sermon): ?>
                <div class="sermon-card">
                    <!-- Sermon Header -->
                    <div class="sermon-header">
                        <div class="sermon-title"><?php echo htmlspecialchars($sermon['title']); ?></div>
                        <div class="sermon-meta">
                            <span><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($sermon['speaker']); ?></span>
                            <span><i class="bi bi-calendar me-1"></i><?php echo date('M j, Y', strtotime($sermon['created_at'])); ?></span>
                            <span><i class="bi bi-clock me-1"></i><?php echo date('g:i A', strtotime($sermon['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="sermon-body">
                        <!-- Preacher Profile -->
                        <?php if (!empty($sermon['first_name'])): ?>
                            <div class="preacher-profile">
                                <img src="<?php echo getPreacherAvatar($sermon['member_picture'], $sermon['gender'], $sermon['first_name']); ?>" 
                                     alt="Preacher Avatar" class="preacher-avatar">
                                <div class="preacher-info">
                                    <h6><?php echo htmlspecialchars($sermon['first_name'] . ' ' . $sermon['second_name'] . ' ' . $sermon['third_name']); ?></h6>
                                    <div class="text-muted">Preacher</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics -->
                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $sermon['chunk_count']; ?></div>
                                <div class="stat-label">Audio Chunks</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $sermon['slide_count']; ?></div>
                                <div class="stat-label">Slides</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo formatFileSize($sermon['total_audio_size']); ?></div>
                                <div class="stat-label">Audio Size</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo formatFileSize($sermon['total_slides_size']); ?></div>
                                <div class="stat-label">Slides Size</div>
                            </div>
                        </div>
                        


                        <!-- Audio Chunks with Player -->
                        <?php if (!empty($sermon['audio_chunks'])): ?>
                            <div class="content-section">
                                <div class="section-title">
                                    <i class="bi bi-music-note-beamed text-success"></i>
                                    Audio Chunks (<?php echo count($sermon['audio_chunks']); ?>)
                                </div>
                                <div class="audio-list">
                                    <?php foreach ($sermon['audio_chunks'] as $index => $chunk): ?>
                                        <div class="audio-item">
                                            <div class="file-info">
                                                <div class="file-name">
                                                    <i class="bi bi-mic mic-icon"></i>
                                                    Chunk <?php echo $chunk['chunk_index'] + 1; ?>
                                                </div>
                                                <div class="file-size"><?php echo formatFileSize($chunk['file_size']); ?></div>
                                                
                                                <!-- Audio Player -->
                                                <div class="audio-player" data-sermon-id="<?php echo $sermon['id']; ?>" data-chunk-index="<?php echo $index; ?>">
                                                    <button class="play-pause-btn" onclick="toggleAudio(this)">
                                                        <i class="bi bi-play-fill"></i>
                                                    </button>
                                                    <div class="audio-waveform" onclick="seekAudio(this, event)">
                                                        <div class="audio-progress"></div>
                                                    </div>
                                                    <div class="audio-time">0:00</div>
                                                    <div class="audio-loading"></div>
                                                </div>
                                                
                                                <!-- Hidden audio element -->
                                                <audio preload="none" style="display: none;">
                                                                                                    <?php 
                                                $folder_name = $sermon['sermon_folder_name'] ?? '';
                                                $audio_path = "/G.A.N.G/admin/sermon/audio/" . htmlspecialchars($folder_name) . "/" . htmlspecialchars($chunk['filename']);
                                                ?>
                                                <source src="<?php echo $audio_path; ?>" type="audio/webm">
                                                    Your browser does not support the audio element.
                                                </audio>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Presentation Slides -->
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
                                                <div class="file-name"><?php echo htmlspecialchars($slide['original_filename']); ?></div>
                                                <div class="file-size"><?php echo formatFileSize($slide['file_size']); ?></div>
                                            </div>
                                            <?php 
                                            $folder_name = $sermon['sermon_folder_name'] ?? '';
                                            $slide_path = "/G.A.N.G/admin/sermon/audio/" . htmlspecialchars($folder_name) . "/slides/" . htmlspecialchars($slide['saved_filename']);
                                            ?>
                                            <a href="<?php echo $slide_path; ?>" 
                                               class="btn btn-sm btn-outline-info" target="_blank">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
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
                                    <i class="bi bi-download me-2"></i>Download All Audio
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($sermon['slides'])): ?>
                                <a href="/G.A.N.G/admin/sermon/download.php?type=slides&sermon_id=<?php echo $sermon['id']; ?>" 
                                   class="btn btn-info btn-download">
                                    <i class="bi bi-download me-2"></i>Download All Slides
                                </a>
                            <?php endif; ?>
                            
                            <a href="/G.A.N.G/admin/sermon/download.php?type=complete&sermon_id=<?php echo $sermon['id']; ?>" 
                               class="btn btn-primary btn-download">
                                <i class="bi bi-archive me-2"></i>Download Complete Package
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Sermons pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&speaker=<?php echo urlencode($speaker_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
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
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&speaker=<?php echo urlencode($speaker_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&speaker=<?php echo urlencode($speaker_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div id="footer"></div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/G.A.N.G/includes/js/include.js"></script>
    
    <!-- Audio Player JavaScript -->
    <script>
        let currentlyPlaying = null;
        let audioElements = new Map();

        function toggleAudio(button) {
            const player = button.closest('.audio-player');
            const audioElement = player.parentElement.querySelector('audio');
            const playIcon = button.querySelector('i');
            const progressBar = player.querySelector('.audio-progress');
            const timeDisplay = player.querySelector('.audio-time');
            const loading = player.querySelector('.audio-loading');

            // Stop any currently playing audio
            if (currentlyPlaying && currentlyPlaying !== audioElement) {
                pauseAudio(currentlyPlaying);
            }

            if (audioElement.paused) {
                // Show loading
                loading.style.display = 'block';
                button.style.display = 'none';

                // Load and play audio
                if (audioElement.readyState === 0) {
                    audioElement.load();
                }

                audioElement.play().then(() => {
                    // Hide loading, show pause button
                    loading.style.display = 'none';
                    button.style.display = 'flex';
                    playIcon.className = 'bi bi-pause-fill';
                    player.classList.add('playing');
                    currentlyPlaying = audioElement;

                    // Set up event listeners if not already done
                    if (!audioElements.has(audioElement)) {
                        setupAudioListeners(audioElement, progressBar, timeDisplay, button, player);
                        audioElements.set(audioElement, true);
                    }
                }).catch((error) => {
                    console.error('Error playing audio:', error);
                    loading.style.display = 'none';
                    button.style.display = 'flex';
                });
            } else {
                pauseAudio(audioElement);
            }
        }

        function pauseAudio(audioElement) {
            audioElement.pause();
            const player = audioElement.parentElement.querySelector('.audio-player');
            const button = player.querySelector('.play-pause-btn');
            const playIcon = button.querySelector('i');
            
            playIcon.className = 'bi bi-play-fill';
            player.classList.remove('playing');
            
            if (currentlyPlaying === audioElement) {
                currentlyPlaying = null;
            }
        }

        function setupAudioListeners(audioElement, progressBar, timeDisplay, button, player) {
            audioElement.addEventListener('timeupdate', () => {
                if (audioElement.duration) {
                    const progress = (audioElement.currentTime / audioElement.duration) * 100;
                    progressBar.style.width = progress + '%';
                    timeDisplay.textContent = formatTime(audioElement.currentTime);
                }
            });

            audioElement.addEventListener('ended', () => {
                const playIcon = button.querySelector('i');
                playIcon.className = 'bi bi-play-fill';
                player.classList.remove('playing');
                progressBar.style.width = '0%';
                timeDisplay.textContent = '0:00';
                currentlyPlaying = null;
            });

            audioElement.addEventListener('loadedmetadata', () => {
                timeDisplay.textContent = formatTime(audioElement.duration);
            });

            audioElement.addEventListener('error', (e) => {
                console.error('Audio error:', e);
                const loading = player.querySelector('.audio-loading');
                loading.style.display = 'none';
                button.style.display = 'flex';
            });
        }

        function seekAudio(waveform, event) {
            const player = waveform.closest('.audio-player');
            const audioElement = player.parentElement.querySelector('audio');
            
            if (audioElement.duration) {
                const rect = waveform.getBoundingClientRect();
                const clickX = event.clientX - rect.left;
                const percentage = clickX / rect.width;
                const newTime = percentage * audioElement.duration;
                
                audioElement.currentTime = newTime;
            }
        }

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
        }

        // Pause all audio when page is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && currentlyPlaying) {
                pauseAudio(currentlyPlaying);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && currentlyPlaying && !e.target.matches('input, textarea, select')) {
                e.preventDefault();
                const player = currentlyPlaying.parentElement.querySelector('.audio-player');
                const button = player.querySelector('.play-pause-btn');
                toggleAudio(button);
            }
        });
    </script>
</body>
</html>

