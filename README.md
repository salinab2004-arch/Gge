# Full-Stack FTP Server - Advanced File Manager

A professional web-based file management system built with PHP and MySQL that provides advanced FTP-like functionality with a modern, intuitive interface.

## Features Overview

### Core Features
- **User Authentication**: Secure registration and login with role-based access
- **File Upload**: Multiple file upload with drag & drop support
- **File Download**: Single and bulk download with ZIP compression
- **File Management**: Rename, move, copy, delete files
- **Directory Management**: Create, navigate, and delete folder structures
- **File Preview**: In-browser preview for images, PDFs, and text files
- **File Sharing**: Generate public links with password protection and expiration
- **Search Functionality**: Real-time file search across your storage
- **View Modes**: Toggle between grid and list views
- **Sorting Options**: Sort by name, size, date, or type
- **Storage Management**: Per-user storage limits with visual indicators
- **Activity Logging**: Comprehensive audit trail of all user actions

### Admin Features
- **User Management**: Create, modify, delete users and change roles
- **Storage Control**: Set storage limits from 1MB to unlimited per user
- **File Oversight**: View and manage files across all users
- **Activity Monitoring**: System-wide activity tracking and monitoring
- **Statistics Dashboard**: Real-time metrics for users, files, and storage
- **Role Management**: Assign admin or user roles

### Advanced Capabilities
- 📤 **Drag & Drop Upload**: Simply drag files into the browser
- 📦 **Bulk Operations**: Select multiple files for download or deletion
- 🔍 **File Search**: Quick search through all your files
- 🔗 **Public Sharing**: Share files with customizable expiration and limits
- 🔒 **Password Protection**: Secure shared links with passwords
- 👁️ **File Preview**: View images, PDFs, and text without downloading
- 📊 **Activity Logs**: Track all file operations and user activities
- 🎨 **Modern UI**: Responsive design with grid and list views
- 📈 **Storage Tracking**: Visual indicators for storage usage
- ✏️ **File Operations**: Rename, move, and copy files easily

## Requirements

- PHP 7.4 or higher (with extensions: PDO, fileinfo, zip, mbstring)
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Apache/Nginx web server
- Minimum 100MB disk space (plus user uploads)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd Gge
```

### 2. Set Up the Database

Create a MySQL database:

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

Or use phpMyAdmin to import [`database.sql`](database.sql).

### 3. Configure Database Connection

Edit [`config.php`](config.php) and update credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'ftp_system');
```

### 4. Set Up File Permissions

```bash
mkdir uploads
chmod 755 uploads
chown www-data:www-data uploads  # For Apache on Ubuntu/Debian
```

### 5. Configure PHP Settings

Update `php.ini` for larger uploads:

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
```

### 6. Access the Application

Navigate to: `http://localhost/Gge/` or your configured domain.

## Default Credentials

- **Username**: `admin`
- **Password**: `admin123`

**CRITICAL**: Change these credentials immediately after first login!

## File Structure

```
Gge/
├── config.php          # Database and app configuration
├── database.sql        # Complete database schema
├── index.php           # Main dashboard with advanced features
├── login.php           # User authentication
├── register.php        # User registration
├── logout.php          # Session termination
├── download.php        # File download handler
├── preview.php         # File preview with support for images/PDFs/text
├── share.php           # Public file sharing system
├── profile.php         # User profile and activity logs
├── admin.php           # Admin panel with full system control
├── actions.php         # File operations (rename, move, copy, bulk)
├── utils.php           # Utility functions and helpers
├── style.css           # Responsive stylesheet
├── uploads/            # File storage (auto-created)
└── README.md           # This file
```

## User Guide

### File Upload

**Method 1: Drag & Drop**
- Drag files from your computer directly into the upload area
- Supports multiple files simultaneously

**Method 2: Click to Upload**
- Click the "Upload Files" button
- Select one or multiple files
- Files upload automatically

### File Management

**Rename Files**
- Click the ✏️ (edit) icon next to any file
- Enter new filename
- Original extension is preserved

**Move Files**
- Not yet implemented in UI (backend ready via [`actions.php`](actions.php))

**Copy Files**
- Backend ready via [`copyFile()`](utils.php:82) function

**Delete Files**
- Single: Click 🗑️ button next to file
- Bulk: Select multiple files with checkboxes, click "Delete"

### File Preview

Click "👁️ Preview" to view supported file types:
- **Images**: JPG, PNG, GIF, SVG, WebP
- **Documents**: PDF files
- **Text Files**: TXT, HTML, CSS, JavaScript, JSON, XML

### File Sharing

1. Click 🔗 icon next to any file
2. Configure share settings:
   - Expiration time (hours, or never)
   - Max downloads (unlimited or specific count)
   - Password protection (optional)
3. Copy the generated link
4. Share with anyone (no account required)

**Share Link Features:**
- Password protection
- Expiration dates
- Download limits
- Access tracking

### Search Files

- Use the search box in the toolbar
- Search by filename
- Results show across all directories
- Instant filtering

### View Modes

- **List View** (📋): Traditional table layout with detailed info
- **Grid View** (⊞): Visual card layout with large icons

### Sorting

Sort files by:
- Date uploaded (newest/oldest)
- Filename (A-Z / Z-A)
- File size (largest/smallest)
- File type

### Bulk Operations

1. Select multiple files using checkboxes
2. Choose operation:
   - **Download**: Creates ZIP archive of selected files
   - **Delete**: Removes all selected files

### User Profile

Access via "👤 Profile" button:
- View storage statistics
- Update email address
- Change password
- View activity history (last 50 actions)

## Admin Guide

