# PAFFP Website

A web-based platform for managing PAFFP verification workflows, beneficiary records, payout events, and administrative operations.

## Overview

The PAFFP Website provides a centralized platform for managing records, reviewing submitted information, monitoring verification activities, and administering payout events.

It includes role-based access control, optional user authentication, administrative tools, and a responsive interface.

## Features

### Verification Management
- Manage and review verification records.
- Monitor verification activities.
- Support responsible accounts and reviewer accounts.
- Verification status management.
- Verifier communication and chat interface.
- Live monitoring functionality.

### User Authentication
- Optional login and registration system.
- Configurable authentication activation.
- Username and password authentication.
- Account verification.
- Invitation-based account registration.
- Role-based access permissions.

### Administration
- Admin and Superadmin accounts.
- User and account management.
- Administrative access controls.
- Payout event management.
- Backup and restore functionality.
- Administrative activity monitoring.

### Payout Management
- Create and manage payout events.
- Update payout information.
- Track payout-related records.
- Manage payout operations through administrative controls.

### Interface
- Responsive interface.
- Light and dark modes.
- Search and pagination.
- User-friendly dashboards.
- Verification and administrative views.

## Technology Stack

- **Backend:** PHP
- **Frontend:** HTML5, CSS3, JavaScript
- **Data Storage:** JSON-based file storage
- **Authentication:** PHP Sessions and password hashing
- **Deployment:** Apache/Nginx-compatible PHP hosting

## Requirements

- PHP 7.4 or later (use a currently supported PHP version where possible).
- Apache or Nginx web server.
- PHP sessions enabled.
- Write permissions for the application's data directory.
- Required PHP extensions used by the application.

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/YOUR-USERNAME/YOUR-REPOSITORY.git
```

### 2. Navigate to the project directory

```bash
cd YOUR-REPOSITORY
```

### 3. Configure the web server

Configure your Apache or Nginx document root to point to the project directory.

### 4. Configure permissions

Ensure that the PHP process can write to the application's required data directories.

Example for Linux:

```bash
sudo chown -R www-data:www-data data
sudo chmod -R 750 data
```

Adjust the commands according to your hosting environment.

### 5. Configure the application

Review the configuration files before running the website.

Make sure that:
- Required directories exist.
- Data files are initialized correctly.
- Application permissions are configured.
- HTTPS is enabled for production.

### 6. Open the website

Visit your configured domain or local development URL.

## Running Locally

For development purposes, you can use PHP's built-in web server:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000
```

Note: PHP's built-in server is intended for development, not production hosting.

## User Roles

The application supports different account roles and permissions.

| Role | Description |
|---|---|
| User | Accesses permitted user-facing features. |
| Responsible Account | Handles assigned verification responsibilities. |
| Reviewer | Reviews verification records. |
| Admin | Manages authorized administrative operations. |
| Superadmin | Has elevated administrative privileges. |

Actual permissions depend on the application's configured access controls.

## Security Considerations

Before deploying to production:

- Use HTTPS.
- Use strong passwords.
- Keep PHP and server software updated.
- Restrict direct public access to the `data/` directory.
- Never commit passwords, account databases, or private configuration files.
- Keep backups in a secure location.
- Restrict access to backup and restore operations.
- Configure server-side login rate limiting.
- Review file and directory permissions.
- Protect administrative accounts with strong credentials.

**Important:** JSON-based storage requires persistent server storage. Do not rely on temporary serverless filesystems for permanent application data.

## Deployment

The application is designed for traditional PHP hosting or a VPS with persistent storage.

Recommended deployment environments include:
- PHP-compatible shared hosting.
- VPS hosting with Apache or Nginx.
- Docker-based PHP hosting.

Vercel deployment requires additional work, particularly migrating persistent JSON storage to a database or external storage service.

## Project Structure

The application contains PHP pages, authentication libraries, frontend assets, and data-storage directories.

```text
PAFFP-Website/
├── index.php
├── admin.php
├── verify.php
├── auth-lib.php
├── data/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
└── README.md
```

*This is an illustrative structure. Refer to the actual repository for the complete file and directory layout.*

## Backup and Recovery

Regularly back up the application's persistent data.

Store backups outside the public web directory and ensure that only authorized administrators can access them.

Test the restoration process periodically.

## Contributing

Contributions, bug reports, and suggestions are welcome.

1. Fork the repository.
2. Create a feature branch.
3. Make your changes.
4. Test your changes.
5. Submit a pull request.

## License

Specify the license governing this project before making the repository public.

If no license is provided, all rights remain reserved by the copyright holder.

## Disclaimer

This project is intended to support PAFFP administrative and verification workflows. Deployers are responsible for securing the application, protecting personal information, maintaining backups, and complying with applicable data protection requirements.
