<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Sign up - Codex</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/fonts.css">
</head>

<body>
  <!-- Navigation bar + Header -->
  <header id="site-header">
    <nav id="navbar" class="navbar">
      <div class="nav-brand">
        <a href="../index.html" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; color: inherit;">
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
    <!-- Signup Section -->
    <section id="signup" class="signup">
      <div class="signup-tag">SIGN UP</div>
      
      <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
          <?php
          $error = $_GET['error'];
          if ($error == 'empty_fields') echo 'Please fill in all fields.';
          elseif ($error == 'invalid_email') echo 'Please enter a valid email address.';
          elseif ($error == 'invalid_account_type') echo 'Please select a valid account type.';
          elseif ($error == 'email_exists') echo 'This email is already registered.';
          elseif ($error == 'registration_failed') echo 'Registration failed. Please try again.';
          else echo 'An error occurred. Please try again.';
          ?>
        </div>
      <?php endif; ?>
      
      <div class="signup-form-card">
        <form class="signup-form" method="POST" action="signup_process.php">
          <div class="form-row">
            <div class="form-group">
              <label for="signup-name">Name</label>
              <input type="text" id="signup-name" name="name" placeholder="Your name" required>
            </div>
            
            <div class="form-group">
              <label for="signup-email">Email</label>
              <input type="email" id="signup-email" name="email" placeholder="email@example.com" required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="account-type">Select account</label>
            <select id="account-type" name="account_type" required>
              <option value="">Select...</option>
              <option value="student">Student</option>
              <option value="teacher">Teacher</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="course-selection">Select course(s)</label>
            <select id="course-selection" name="course_selection" required>
              <option value="">Select...</option>
              <option value="networks">Networks</option>
              <option value="data-structures">Data structure & Algorithms</option>
              <option value="web-dev">Professional Web Development</option>
              <option value="software-eng">Software Engineering</option>
              <option value="javascript">Javascript</option>
              <option value="python">Python</option>
            </select>
          </div>
          
          <button type="submit" class="btn-signup-form">Sign up</button>
        </form>
      </div>
    </section>
  </main>

  <script src="../js/app.js" defer></script>
</body>
</html>
