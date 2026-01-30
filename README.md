# FTP-Like File Manager System

A web-based file management system built with PHP and MySQL that mimics FTP functionality. Users can upload, download, organize files in directories, and manage their personal storage space.

## Features

- **User Authentication**: Secure registration and login system
- **File Upload**: Upload files with size and type restrictions
- **File Download**: Download files with proper content headers
- **File Management**: Delete unwanted files
- **Directory Management**: Create and delete folders, navigate through directory structure
- **Responsive Design**: Mobile-friendly interface
- **Storage Tracking**: Monitor total storage usage
- **Breadcrumb Navigation**: Easy navigation through folder structure

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Apache/Nginx web server
- PDO PHP extension
- PHP fileinfo extension

## Installation

### 1. Clone or Download the Repository

```bash
git clone <repository-url>
cd Gge
```

### 2. Set Up the Database

Create a MySQL database and import the schema:

```bash
mysql -u root -p
```

Then in MySQL:

```sql
CREATE DATABASE ftp_system;
exit;
```

Import the database schema:

```bash
mysql -u root -p ftp_system < database.sql
```

Or import via phpMyAdmin by importing the `database.sql` file.

### 3. Configure Database Connection

Edit the [`config.php`](config.php) file and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'ftp_system');
```

### 4. Set Up File Permissions

Create the uploads directory and set proper permissions:

```bash
mkdir uploads
chmod 755 uploads
```

Or the directory will be created automatically when the first file is uploaded.

### 5. Configure Web Server

#### Apache

Ensure [`mod_rewrite`](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) is enabled and place the application in your web root (e.g., `/var/www/html/`).

#### Nginx

Configure your server block to point to the application directory:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/Gge;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Access the Application

Open your browser and navigate to:

```
http://localhost/Gge/
```

Or your configured domain.

## Default Credentials

A default admin account is created during database setup:

- **Username**: `admin`
- **Password**: `admin123`

**Important**: Change these credentials immediately after first login!

## Configuration Options

You can modify these settings in [`config.php`](config.php):

- `MAX_FILE_SIZE`: Maximum upload file size (default: 50MB)
- `ALLOWED_EXTENSIONS`: Array of allowed file extensions
- `UPLOAD_DIR`: Directory where files are stored

## File Structure

```
Gge/
├── config.php          # Database and application configuration
├── database.sql        # Database schema
├── index.php           # Main dashboard
├── login.php           # Login page
├── register.php        # Registration page
├── logout.php          # Logout handler
├── download.php        # File download handler
├── style.css           # Stylesheet
├── uploads/            # File storage directory (created automatically)
└── README.md           # This file
```

## Security Considerations

1. **Change Default Credentials**: Immediately change the default admin password
2. **File Upload Restrictions**: Only allowed file types can be uploaded
3. **File Size Limits**: Maximum file size is enforced
4. **User Isolation**: Users can only access their own files
5. **SQL Injection Protection**: Prepared statements are used throughout
6. **Password Security**: Passwords are hashed using PHP's `password_hash()`
7. **Session Management**: Secure session handling for authentication

## Usage

### Register a New Account

1. Navigate to [`register.php`](register.php)
2. Fill in username, email, and password
3. Click "Register"

### Login

1. Navigate to [`login.php`](login.php)
2. Enter username/email and password
3. Click "Login"

### Upload Files

1. Click "Upload File" button
2. Choose a file
3. Click "Upload"

### Create Folders

1. Click "Create Folder" button
2. Enter folder name
3. Click "Create"

### Navigate Folders

- Click on folder names to enter them
- Use breadcrumb navigation to go back

### Download Files

- Click the "Download" button next to any file

### Delete Files/Folders

- Click the "Delete" button next to any file or folder
- Confirm the deletion

## Troubleshooting

### Upload Issues

- Check PHP upload limits in `php.ini`:
  - `upload_max_filesize`
  - `post_max_size`
  - `max_execution_time`
- Ensure the uploads directory has write permissions

### Database Connection Issues

- Verify database credentials in [`config.php`](config.php)
- Ensure MySQL service is running
- Check if the database exists

### Permission Denied Errors

```bash
chmod 755 uploads
chown www-data:www-data uploads  # For Apache on Ubuntu/Debian
```

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## License

This project is open source and available under the [MIT License](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues and questions, please open an issue in the repository.
