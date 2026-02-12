<?php 
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$success = false;

if($_SERVER['REQUEST_METHOD'] == "POST") {
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Security token invalid. Please try again.";
    } else {
        $username = filter_var($_POST['username'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $bio = filter_var($_POST['bio'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $bio = substr($bio, 0, 500); // Limit bio length

        // Check if username already exists (excluding current user)
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$username, $_SESSION['user_id']]);
        
        if($check_stmt->fetch()) {
            $message = "Username already taken. Please choose another.";
        } else {
            // Handle avatar upload
            $avatar_file = null;
            
            if(!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                
                if(in_array($file_ext, $allowed_extensions)) {
                    // Check file size (max 5MB)
                    if($_FILES['avatar']['size'] <= 5 * 1024 * 1024) {
                        // Generate unique filename
                        $avatar_file = uniqid() . '.' . $file_ext;
                        $upload_path = "uploads/" . $avatar_file;
                        
                        // Move uploaded file
                        if(move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                            // Update avatar in database
                            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")
                                ->execute([$avatar_file, $_SESSION['user_id']]);
                        } else {
                            $message = "Failed to upload avatar image.";
                        }
                    } else {
                        $message = "Avatar image size too large. Maximum 5MB allowed.";
                    }
                } else {
                    $message = "Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP";
                }
            }
            
            // Update username and bio
            $pdo->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?")
                ->execute([$username, $bio, $_SESSION['user_id']]);
            
            if(empty($message)) {
                $message = "Profile updated successfully!";
                $success = true;
            }
        }
    }
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If no avatar, use default
if(empty($user['avatar']) || !file_exists('uploads/' . $user['avatar'])) {
    $user['avatar'] = 'default.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SUGO Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --radius: 12px;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--light);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }
        
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }
        
        .avatar-upload {
            position: relative;
            margin-right: 2rem;
        }
        
        .avatar-change-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .avatar-change-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }
        
        .profile-info h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #fff 30%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-label {
            color: #cbd5e1;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .textarea-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
            color: white;
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #86efac;
            border-radius: 8px;
            padding: 12px 16px;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 8px;
            padding: 12px 16px;
        }
        
        .char-count {
            font-size: 0.85rem;
            color: var(--gray);
            text-align: right;
            margin-top: 5px;
        }
        
        .file-info {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .nav-buttons {
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .avatar-upload {
                margin-right: 0;
                margin-bottom: 1.5rem;
            }
            
            .profile-stats {
                justify-content: center;
            }
        }
        
        .bio-preview {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-top: 1rem;
        }
        
        .bio-preview h6 {
            color: #94a3b8;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .bio-preview p {
            color: white;
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        /* Add to existing CSS */

/* Animation for task notifications */
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes glow {
    from { box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
    to { box-shadow: 0 0 20px rgba(16, 185, 129, 0.8); }
}

/* Profile avatar hover effect */
.profile-avatar {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.profile-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
}

/* Bio preview link styling */
.bio-preview a {
    color: #6366f1;
    text-decoration: none;
    transition: color 0.3s ease;
}

.bio-preview a:hover {
    color: #8b5cf6;
    text-decoration: underline;
}

.bio-preview .text-primary {
    color: #8b5cf6 !important;
}

.bio-preview .text-success {
    color: #10b981 !important;
}

/* Character count styling */
#usernameCount, #bioCount {
    font-weight: 600;
    transition: color 0.3s ease;
}

#usernameCount.text-success,
#bioCount.text-success {
    color: #10b981 !important;
}

#usernameCount.text-warning,
#bioCount.text-warning {
    color: #f59e0b !important;
}

#usernameCount.text-danger,
#bioCount.text-danger {
    color: #ef4444 !important;
}

/* Form input focus effects */
.form-control:focus {
    transform: translateY(-1px);
    box-shadow: 0 5px 20px rgba(99, 102, 241, 0.2) !important;
    border-color: #6366f1;
}

/* Avatar change button */
.avatar-change-btn {
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.avatar-change-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
}

/* Bio formatting buttons */
.btn-outline-secondary {
    border-color: rgba(255, 255, 255, 0.2);
    color: #94a3b8;
    transition: all 0.3s ease;
}

