<?php
require_once 'config.php';
require_once 'utils.php';
requireLogin();

$user_id = getCurrentUserId();
$username = getCurrentUsername();
$conn = getDBConnection();

$success = '';
$error = '';

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = 'Password changed successfully.';
            logActivity($user_id, 'PASSWORD_CHANGE', null, 'Password updated');
        } else {
            $error = 'Failed to change password.';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            $error = 'Email already in use.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            if ($stmt->execute([$email, $user_id])) {
                $success = 'Profile updated successfully.';
                $user['email'] = $email;
                logActivity($user_id, 'PROFILE_UPDATE', null, 'Email updated');
            } else {
                $error = 'Failed to update profile.';
            }
        }
    }
}

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM files WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) as total_dirs FROM directories WHERE user_id = ?");
$stmt->execute([$user_id]);
$dir_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) as total_shares FROM shared_links WHERE user_id = ?");
$stmt->execute([$user_id]);
$share_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activities
$activities = getRecentActivities($user_id, 50);

$storage_limit = getUserStorageLimit($user_id);
$storage_limit_formatted = getStorageLimitFormatted($storage_limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - FTP File Manager</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .profile-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .profile-card h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-action {
            font-weight: 600;
            color: #667eea;
        }
        .activity-time {
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>👤 User Profile</h1>
            </div>
            <div class="header-right">
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn btn-secondary">🛡️ Admin Panel</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">My Files</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>📄 Total Files</h3>
                    <p class="stat-number"><?php echo $stats['total_files']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>💾 Storage Used</h3>
                    <p class="stat-number"><?php echo formatBytes($stats['total_size'] ?? 0); ?></p>
                    <small>of <?php echo $storage_limit_formatted; ?></small>
                </div>
                <div class="stat-card">
                    <h3>📁 Directories</h3>
                    <p class="stat-number"><?php echo $dir_stats['total_dirs']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>🔗 Shared Links</h3>
                    <p class="stat-number"><?php echo $share_stats['total_shares']; ?></p>
                </div>
            </div>

            <div class="profile-grid">
                <!-- Profile Info -->
                <div class="profile-card">
                    <h3>Profile Information</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Member Since</label>
                            <input type="text" value="<?php echo date('Y-m-d', strtotime($user['created_at'])); ?>" disabled>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="profile-card">
                    <h3>Change Password</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="file-list">
                <h3>📊 Recent Activity</h3>
                <div class="activity-list">
                    <?php if (count($activities) > 0): ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <span class="activity-action"><?php echo htmlspecialchars(str_replace('_', ' ', $activity['action'])); ?></span>
                                <?php if ($activity['original_filename']): ?>
                                    - <?php echo htmlspecialchars($activity['original_filename']); ?>
                                <?php endif; ?>
                                <?php if ($activity['details']): ?>
                                    <br><small><?php echo htmlspecialchars($activity['details']); ?></small>
                                <?php endif; ?>
                                <div class="activity-time"><?php echo date('Y-m-d H:i:s', strtotime($activity['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-message">No activity yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
