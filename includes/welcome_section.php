<?php
// Welcome Section Component
// This should be included after the header for logged-in users

// Check if user is logged in
$is_logged_in = isset($_SESSION['member_id']);
$welcome_message = '';
$user_display_name = '';

if ($is_logged_in) {
    $first_name = $_SESSION['first_name'] ?? '';
    $second_name = $_SESSION['second_name'] ?? '';
    $third_name = $_SESSION['third_name'] ?? '';
    $user_picture = $_SESSION['picture'] ?? '';
    $gender = $_SESSION['gender'] ?? '';
    $role = $_SESSION['role'] ?? '';
    
    $user_display_name = trim($first_name . ' ' . $second_name . ' ' . $third_name);
    
    // Generate welcome message based on time of day
    $hour = date('H');
    if ($hour < 12) {
        $welcome_message = "Good morning";
    } elseif ($hour < 17) {
        $welcome_message = "Good afternoon";
    } else {
        $welcome_message = "Good evening";
    }
    
    $welcome_message .= ", " . $user_display_name;
}

function getDefaultAvatar($gender, $name) {
    $initial = strtoupper(substr($name, 0, 1));
    $bg_color = $gender === 'Female' ? '#e91e63' : '#2196f3';
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='$bg_color'/%3E%3Ctext x='50' y='65' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3E$initial%3C/text%3E%3C/svg%3E";
}

function getUserAvatar($picture, $gender, $name) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/G.A.N.G/registration/profile_pics/' . basename($picture);
    if (!empty($picture) && file_exists($path)) {
        return '/G.A.N.G/registration/profile_pics/' . basename($picture);
    }
    return getDefaultAvatar($gender, $name);
}
?>

    <!-- Welcome Section (only shown for logged-in users) -->
    <?php if ($is_logged_in): ?>
    <div class="welcome-section" id="welcomeSection">
        <style>
            .welcome-section {
                margin-top: 30px;
            }
        </style>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="welcome-content">
                    <h2 class="welcome-message">
                        <i class="bi bi-sunrise me-2"></i><?php echo htmlspecialchars($welcome_message); ?>
                    </h2>
                    <p class="welcome-subtitle">
                        Welcome to God's Appointed New Generation. We're glad to have you here!
                    </p>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="profile-container">
                    <div class="profile-picture-wrapper" id="profilePictureWrapper">
                        <img src="<?php echo getUserAvatar($user_picture, $gender, $user_display_name); ?>" 
                             alt="Profile Picture" 
                             class="profile-picture" 
                             id="profilePicture"
                             data-bs-toggle="dropdown" 
                             aria-expanded="false">
                        
                        <!-- Profile Dropdown Menu -->
                        <div class="dropdown-menu profile-dropdown" id="profileDropdown">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($user_display_name); ?></strong>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($role); ?></small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                                <i class="bi bi-pencil me-2"></i>Update Profile Picture
                            </button>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="/G.A.N.G/auth/login.html">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Profile Modal -->
<div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProfileModalLabel">
                    <i class="bi bi-person-circle me-2"></i>Update Profile Picture
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateProfileForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="current-profile-preview">
                            <img src="<?php echo getUserAvatar($user_picture, $gender, $user_display_name); ?>" 
                                 alt="Current Profile" 
                                 class="current-profile-picture" 
                                 id="currentProfilePreview">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newProfilePicture" class="form-label">
                            <i class="bi bi-camera me-2"></i>Select New Picture
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="newProfilePicture" 
                               name="profile_picture" 
                               accept="image/*" 
                               required>
                        <div class="form-text">Supported formats: JPG, PNG, GIF. Max size: 5MB</div>
                    </div>
                    
                    <div class="mb-3" id="imagePreviewContainer" style="display: none;">
                        <label class="form-label">Preview:</label>
                        <div class="text-center">
                            <img id="imagePreview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateProfileBtn">
                        <i class="bi bi-check-circle me-2"></i>Update Picture
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success/Error Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="profileUpdateToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-info-circle me-2"></i>
            <strong class="me-auto" id="toastTitle">Profile Update</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            Profile picture updated successfully!
        </div>
    </div>
</div>

<?php endif; ?>

<style>
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 0;
    margin-top: 0; /* No margin since it's attached to the header */
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.welcome-message {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 5px;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
}

.welcome-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.profile-container {
    display: flex;
    justify-content: flex-end;
    align-items: center;
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
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.3);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.profile-picture:hover {
    border-color: rgba(255,255,255,0.8);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.profile-dropdown {
    min-width: 250px;
    padding: 0;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border-radius: 12px;
    overflow: hidden;
}

.dropdown-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.dropdown-item {
    padding: 12px 20px;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item i {
    width: 16px;
}

.current-profile-preview {
    margin-bottom: 20px;
}

.current-profile-picture {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e9ecef;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

#imagePreview {
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.toast {
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

@media (max-width: 768px) {
    .welcome-section {
        padding: 15px 0;
        margin-top: 70px;
    }
    
    .welcome-message {
        font-size: 1.4rem;
    }
    
    .welcome-subtitle {
        font-size: 0.9rem;
    }
    
    .profile-container {
        justify-content: center;
        margin-top: 15px;
    }
    
    .profile-picture {
        width: 50px;
        height: 50px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile picture preview
    const fileInput = document.getElementById('newProfilePicture');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const currentProfilePreview = document.getElementById('currentProfilePreview');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreviewContainer.style.display = 'none';
            }
        });
    }
    
    // Form submission
    const updateProfileForm = document.getElementById('updateProfileForm');
    if (updateProfileForm) {
        updateProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const updateBtn = document.getElementById('updateProfileBtn');
            const originalText = updateBtn.innerHTML;
            
            // Show loading state
            updateBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';
            updateBtn.disabled = true;
            
            fetch('/G.A.N.G/includes/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update profile pictures
                    const profilePicture = document.getElementById('profilePicture');
                    if (profilePicture) {
                        profilePicture.src = data.picture_url;
                    }
                    if (currentProfilePreview) {
                        currentProfilePreview.src = data.picture_url;
                    }
                    
                    // Show success message
                    showToast('Success!', data.message, 'success');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('updateProfileModal'));
                    modal.hide();
                    
                    // Reset form
                    updateProfileForm.reset();
                    imagePreviewContainer.style.display = 'none';
                } else {
                    showToast('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error!', 'An error occurred while updating your profile picture.', 'error');
            })
            .finally(() => {
                // Reset button state
                updateBtn.innerHTML = originalText;
                updateBtn.disabled = false;
            });
        });
    }
    
    function showToast(title, message, type) {
        const toast = document.getElementById('profileUpdateToast');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');
        
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        
        // Update toast appearance based on type
        toast.className = `toast ${type === 'success' ? 'bg-success text-white' : 'bg-danger text-white'}`;
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }
});
</script> 