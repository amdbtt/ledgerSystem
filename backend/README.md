# Ledger PHP API

PHP 8 + MySQL/MariaDB backend for the React ERP UI.

## Requirements

- PHP 8.2+ (PDO MySQL)
- MySQL or MariaDB on port `3305` (configurable in `.env`)

## Setup

```bash
cd backend
cp .env.example .env   # already configured for 127.0.0.1:3305 / ledger_erp
php bin/migrate.php
./bin/serve.sh
```

API: `http://localhost:8888`

Default admin: `admin@admin.com` / `admin123`

## Security notes

- Passwords hashed with `password_hash` (bcrypt)
- JWT HS256 with secret from `.env` (never commit real secrets)
- PDO prepared statements only
- CORS locked to `CORS_ORIGIN`
- Uploads: MIME allow-list, size limit, random file names
- Soft deletes (`removed = 1`)
- Password-reset tokens stored hashed; responses avoid email enumeration when mail is enabled

## Frontend

The React app still uses local dummy data. Next step is reconnecting `frontend/src/config/serverApiConfig.js` and `request.js` to this API.
