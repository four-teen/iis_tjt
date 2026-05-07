# IIS-TJT Movers

Clean PHP foundation for rebuilding the TJT Movers management system.

## Local Setup

1. Copy `.env.example` to `.env`.
2. Update the database values in `.env`.
3. Run the database setup from this folder:

```bash
php database/setup.php
```

The setup creates the configured database, creates the account tables, and seeds the default administrator.

## First Login

- URL: `/login.php`
- Username: value of `DEFAULT_ADMIN_USERNAME` in `.env`
- Password: value of `DEFAULT_ADMIN_PASSWORD` in `.env`

Current suggested local default:

- Username: `admin`
- Password: `TJTAdmin@2026!`

Change the default administrator password after first login.

## Structure

- `index.php` - entry redirect
- `login.php` - login form and database authentication
- `logout.php` - session logout
- `administrator/` - protected admin pages
- `includes/` - app bootstrap and authentication helpers
- `partials/` - reusable layout pieces
- `config/` - app settings
- `database/` - schema and setup script
- `assets/` - clean CSS and JavaScript

Do not commit the real `.env` file to GitHub. Commit `.env.example` instead.
