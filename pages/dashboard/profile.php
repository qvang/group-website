<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?error=login_required");
    exit();
}

// Check if user is approved
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'approved') {
    header("Location: ../login.php?error=pending_approval");
    exit();
}

// Only students and teachers can access profile (not admins)
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'admin') {
    header("Location: admin_dashboard.php?error=unauthorized");
    exit();
}

require_once '../../config/db_connection.php';

// Get user information from database
$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, student_id, name, email, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    header("Location: " . ($_SESSION['account_type'] === 'teacher' ? 'teacher_dashboard.php' : 'dashboard.php') . "?error=user_not_found");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();
closeDBConnection($conn);

// Determine redirect URL based on account type
$dashboard_url = $_SESSION['account_type'] === 'teacher' ? 'teacher_dashboard.php' : 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Codex</title>
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
</head>
<body>
    <!-- Navigation bar + Header -->
    <header id="site-header">
        <nav id="navbar" class="navbar">
            <div class="nav-brand">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: inherit; cursor: default;">
                    <div class="logo-icon">C</div>
                    <span class="logo-text">Codex</span>
                </div>
            </div>
            <ul class="nav-links">
                <li><a href='<?php echo $dashboard_url; ?>'>Dashboard</a></li>
                <li><a href='#'>Quiz</a></li>
                <li><a href='profile.php' class="nav-active">Profile</a></li>
                <li><a href='../logout.php'>Log out</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main content -->
    <main>
        <section class="dashboard">
            <div class="dashboard-container">
                <h1 class="dashboard-title">Profile</h1>
                
                <!-- Profile Information -->
                <div class="profile-section" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px;">
                    <h2 class="section-heading" style="margin-top: 0; margin-bottom: 1.5rem;">Account Information</h2>
                    <div class="profile-details" style="display: grid; gap: 1.5rem;">
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Name</strong>
                            <span style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['name']); ?></span>
                        </div>
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Email</strong>
                            <span style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">ID</strong>
                            <span style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['student_id']); ?></span>
                        </div>
                        <div class="detail-row" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <strong style="font-size: 0.875rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Password</strong>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="font-size: 1.1rem; color: #333; font-family: monospace; letter-spacing: 2px;">••••••••</span>
                                <button type="button" class="btn-change-password" style="padding: 0.5rem 1rem; background-color: #f5f5f5; color: #333; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; font-family: inherit;">Change</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="../../js/app.js" defer></script>
</body>
</html>
