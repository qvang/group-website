# How to run the website.

Prerequisites:
- You are using XAMPP control panel with Apache, mySQL and phpMyAdmin.
- The group-website folder is saved in xampp\htdocs.

## Accessing the homepage and adding the database
1. Open XAMPP control panel and start Apache and MySQL.
2. Go to `http://localhost/phpmyadmin` and import schema.sql.
3. On your web browser, go to `http://localhost/group-website/`.

## Creating an admin account
To properly generate an admin account, you must go to `http://localhost/group-website/setup_admin.php`.
This script will generate an admin account with the user credentials:
ID: 99999999
Password: admin123

## Creating student/teacher accounts
1. You can either click sign-up from the homepage or navigate to 
`http://localhost/group-website/signup.php`.
2. Once you signup, save the user credentials to use when approved.

## Approving accounts
1. Login using the admin account.
2. Once logged in, you will be redirected to the admin dashboard where you can approve/reject applications.

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



