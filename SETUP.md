# Codex PHP/MySQL Setup Instructions

## Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB)
- Web server (Apache/Nginx) with PHP support

## Setup Steps

### 1. Database Setup

1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or command line)
2. Run the SQL schema file to create the database and tables:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
   Or import `sql/schema.sql` through phpMyAdmin

### 2. Database Configuration

1. Open `config/db_connection.php`
2. Update the database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');  // Change this
   define('DB_PASS', 'your_password');   // Change this
   define('DB_NAME', 'codex_db');        // Change if needed
   ```

### 2.5. Admin Account

A default admin account is created in the database schema:
- **Student ID:** 99999999
- **Password:** admin123 (or check the schema.sql file for the actual default)

**Important:** Change the admin password after first login for security. You can create a new password hash using:
```bash
php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
```

Then update the password in the database:
```sql
UPDATE users SET password = 'your_new_hash' WHERE student_id = 99999999;
```

### 3. File Renaming

Rename the following files from `.html` to `.php`:
- `pages/login.html` → `pages/login.php`
- `pages/signup.html` → `pages/signup.php`

Update the form actions in these files:
- In `login.php`: Change `action="login_process.php"` if needed
- In `signup.php`: Change `action="signup_process.php"` if needed

### 4. Update Navigation Links

Update all navigation links that point to login/signup:
- Change `login.html` to `login.php`
- Change `signup.html` to `signup.php`

Files to update:
- `index.html` (navbar)
- `pages/login.php` (navbar)
- `pages/signup.php` (navbar)
- Any other pages with navigation

### 5. File Permissions

Ensure PHP files have proper permissions:
```bash
chmod 644 *.php
chmod 644 config/*.php
```

### 6. Security Considerations

**Important Security Steps:**

1. **Move config file outside web root** (recommended):
   - Move `config/db_connection.php` to a directory outside your web root
   - Update the `require_once` paths in PHP files

2. **Create `.htaccess` file** (if using Apache):
   ```apache
   # Protect config directory
   <FilesMatch "\.php$">
     Order Allow,Deny
     Deny from all
   </FilesMatch>
   ```

3. **Update database credentials** - Never commit real credentials to version control

4. **Enable HTTPS** - Use SSL/TLS for production

### 7. Testing

1. Start your web server
2. Navigate to `http://localhost/pages/signup.php`
3. Create a test account
4. Note the generated Student ID and temporary password
5. Log in at `http://localhost/pages/login.php`

### 8. Features Implemented

- ✅ User registration with email validation
- ✅ Automatic 8-digit Student ID generation
- ✅ Temporary password generation
- ✅ Secure password hashing (bcrypt)
- ✅ Login authentication
- ✅ Session management
- ✅ Course enrollment
- ✅ Error handling and user feedback
- ✅ SQL injection prevention (prepared statements)
- ✅ Logout functionality

### 9. Next Steps (Optional Enhancements)

- Add password reset functionality
- Email verification
- Password change on first login
- Remember me functionality
- User dashboard page
- ✅ Admin panel for user management (IMPLEMENTED)
- Email notifications for registration

## Troubleshooting

**Connection Error:**
- Check database credentials in `config/db_connection.php`
- Ensure MySQL service is running
- Verify database exists: `SHOW DATABASES;`

**File Not Found:**
- Check file paths in `require_once` statements
- Verify file permissions

**Session Issues:**
- Ensure `session_start()` is called before any output
- Check PHP session configuration

## Support

For issues or questions, check:
- PHP error logs
- MySQL error logs
- Browser console for JavaScript errors
