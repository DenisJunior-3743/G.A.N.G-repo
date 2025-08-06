<?php
// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['first_name'] . ' ' . $_SESSION['second_name'];
    $user_picture = $_SESSION['picture'] ?? '';
    $gender = $_SESSION['gender'] ?? 'Male';
    
    // Get time-based greeting
    $hour = date('H');
    if ($hour < 12) {
        $greeting = "Good Morning";
    } elseif ($hour < 17) {
        $greeting = "Good Afternoon";
    } else {
        $greeting = "Good Evening";
    }
    
    $user_display_name = trim($user_name);
}

// Unique function names for welcome section to avoid conflicts
function getWelcomeDefaultAvatar($gender, $name) {
    $initial = strtoupper(substr($name, 0, 1));
    $bg_color = ($gender === 'Female') ? '#e91e63' : '#2196f3';
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='$bg_color'/%3E%3Ctext x='50' y='65' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3E$initial%3C/text%3E%3C/svg%3E";
}

function getWelcomeUserAvatar($picture, $gender, $name) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/G.A.N.G/registration/profile_pics/' . basename($picture);
    if (!empty($picture) && file_exists($path)) {
        return '/G.A.N.G/registration/profile_pics/' . basename($picture);
    }
    return getWelcomeDefaultAvatar($gender, $name);
}
?>

<!-- Welcome Section (only shown for logged-in users) -->
<?php if ($is_logged_in): ?>
<div class="welcome-section" id="welcomeSection">
    <style>
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 0;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .welcome-section .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .welcome-message h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.4rem;
        }
        
        .welcome-message p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .profile-container {
            position: relative;
        }
        
        .profile-picture-wrapper {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .profile-picture-wrapper:hover {
            transform: scale(1.05);
        }
        
        .profile-picture {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.3);
            object-fit: cover;
            transition: all 0.3s ease;
        }
        
        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 8px 0;
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 5px;
        }
        
        .profile-dropdown.show {
            display: block;
        }
        
        .profile-dropdown a {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        
        .profile-dropdown a:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .current-profile-picture {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            margin-bottom: 15px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-wrapper .btn {
            width: 100%;
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
    
    <div class="container">
        <div class="col-md-8">
            <div class="welcome-message">
                <h4><?php echo $greeting; ?>, <?php echo htmlspecialchars($user_display_name); ?>!</h4>
                <p>Welcome back to G.A.N.G. We're glad to see you today.</p>
            </div>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="d-flex align-items-center justify-content-end gap-3">
                <!-- Fullscreen Toggle Button -->
                <button class="btn btn-outline-light btn-sm" id="fullscreenToggle" onclick="toggleFullscreen()" title="Toggle Fullscreen View">
                    <i class="fas fa-expand" id="fullscreenIcon"></i>
                </button>
                
                <!-- Profile Container -->
                <div class="profile-container">
                    <div class="profile-picture-wrapper" id="profilePictureWrapper">
                        <img src="<?php echo getWelcomeUserAvatar($user_picture, $gender, $user_display_name); ?>" 
                             alt="Profile Picture" 
                             class="profile-picture" 
                             id="profilePicture"
                             onclick="toggleProfileDropdown()">
                        <div class="profile-dropdown" id="profileDropdown">
                            <a href="#" onclick="openProfileModal()">
                                <i class="fas fa-edit"></i> Update Profile Picture
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Update Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="current-profile-preview">
                        <img src="<?php echo getWelcomeUserAvatar($user_picture, $gender, $user_display_name); ?>" 
                             alt="Current Profile" 
                             class="current-profile-picture" 
                             id="currentProfilePreview">
                    </div>
                </div>
                <form id="profileUpdateForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profilePictureInput" class="form-label">Choose New Profile Picture</label>
                        <div class="file-input-wrapper">
                            <input type="file" 
                                   class="form-control" 
                                   id="profilePictureInput" 
                                   name="profile_picture" 
                                   accept="image/*" 
                                   required>
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('profilePictureInput').click()">
                                <i class="fas fa-upload"></i> Select Image
                            </button>
                        </div>
                        <div class="form-text">Supported formats: JPG, PNG, GIF (Max 5MB)</div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile Picture
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Fullscreen toggle functionality
let isFullscreen = false;

function toggleFullscreen() {
    const header = document.getElementById('header');
    const footer = document.getElementById('footer');
    const welcomeSection = document.getElementById('welcomeSection');
    const body = document.body;
    const icon = document.getElementById('fullscreenIcon');
    
    isFullscreen = !isFullscreen;
    
    if (isFullscreen) {
        // Hide header and footer
        if (header) header.style.display = 'none';
        if (footer) footer.style.display = 'none';
        if (welcomeSection) welcomeSection.style.display = 'none';
        
        // Add fullscreen class to body
        body.classList.add('fullscreen-mode');
        
        // Change icon to compress
        icon.className = 'fas fa-compress';
        
        // Store original scroll position
        window.fullscreenScrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Scroll to top for better experience
        window.scrollTo(0, 0);
    } else {
        // Show header and footer
        if (header) header.style.display = '';
        if (footer) footer.style.display = '';
        if (welcomeSection) welcomeSection.style.display = '';
        
        // Remove fullscreen class from body
        body.classList.remove('fullscreen-mode');
        
        // Change icon back to expand
        icon.className = 'fas fa-expand';
        
        // Restore scroll position
        if (window.fullscreenScrollTop !== undefined) {
            window.scrollTo(0, window.fullscreenScrollTop);
        }
    }
}

// Keyboard shortcut for fullscreen toggle (F11 or Escape)
document.addEventListener('keydown', function(e) {
    if (e.key === 'F11') {
        e.preventDefault();
        toggleFullscreen();
    } else if (e.key === 'Escape' && isFullscreen) {
        toggleFullscreen();
    }
});

function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const wrapper = document.getElementById('profilePictureWrapper');
    const dropdown = document.getElementById('profileDropdown');
    
    if (!wrapper.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

function openProfileModal() {
    const modal = new bootstrap.Modal(document.getElementById('profileModal'));
    modal.show();
    document.getElementById('profileDropdown').classList.remove('show');
}

// Handle file input change
document.getElementById('profilePictureInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Preview the selected image
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('currentProfilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Handle form submission
document.getElementById('profileUpdateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('user_id', '<?php echo $user_id; ?>');
    
    fetch('/G.A.N.G/includes/update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the profile picture in the welcome section
            document.getElementById('profilePicture').src = data.picture_url;
            
            // Show success toast
            showToast('Profile picture updated successfully!', 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
            modal.hide();
            
            // Reset form
            document.getElementById('profileUpdateForm').reset();
            document.getElementById('currentProfilePreview').src = '<?php echo getWelcomeUserAvatar($user_picture, $gender, $user_display_name); ?>';
        } else {
            showToast(data.message || 'Failed to update profile picture', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating profile picture', 'error');
    });
});

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0" 
             role="alert" 
             aria-live="assertive" 
             aria-atomic="true" 
             id="${toastId}">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}
</script>
<?php endif; ?> 