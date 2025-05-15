# LiteSupabase

LiteSupabase is a lightweight alternative to Supabase for modern web applications. Build scalable applications with authentication, database, storage and Restful APIs.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-%3E%3D5.7-blue.svg)](https://www.mysql.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

## Features

- **Authentication**
  - User signup and login
  - JWT-based authentication
  - OAuth support (Google, GitHub)
  - Token refresh mechanism
  - Password reset functionality

- **Database Management**
  - Secure data access
  - Admin dashboard interface

- **File Storage**
  - File upload and download
  - Storage management

- **Restful APIs**
  - Restful api document 

## Requirements

- PHP >= 8.1
- MySQL >= 5.7
- Composer

## Quick Start

1. Clone the repository:
```bash
git clone https://github.com/cloudkoonly/litesupabase.git
cd litesupabase
```

2. Install PHP dependencies:
```bash
composer install
```

3. Configure your environment:
```bash
cp .env.example .env
# Edit .env with your database credentials and other settings
```

4. Set up the database:
```bash
# Connect to your MySQL server and create the database
mysql -u your_username -p
CREATE DATABASE litesupabase;

# Import the database schema
mysql -u your_username -p litesupabase < db.sql
```

5. Configure your web server (Apache/Nginx) to point to the `public` directory.

```nginx
server {
    listen  80;
    server_name  your_domain;
    root   your_web_path/litesupabase/public;
   
    location / {
        index index.html index.php;
        try_files $uri  /index.php$is_args$args;
    }
    
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_pass  127.0.0.1:9000;
    }

    location ~ /\. {
        deny  all;
    }
}

```

## Project Structure

```
litesupabase/
├── config/                 # Configuration files
├── public/                # Public assets and API entry point
│   └── docs/             # API documentation
├── src/                   # Source code
│   ├── Admin/           # Admin dashboard components
│   ├── Auth/            # Authentication components
│   ├── Database/        # Database management
│   ├── Storage/         # File storage components
│   └── Controllers/     # API controllers
└── vendor/                # Composer dependencies
```

## API Endpoints

### Authentication
- `POST /api/auth/signup` - Create new user account
- `POST /api/auth/login` - User login
- `POST /api/auth/refresh` - Refresh access token
- `POST /api/auth/forgot` - Request password reset
- `GET /api/auth/config` - Get auth configuration
- `GET /api/auth/google/callback` - Google OAuth callback
- `GET /api/auth/github/callback` - GitHub OAuth callback

### Storage
- `GET /api/storage/buckets` - List buckets
- `POST /api/storage/buckets` - Create bucket
- `GET /api/storage/buckets/{bucket}/files` - List files
- `POST /api/storage/buckets/{bucket}/files` - Upload file
- `GET /api/storage/buckets/{bucket}/files/{path}` - Download file
- `DELETE /api/storage/buckets/{bucket}/files/{path}` - Delete file

## Security

- All API endpoints (except authentication) require a valid JWT token
- OAuth 2.0 integration for secure third-party authentication
- File storage with bucket-level access control
- Passwords are securely hashed
- CORS protection for web security

## License

This project is licensed under the MIT License - see the LICENSE file for details.
