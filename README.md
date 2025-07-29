# LiteSupabase

`LiteSupabase` is a lightweight, open-source alternative to Supabase, built with PHP and the Slim Framework. It aims to provide developers with a self-hostable, high-performance Backend-as-a-Service (BaaS) platform, including core features like authentication, database management, and file storage.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-blue.svg)](https://php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-%3E%3D5.7-blue.svg)](https://www.mysql.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

## ✨ Features

- **User Authentication**
  - Email and password registration/login.
  - Stateless authentication using JWT (JSON Web Tokens).
  - OAuth support for Google and GitHub.
  - Access token refresh and password reset functionality.

- **Admin Dashboard**
  - An intuitive dashboard to manage users, databases, and storage.
  - View and manage all registered users.
  - Configure third-party authentication credentials.
  - Provides API documentation and debugging tools.

- **Database Management**
  - Manage database tables directly from the admin panel.

- **File Storage**
  - Functionality for file uploads, downloads, and management.
  - (Note: Storage-related API endpoints are currently under development).

## 🚀 Quick Start

### Requirements
- PHP >= 8.2
- MySQL >= 5.7
- Composer

### Installation Steps

1.  **Clone the Repository**
    ```bash
    git clone https://github.com/cloudkoonly/litesupabase.git
    cd litesupabase
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    ```

3.  **Configure Environment Variables**
    Copy the example environment file and modify it for your setup.
    ```bash
    cp .env.example .env
    ```
    You will need to edit the following settings in your `.env` file:
    - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
    - `JWT_SECRET` (a secure, random string)
    - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (for Google login)
    - `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` (for GitHub login)

4.  **Initialize the Database**
    Log in to your MySQL server, create a database, and import the `db.sql` schema.
    ```bash
    # Log in to MySQL
    mysql -u your_username -p

    # Create the database
    CREATE DATABASE litesupabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    # Import the table schema
    mysql -u your_username -p litesupabase < db.sql
    ```

5.  **Configure Your Web Server**
    Point your web server's (e.g., Nginx or Apache) document root to the project's `public` directory. Here is an example Nginx configuration:

    ```nginx
    server {
        listen 80;
        server_name your_domain.com;
        root /path/to/your/litesupabase/public;

        index index.php index.html;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            try_files $uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass 127.0.0.1:9000; # Or your PHP-FPM socket
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }

        location ~ /\.ht {
            deny all;
        }
    }
    ```

## 🔑 Admin Panel

- **URL**: `http://your_domain.com/admin`
- **Default Email**: `admin@litesupabase.com`
- **Default Password**: `123456`

**Important**: Please change the default admin password immediately after your first login!

## 📖 API Endpoints

All API endpoints are prefixed with `/api`.

### Auth Endpoints (`/api/auth`)

- `POST /signup`: Register a new user
- `POST /login`: Log in a user
- `POST /logout`: Log out a user (requires authentication)
- `POST /refresh`: Refresh an access token
- `POST /forgot`: Request a password reset
- `GET /user`: Get the current user's information (requires authentication)
- `GET /config`: Get public authentication configuration

### Third-Party Login Callbacks

- `POST /google/callback`: Google login callback
- `POST /github/callback`: GitHub login callback

## 🤝 Contributing

Contributions of all kinds are welcome! Please feel free to open a GitHub Issue or Pull Request to report bugs, suggest features, or contribute code.

## 📄 License

This project is licensed under the [MIT](LICENSE) License.