.btn-outline-secondary:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
    color: white;
    transform: translateY(-1px);
}

/* Coin tip styling */
.coin-tip {
    backdrop-filter: blur(10px);
}

/* Loading state for submit button */
button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Bio preview transitions */
#bioPreview {
    transition: all 0.3s ease;
    line-height: 1.6;
}

/* Mobile responsiveness for formatting buttons */
@media (max-width: 768px) {
    .format-buttons {
        justify-content: center;
    }
    
    .format-buttons .btn {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
    .bio-preview {
        background: rgba(255, 255, 255, 0.05);
    }
    
    .form-control {
        background: rgba(15, 23, 42, 0.9);
    }
}

/* Print styles */
@media print {
    .avatar-change-btn,
    .format-buttons,
    .coin-tip,
    .task-notification {
        display: none !important;
    }
}
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Navigation -->
        <div class="nav-buttons">
            <a href="home.php" class="btn btn-outline-light mb-2">
                <i class="fas fa-home me-2"></i>Home
            </a>
            <a href="discover.php" class="btn btn-outline-info mb-2">
                <i class="fas fa-users me-2"></i>Discover
            </a>
            <a href="messages.php" class="btn btn-outline-warning mb-2">
                <i class="fas fa-comments me-2"></i>Messages
            </a>
        </div>
        
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-upload">
                    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" 
                         class="profile-avatar" 
                         alt="Profile Avatar"
                         id="avatarPreview">
                    <label for="avatar" class="avatar-change-btn" title="Change Avatar">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
                
                <div class="profile-info">
                    <h2 id="usernamePreview"><?= htmlspecialchars($user['username']) ?></h2>
                    <p class="text-muted">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?= $user['coins'] ?></div>
                            <div class="stat-label">Coins</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                // Count messages sent by user
                                $msg_stmt = $pdo->prepare("SELECT COUNT(*) FROM private_messages WHERE sender_id = ?");
                                $msg_stmt->execute([$_SESSION['user_id']]);
                                echo $msg_stmt->fetchColumn();
                                ?>
                            </div>
                            <div class="stat-label">Messages</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                // Check if user is online (active within 5 minutes)
                                $active_stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(MINUTE, last_active, NOW()) < 5 as is_online FROM users WHERE id = ?");
                                $active_stmt->execute([$_SESSION['user_id']]);
                                echo $active_stmt->fetch()['is_online'] ? 'Online' : 'Offline';
                                ?>
                            </div>
                            <div class="stat-label">Status</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Message Alert -->
            <?php if(!empty($message)): ?>
                <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?> mb-4">
                    <i class="fas <?= $success ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Form -->
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Avatar Upload -->
                <div class="form-section">
                    <label class="form-label">Profile Picture</label>
                    <input type="file" 
                           name="avatar" 
                           id="avatar" 
                           class="form-control" 
                           accept="image/*"
                           style="display: none;">
                    <div class="file-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Click the camera icon to change your avatar. Max size: 5MB. Allowed formats: JPG, PNG, GIF, WEBP
                    </div>
                </div>
                
                <!-- Username -->
                <div class="form-section">
                    <label class="form-label">Username</label>
                    <input type="text" 
                           name="username" 
                           class="form-control" 
                           value="<?= htmlspecialchars($user['username']) ?>" 
                           required
                           minlength="3"
                           maxlength="30"
                           id="usernameInput">
                    <div class="char-count">
                        <span id="usernameCount"><?= strlen($user['username']) ?></span>/30 characters
                    </div>
                </div>
                
                <!-- Bio -->
                <div class="form-section">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" 
                              class="form-control textarea-control" 
                              id="bioInput" 
                              maxlength="500"
                              placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <div class="char-count">
                        <span id="bioCount"><?= strlen($user['bio'] ?? '') ?></span>/500 characters
                    </div>
                    
                    <!-- Bio Preview -->
                    <div class="bio-preview mt-3">
                        <h6>Preview:</h6>
                        <p id="bioPreview">
                            <?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : 'Your bio will appear here...' ?>
                        </p>
                    </div>
                </div>
                
                <!-- Form Buttons -->
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                    <a href="profile_view.php?user=<?= $_SESSION['user_id'] ?>" class="btn btn-outline-light">
                        <i class="fas fa-eye me-2"></i>View Profile
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Account Info Card -->
        <div class="profile-card">
            <h4 class="mb-4"><i class="fas fa-user-shield me-2"></i>Account Information</h4>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="form-section">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        <small class="text-muted">Contact support to change your email</small>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="form-section">
                        <label class="form-label">Account Created</label>
                        <input type="text" class="form-control" value="<?= date('F j, Y', strtotime($user['created_at'])) ?>" readonly>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>

   <script>
    // Avatar upload preview
    document.getElementById('avatar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum 5MB allowed.');
                this.value = ''; // Clear input
                return;
            }
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP');
                this.value = ''; // Clear input
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
                
                // Auto-complete update profile task
                setTimeout(() => {
                    completeTask('update_profile');
                }, 1000);
            }
            reader.readAsDataURL(file);
            
            // Show file info
            const fileInfo = document.querySelector('.file-info');
            if (fileInfo) {
                fileInfo.innerHTML = `
                    <i class="fas fa-check-circle text-success me-1"></i>
                    Selected: ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                    <br>
                    <small class="text-muted">Click "Save Changes" to upload</small>
                `;
            }
        }
    });
    
    // Trigger file input when camera icon is clicked
    document.querySelector('.avatar-change-btn').addEventListener('click', function() {
        document.getElementById('avatar').click();
    });
    
    // Username character count
    const usernameInput = document.getElementById('usernameInput');
    const usernameCount = document.getElementById('usernameCount');
    const usernamePreview = document.getElementById('usernamePreview');
    
    usernameInput.addEventListener('input', function() {
        const length = this.value.length;
        usernameCount.textContent = length;
        usernamePreview.textContent = this.value || "<?= htmlspecialchars($user['username']) ?>";
        
        // Check username availability
        if (length >= 3) {
            checkUsernameAvailability(this.value);
        }
    });
    
    // Bio character count and preview
    const bioInput = document.getElementById('bioInput');
    const bioCount = document.getElementById('bioCount');
    const bioPreview = document.getElementById('bioPreview');
    
    bioInput.addEventListener('input', function() {
        const length = this.value.length;
        bioCount.textContent = length;
        
        // Update preview with line breaks and links
        const previewText = this.value || 'Your bio will appear here...';
        const formattedText = this.formatBioText(previewText);
        bioPreview.innerHTML = formattedText;
        
        // Auto-complete bio task if bio is decent length
        if (length >= 20 && length <= 500) {
            setTimeout(() => {
                completeTask('complete_bio');
            }, 2000);
        }
    });
    
    // Format bio text with links and line breaks
    bioInput.formatBioText = function(text) {
        // Convert URLs to links
        let formatted = text.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" class="text-info">$1</a>'
        );
        
        // Convert line breaks
        formatted = formatted.replace(/\n/g, '<br>');
        
        // Convert @mentions
        formatted = formatted.replace(
            /@(\w+)/g,
            '<span class="text-primary">@$1</span>'
        );
        
        // Convert #hashtags
        formatted = formatted.replace(
            /#(\w+)/g,
            '<span class="text-success">#$1</span>'
        );
        
        return formatted;
    };
    
    // Check username availability
    async function checkUsernameAvailability(username) {
        if (username.length < 3) return;
        
        try {
            const response = await fetch('check_username.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&current_id=<?= $_SESSION['user_id'] ?>`
            });
            
            const data = await response.json();
            const usernameCount = document.getElementById('usernameCount');
            
            if (data.available) {
                usernameCount.classList.remove('text-danger');
                usernameCount.classList.add('text-success');
                usernameCount.title = 'Username available';
            } else {
                usernameCount.classList.remove('text-success');
                usernameCount.classList.add('text-danger');
                usernameCount.title = 'Username already taken';
            }
        } catch (error) {
            console.error('Username check failed:', error);
        }
    }
    
    // Complete task function
    function completeTask(taskType) {
        fetch('tasks_complete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `task_type=${taskType}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                showTaskNotification(`Task completed! +${data.coins_added} coins earned!`);
                
                // Update coin display if exists
                const coinElements = document.querySelectorAll('.coin-display, .coins-badge');
                coinElements.forEach(el => {
                    const current = parseInt(el.textContent) || 0;
                    const newCoins = current + data.coins_added;
                    el.innerHTML = el.innerHTML.replace(/\d+/, newCoins);
                });
            }
        })
        .catch(err => console.log('Task completion error:', err));
    }
    
    // Show task notification
    function showTaskNotification(message) {
        // Remove existing notification
        const existing = document.querySelector('.task-notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.className = 'task-notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease, slideOutRight 0.3s ease 2.7s;
        `;
        
        notification.innerHTML = `
            <i class="fas fa-check-circle fa-2x"></i>
            <div>
                <strong>${message}</strong><br>
                <small>Keep completing tasks for more coins!</small>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
    
    // Form validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = usernameInput.value.trim();
        const avatarFile = document.getElementById('avatar').files[0];
        
        // Validation checks
        let isValid = true;
        let errorMessage = '';
        
        // Username validation
        if (username.length < 3) {
            isValid = false;
            errorMessage = 'Username must be at least 3 characters long.';
        } else if (username.length > 30) {
            isValid = false;
            errorMessage = 'Username cannot exceed 30 characters.';
        }
        
        // Avatar file validation
        if (avatarFile) {
            if (avatarFile.size > 5 * 1024 * 1024) {
                isValid = false;
                errorMessage = 'Avatar image size too large. Maximum 5MB allowed.';
            }
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(avatarFile.type)) {
                isValid = false;
                errorMessage = 'Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP';
            }
        }
        
        if (!isValid) {
            // Show error
            showFormError(errorMessage);
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Saving...
        `;
        submitBtn.disabled = true;
        
        // Submit form
        const formData = new FormData(this);
        
        fetch('profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Create a temporary div to parse the response
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Check if there's an alert in the response
            const alertDiv = tempDiv.querySelector('.alert');
            if (alertDiv) {
                // Replace the form with new content
                document.querySelector('.profile-card').innerHTML = tempDiv.querySelector('.profile-card').innerHTML;
                
                // Re-initialize event listeners
                initializeProfileForm();
                
                // Show success animation
                if (alertDiv.classList.contains('alert-success')) {
                    showSuccessAnimation();
                }
            } else {
                // Reload the page if something went wrong
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
           
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
        
        return false;
    });
    
    // Show form error
    function showFormError(message) {
        // Remove existing error
        const existing = document.querySelector('.form-error');
        if (existing) existing.remove();
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error';
        errorDiv.style.cssText = `
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: shake 0.5s ease;
        `;
        
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle me-2"></i>
            ${message}
        `;
        
        const form = document.getElementById('profileForm');
        form.parentNode.insertBefore(errorDiv, form);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
    
    // Show success animation
    function showSuccessAnimation() {
        const avatar = document.getElementById('avatarPreview');
        if (!avatar) return;
        
        // Create confetti effect
        for (let i = 0; i < 20; i++) {
            createConfetti(avatar);
        }
        
        // Pulse animation
        avatar.style.animation = 'pulse 1s ease';
        setTimeout(() => {
            avatar.style.animation = '';
        }, 1000);
    }
    
    // Create confetti effect
    function createConfetti(element) {
        const confetti = document.createElement('div');
        confetti.style.cssText = `
            position: absolute;
            width: 8px;
            height: 8px;
            background: ${getRandomColor()};
            border-radius: 50%;
            pointer-events: none;
            z-index: 1000;
        `;
        
        const rect = element.getBoundingClientRect();
        const startX = rect.left + rect.width / 2;
        const startY = rect.top + rect.height / 2;
        
        confetti.style.left = startX + 'px';
        confetti.style.top = startY + 'px';
        
        document.body.appendChild(confetti);
        
        // Animate
        const angle = Math.random() * Math.PI * 2;
        const velocity = 2 + Math.random() * 3;
        const vx = Math.cos(angle) * velocity;
        const vy = Math.sin(angle) * velocity;
        const gravity = 0.1;
        
        let x = startX;
        let y = startY;
        let opacity = 1;
        
        function animate() {
            x += vx;
            y += vy;
            vy += gravity;
            opacity -= 0.02;
            
            confetti.style.left = x + 'px';
            confetti.style.top = y + 'px';
            confetti.style.opacity = opacity;
            
            if (opacity > 0) {
                requestAnimationFrame(animate);
            } else {
                confetti.remove();
            }
        }
        
        animate();
    }
    
    // Get random color for confetti
    function getRandomColor() {
        const colors = [
            '#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
            '#06b6d4', '#3b82f6', '#ef4444', '#84cc16', '#f97316'
        ];
        return colors[Math.floor(Math.random() * colors.length)];
    }
    
    // Initialize form after dynamic content changes
    function initializeProfileForm() {
        // Re-attach event listeners
        const newAvatarInput = document.getElementById('avatar');
        const newAvatarChangeBtn = document.querySelector('.avatar-change-btn');
        const newUsernameInput = document.getElementById('usernameInput');
        const newBioInput = document.getElementById('bioInput');
        const newForm = document.getElementById('profileForm');
        
        if (newAvatarInput) {
            newAvatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size too large. Maximum 5MB allowed.');
                        this.value = '';
                        return;
                    }
                    
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP');
                        this.value = '';
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('avatarPreview').src = e.target.result;
                        setTimeout(() => {
                            completeTask('update_profile');
                        }, 1000);
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
        
        if (newAvatarChangeBtn) {
            newAvatarChangeBtn.addEventListener('click', function() {
                document.getElementById('avatar').click();
            });
        }
        
        if (newUsernameInput) {
            newUsernameInput.addEventListener('input', function() {
                const length = this.value.length;
                const usernameCount = document.getElementById('usernameCount');
                const usernamePreview = document.getElementById('usernamePreview');
                
                if (usernameCount) usernameCount.textContent = length;
                if (usernamePreview) usernamePreview.textContent = this.value || "<?= htmlspecialchars($user['username']) ?>";
                
                if (length >= 3) {
                    checkUsernameAvailability(this.value);
                }
            });
        }
        
        if (newBioInput) {
            newBioInput.addEventListener('input', function() {
                const length = this.value.length;
                const bioCount = document.getElementById('bioCount');
                const bioPreview = document.getElementById('bioPreview');
                
                if (bioCount) bioCount.textContent = length;
                
                const previewText = this.value || 'Your bio will appear here...';
                const formattedText = formatBioText(previewText);
                if (bioPreview) bioPreview.innerHTML = formattedText;
                
                if (length >= 20 && length <= 500) {
                    setTimeout(() => {
                        completeTask('complete_bio');
                    }, 2000);
                }
            });
        }
        
        if (newForm) {
            newForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const username = newUsernameInput ? newUsernameInput.value.trim() : '';
                const avatarFile = newAvatarInput ? newAvatarInput.files[0] : null;
                
                let isValid = true;
                let errorMessage = '';
                
                if (username.length < 3) {
                    isValid = false;
                    errorMessage = 'Username must be at least 3 characters long.';
                } else if (username.length > 30) {
                    isValid = false;
                    errorMessage = 'Username cannot exceed 30 characters.';
                }
                
                if (avatarFile) {
                    if (avatarFile.size > 5 * 1024 * 1024) {
                        isValid = false;
                        errorMessage = 'Avatar image size too large. Maximum 5MB allowed.';
                    }
                    
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(avatarFile.type)) {
                        isValid = false;
                        errorMessage = 'Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP';
                    }
                }
                
                if (!isValid) {
                    showFormError(errorMessage);
                    return false;
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Saving...
                `;
                submitBtn.disabled = true;
                
                const formData = new FormData(this);
                
                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const alertDiv = tempDiv.querySelector('.alert');
                    if (alertDiv) {
                        document.querySelector('.profile-card').innerHTML = tempDiv.querySelector('.profile-card').innerHTML;
                        initializeProfileForm();
                        
                        if (alertDiv.classList.contains('alert-success')) {
                            showSuccessAnimation();
                        }
                    } else {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showFormError('An error occurred. Please try again.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
                
                return false;
            });
        }
    }
    
    // Format bio text helper
    function formatBioText(text) {
        let formatted = text.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" class="text-info">$1</a>'
        );
        
        formatted = formatted.replace(/\n/g, '<br>');
        formatted = formatted.replace(
            /@(\w+)/g,
            '<span class="text-primary">@$1</span>'
        );
        
        formatted = formatted.replace(
            /#(\w+)/g,
            '<span class="text-success">#$1</span>'
        );
        
        return formatted;
    }
    
    // Auto-update user status
    setInterval(() => {
        fetch('update_status.php').catch(() => {});
    }, 30000);
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .profile-avatar {
            transition: transform 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .bio-preview a {
            text-decoration: none;
        }
        
        .bio-preview a:hover {
            text-decoration: underline;
        }
        
        .avatar-change-btn {
            transition: all 0.3s ease;
        }
        
        .avatar-change-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
        }
        
        /* Character count animations */
        #usernameCount, #bioCount {
            transition: color 0.3s ease;
        }
        
        /* Form input focus effects */
        .form-control:focus {
            transform: translateY(-1px);
            box-shadow: 0 5px 20px rgba(99, 102, 241, 0.2) !important;
        }
        
        /* Bio preview transitions */
        #bioPreview {
            transition: all 0.3s ease;
        }
        
        /* Task notification glow */
        .task-notification {
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
            to { box-shadow: 0 0 20px rgba(16, 185, 129, 0.8); }
        }
        
        /* Loading spinner */
        .spinner-border {
            vertical-align: middle;
        }
    `;
    document.head.appendChild(style);
    
    // Add live character count update for bio
    if (bioInput) {
        bioInput.addEventListener('keyup', function() {
            const length = this.value.length;
            const remaining = 500 - length;
            
            if (remaining < 0) {
                this.value = this.value.substring(0, 500);
                return;
            }
            
            const bioCount = document.getElementById('bioCount');
            if (bioCount) {
                bioCount.textContent = length;
                
                // Change color based on remaining characters
                if (remaining < 50) {
                    bioCount.classList.add('text-warning');
                    bioCount.classList.remove('text-success', 'text-danger');
                } else if (remaining < 20) {
                    bioCount.classList.add('text-danger');
                    bioCount.classList.remove('text-success', 'text-warning');
                } else {
                    bioCount.classList.add('text-success');
                    bioCount.classList.remove('text-warning', 'text-danger');
                }
            }
        });
    }
    
    // Add tooltip for avatar upload
    const avatarUpload = document.querySelector('.avatar-upload');
    if (avatarUpload) {
        const tooltip = document.createElement('div');
        tooltip.className = 'avatar-tooltip';
        tooltip.style.cssText = `
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 100;
        `;
        tooltip.textContent = 'Click to change avatar';
        avatarUpload.appendChild(tooltip);
        
        avatarUpload.addEventListener('mouseenter', () => {
            tooltip.style.opacity = '1';
        });
        
        avatarUpload.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    }
    
    // Add copy email feature
    const emailInput = document.querySelector('input[type="email"]');
    if (emailInput) {
        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'btn btn-sm btn-outline-info ms-2';
        copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
        copyBtn.title = 'Copy email to clipboard';
        
        copyBtn.addEventListener('click', () => {
            emailInput.select();
            document.execCommand('copy');
            
            // Show copied feedback
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i>';
            copyBtn.classList.remove('btn-outline-info');
            copyBtn.classList.add('btn-success');
            
            setTimeout(() => {
                copyBtn.innerHTML = originalHTML;
                copyBtn.classList.remove('btn-success');
                copyBtn.classList.add('btn-outline-info');
            }, 2000);
        });
        
        emailInput.parentNode.appendChild(copyBtn);
    }
    
    // Add bio formatting helper buttons
    const bioContainer = document.querySelector('textarea[name="bio"]').parentNode;
    const formatButtons = document.createElement('div');
    formatButtons.className = 'd-flex gap-1 mb-2';
    formatButtons.style.flexWrap = 'wrap';
    
    const formattingOptions = [
        { icon: 'fas fa-bold', action: () => formatText('**', '**'), title: 'Bold' },
        { icon: 'fas fa-italic', action: () => formatText('*', '*'), title: 'Italic' },
        { icon: 'fas fa-link', action: () => formatText('[', '](url)'), title: 'Link' },
        { icon: 'fas fa-at', action: () => insertText('@'), title: 'Mention' },
        { icon: 'fas fa-hashtag', action: () => insertText('#'), title: 'Hashtag' },
        { icon: 'fas fa-smile', action: () => insertText('😊'), title: 'Emoji' }
    ];
    
    formattingOptions.forEach(option => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.innerHTML = `<i class="${option.icon}"></i>`;
        btn.title = option.title;
        btn.addEventListener('click', option.action);
        formatButtons.appendChild(btn);
    });
    
    bioContainer.insertBefore(formatButtons, bioInput);
    
    // Text formatting functions
    function formatText(startTag, endTag) {
        const textarea = bioInput;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        
        textarea.value = textarea.value.substring(0, start) +
                        startTag + selectedText + endTag +
                        textarea.value.substring(end);
        
        // Restore cursor position
        textarea.focus();
        textarea.selectionStart = start + startTag.length;
        textarea.selectionEnd = end + startTag.length;
        
        // Trigger input event
        textarea.dispatchEvent(new Event('input'));
    }
    
    function insertText(text) {
        const textarea = bioInput;
        const start = textarea.selectionStart;
        
        textarea.value = textarea.value.substring(0, start) +
                        text +
                        textarea.value.substring(start);
        
        // Restore cursor position
        textarea.focus();
        textarea.selectionStart = start + text.length;
        textarea.selectionEnd = start + text.length;
        
        // Trigger input event
        textarea.dispatchEvent(new Event('input'));
    }
    
    // Initialize everything
    document.addEventListener('DOMContentLoaded', function() {
        // Trigger initial character count
        if (usernameInput) {
            usernameInput.dispatchEvent(new Event('input'));
        }
        
        if (bioInput) {
            bioInput.dispatchEvent(new Event('input'));
        }
        
        // Add coin balance check
        checkCoinBalance();
    });
    
    // Check and display coin balance
    function checkCoinBalance() {
        fetch('get_user_coins.php')
            .then(r => r.json())
            .then(data => {
                const coinElements = document.querySelectorAll('.coins-badge, .coin-display');
                coinElements.forEach(el => {
                    el.innerHTML = el.innerHTML.replace(/\d+/, data.coins);
                });
                
                // Show coin earning tip if balance is low
                if (data.coins < 40) {
                    showCoinTip(data.coins);
                }
            })
            .catch(err => console.log('Coin check error:', err));
    }
    
    // Show coin earning tip
    function showCoinTip(coins) {
        const tip = document.createElement('div');
        tip.className = 'coin-tip';
        tip.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 10px;
            padding: 12px;
            max-width: 300px;
            z-index: 1000;
            animation: slideInRight 0.3s ease;
        `;
        
        tip.innerHTML = `
            <div class="d-flex align-items-start">
                <i class="fas fa-coins text-warning me-2 mt-1"></i>
                <div>
                    <strong>Low on coins (${coins})?</strong><br>
                    <small>Complete your profile tasks to earn more coins!</small>
                </div>
                <button class="btn-close btn-close-white ms-2" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
            <div class="mt-2">
                <a href="tasks.php" class="btn btn-warning btn-sm w-100">
                    <i class="fas fa-tasks"></i> Earn Coins
                </a>
            </div>
        `;
        
        document.body.appendChild(tip);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (tip.parentNode) {
                tip.remove();
            }
        }, 10000);
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S or Cmd+S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            document.getElementById('profileForm').dispatchEvent(new Event('submit'));
        }
        
        // Esc to close notifications
        if (e.key === 'Escape') {
            const notifications = document.querySelectorAll('.task-notification, .coin-tip, .form-error');
            notifications.forEach(notif => notif.remove());
        }
    });
</script>
</body>
</html>