### Access Admin Panel

Click "🛡️ Admin Panel" (visible only to admins)

### User Management

- **View All Users**: See user details, file counts, storage usage
- **Change Roles**: Promote users to admin or demote to regular user
- **Set Storage Limits**: Configure per-user limits (1MB to unlimited)
- **Delete Users**: Remove users and all their data

### Storage Limit Management

For each user, click the ✏️ button in the Storage Limit column:
- Set custom limit in MB or GB
- Or grant unlimited storage
- Users are notified when approaching limits

### File Management

- View all files across the system
- Download any user's files
- Delete inappropriate content
- Monitor file types and sizes

### Activity Monitoring

The admin panel shows:
- All user actions in real-time
- File operations (upload, download, delete, etc.)
- Share link creation
- Login/logout events
- IP addresses for security auditing

### System Statistics

Dashboard shows:
- Total users (active accounts)
- Admin count
- Total files stored
- Total storage used
- Total directories created

## API Documentation

### File Operations API ([`actions.php`](actions.php))

All operations return JSON for AJAX requests:

**Rename File**
```php
POST actions.php?action=rename
file_id: int
new_name: string
```

**Move File**
```php
POST actions.php?action=move
file_id: int
new_directory: string
```

**Copy File**
```php
POST actions.php?action=copy
file_id: int
new_directory: string (optional)
```

**Search Files**
```php
GET actions.php?action=search&q=query&dir=/path
```

**Bulk Download**
```php
POST actions.php?action=bulk_download
file_ids[]: array of int
```

**Bulk Delete**
```php
POST actions.php?action=bulk_delete
file_ids[]: array of int
```

## Security Features

### Authentication & Authorization
- Password hashing with `password_hash()` (bcrypt)
- Role-based access control (user/admin)
- Session-based authentication
- CSRF protection ready

### File Security
- File type validation
- File size limits (configurable)
- Filename sanitization
- User isolation (users can't access others' files)
- Admin-only oversight capabilities

### Share Link Security
- Cryptographically secure tokens (64 bytes)
- Password protection option
- Expiration timestamps
- Download count limits
- One-time passwords ready for implementation

### Activity Tracking
- All file operations logged
- IP address tracking
- User action audit trail
- Admin activity monitoring

### Database Security
- Prepared statements (SQL injection protection)
- Foreign key constraints
- Indexed queries for performance
- Transaction support where needed

## Configuration

### Upload Limits ([`config.php`](config.php))

```php
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'mp4', 'mp3']);
```

### Storage Defaults

- New users: 1GB storage limit
- Admins can modify: 1MB to unlimited
- 0 = unlimited storage

## Advanced Features Explained

### Activity Logging System

Every action is logged with:
- User ID and username
- Action type (upload, download, delete, etc.)
- Affected file (if applicable)
- Additional details
- IP address
- Timestamp

Access logs via:
- User: [`profile.php`](profile.php) (personal history)
- Admin: [`admin.php`](admin.php) (system-wide monitoring)

### File Sharing System

Create shareable public links with:
- **Expiration**: Set link validity period (hours)
- **Download Limits**: Restrict number of downloads
- **Password Protection**: Require password to access
- **Access Tracking**: Monitor download counts

Share links work for:
- Anyone with the link (no account needed)
- Password protected links require correct password
- Expired links show appropriate error message

### Drag & Drop Upload

Features:
- Visual drop zone with hover effects
- Multiple file support
- Automatic upload on drop
- Progress indication
- Error handling per file

### Bulk Operations

Select multiple files to:
- Download as single ZIP archive
- Delete in one action
- See selection count
- Clear selection easily

## Database Schema

### Tables

- **users**: User accounts, roles, storage limits
- **files**: File metadata and locations
- **directories**: Folder structure
- **activity_logs**: Complete audit trail
- **shared_links**: Public sharing system
- **file_permissions**: Access control (ready for future use)

### Indexes

Optimized for:
- Username and email lookups
- File directory queries
- Activity log searches
- Share token validation

## Performance Optimization

- Indexed database queries
- Efficient file storage structure
- Lazy loading for large directories
- Cached view preferences (cookies)
- Optimized SQL queries with JOINs

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (responsive design)

## Troubleshooting

### Upload Errors

**"Storage limit exceeded"**
- Check available storage in profile
- Contact admin for limit increase
- Delete old files to free space

**"File type not allowed"**
- Check allowed extensions in config
- Admin can modify allowed types

**"Failed to upload"**
- Check PHP upload limits
- Verify directory permissions
- Check available disk space

### Share Link Issues

**"Invalid or expired share link"**
- Link may have expired
- Download limit may be reached
- Token may be incorrect

**"Password required"**
- Link creator set password protection
- Contact file owner for password

### Performance Issues

For large file lists:
- Use search to filter files
- Navigate to specific directories
- Use grid view for better performance
- Contact admin to archive old files

## Future Enhancements

Planned features:
- File versioning
- Trash/recycle bin
- File compression
- Thumbnail generation
- WebDAV support
- FTP protocol integration
- Real-time collaboration
- File comments
- Tags and categories

## License

This project is open source and available under the [MIT License](LICENSE).

## Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Support

For issues and questions:
- Open an issue on GitHub
- Check existing issues for solutions
- Review documentation thoroughly

## Credits

Built with:
- PHP 7.4+
- MySQL/MariaDB
- Vanilla JavaScript
- Modern CSS3

## Version History

- **v2.0.0**: Added advanced features (sharing, preview, bulk ops, activity logs)
- **v1.5.0**: Added admin panel with storage management
- **v1.0.0**: Initial release with basic FTP functionality
