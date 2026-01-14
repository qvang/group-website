<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Log in - Codex</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/fonts.css">
</head>

<body>
  <!-- Navigation bar + Header -->
  <header id="site-header">
    <nav id="navbar" class="navbar">
      <div class="nav-brand">
        <a href="../index.html" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
          <div class="logo-icon">C</div>
          <span class="logo-text">Codex</span>
        </a>
      </div>
      <ul class="nav-links">
        <li><a href='../index.html#about-us'>About</a></li>
        <li><a href='../index.html#courses'>Courses</a></li>
        <li><a href='../index.html#contact-us'>Contact Us</a></li>
        <li><a href='login.php'>Log in</a></li>
        <li><a href='signup.php' class="btn-signup">Sign up</a></li>
      </ul>
    </nav>
  </header>

  <!-- Main content -->
  <main>
    <!-- Login Section -->
    <section id="login" class="login">
      <div class="login-tag">LOG IN</div>
      
      <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
          <?php
          $error = $_GET['error'];
          if ($error == 'empty_fields') echo 'Please fill in all fields.';
          elseif ($error == 'invalid_id') echo 'Invalid ID format. Please enter an 8-digit ID.';
          elseif ($error == 'invalid_credentials') echo 'Invalid ID or password.';
          elseif ($error == 'pending_approval') echo 'Your account is pending approval. Please wait for an administrator or teacher to approve your account.';
          else echo 'An error occurred. Please try again.';
          ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['signup'])): ?>
        <div class="success-message">
          Registration successful! 
          <?php 
          $account_type = isset($_GET['account_type']) ? $_GET['account_type'] : 'student';
          $id_label = $account_type === 'teacher' ? 'Teacher ID' : 'Student ID';
          $id_value = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : (isset($_GET['student_id']) ? $_GET['student_id'] : '');
          ?>
          Your <?php echo htmlspecialchars($id_label); ?>: <?php echo htmlspecialchars($id_value); ?><br>
          Temporary Password: <?php echo htmlspecialchars($_GET['temp_password']); ?><br>
          Please log in and change your password.
        </div>
      <?php endif; ?>
      
      <div class="login-form-card">
        <form class="login-form" method="POST" action="login_process.php">
          <div class="form-group">
            <label for="login-id">ID</label>
            <input type="number" id="login-id" name="id" placeholder="Enter 8 digit ID here..." min="0" max="99999999" required>
          </div>
          
          <div class="form-group">
            <label for="login-password">Password</label>
            <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
          </div>
          
          <button type="submit" class="btn-signup-form">Log in</button>
        </form>
      </div>
    </section>
  </main>

  <script src="../js/app.js" defer></script>
</body>
</html>
