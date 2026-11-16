# DTLive Admin Panel — Railway Deployment Guide

This repository is pre-configured to deploy the **DTLive v2.1 Laravel Admin Panel** (`admin_panel/`) on **Railway** using the root `Dockerfile` and `railway.json`.

## Repository Structure

- `admin_panel/` — Laravel 10 PHP backend & admin panel (cleaned for Git + Railway)
- `database/DTLive_db_23_12_2025.sql` — Initial MySQL database dump
- `Dockerfile` — PHP 8.2 + Apache + required Laravel extensions + `$PORT` binding
- `railway.json` — Forces Railway to build via `Dockerfile`

## 1. Deploy on Railway

1. Connect this GitHub repository to a new **Railway Project**.
2. In the same Railway project, add a **MySQL** database service (`+ Create` -> `Database` -> `Add MySQL`).
3. On your App service, go to **Settings -> Networking -> Generate Domain** to get your `https://<app>.up.railway.app` URL.

## 2. Import Database

Use the MySQL public connection details from your Railway MySQL service (`Variables` / `Connect` tab):

```bash
mysql -h <MYSQLHOST> -P <MYSQLPORT> -u root -p<MYSQLPASSWORD> <MYSQLDATABASE> < database/DTLive_db_23_12_2025.sql
```

## 3. Set Railway Environment Variables

Add these variables in your Railway App Service **Variables** tab:

```env
APP_NAME=DTLive
APP_ENV=production
APP_KEY=base64:SgRXHQuxu3fBwBn14NId4cY/8QO45AZ3VuqgeYgwi2c=
APP_DEBUG=false
APP_URL=https://<your-app>.up.railway.app
WEB_URL=https://<your-app>.up.railway.app

API_TOKEN=DTLdcFLx12g5MIGu7deMJIJQTD56qcZx
PAGE_LIMIT=10
DEMO_MODE=OFF
VERSION=2.1

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

FILESYSTEM_DRIVER=local
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

> Note: Replace `APP_KEY` and `API_TOKEN` with your own generated secrets for production, and update `Mainfiles_DTLive/Appcode` in the Flutter app with your Railway domain and `API_TOKEN`.

## 4. Persistent Media Storage (Recommended)

To prevent uploaded posters, thumbnails, and media from resetting on redeploy:

- **Option A (Railway Volume):** Attach a Volume to your App Service with mount path `/var/www/html/storage/app/public`.
- **Option B (S3 / R2):** Configure S3 or Wasabi credentials from the Admin Panel storage settings.
