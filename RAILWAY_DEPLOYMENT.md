# Railway Deployment

## Important

- Do not keep production database credentials in the repository.
- If credentials were shared in chat or logs, rotate them in Railway before going live.
- Use `APP_DEBUG=false` in production.

## Goal

This project can be deployed to Railway with real demo data inside MySQL without reseeding on every deploy.

## Recommended Railway variables

Use your normal Laravel production variables in Railway.

Example:

```env
APP_NAME="Laravel"
APP_ENV="production"
APP_DEBUG="false"
APP_URL="https://your-app.up.railway.app"

DB_CONNECTION="mysql"
DB_HOST="your-railway-host"
DB_PORT="your-railway-port"
DB_DATABASE="railway"
DB_USERNAME="root"
DB_PASSWORD="your-password"

SESSION_DRIVER="database"
QUEUE_CONNECTION="database"
CACHE_STORE="database"
```

## Safe deployment command

Instead of running `db:seed` on every deploy, use the custom command below.

It will:

- run migrations
- seed demo data only if the database is still empty
- rebuild chart of accounts links
- rebuild journal entries
- rebuild payments and inventory movements

Recommended `NIXPACKS_BUILD_CMD`:

```bash
composer install --no-dev --optimize-autoloader && npm install && npm run build && php artisan migrate --force && php artisan app:seed-demo-if-empty && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Why this is better than `db:seed --force`

- `db:seed --force` on every deploy may recreate demo records repeatedly.
- `app:seed-demo-if-empty` only runs the seed path when the operational tables are empty.
- Later deploys keep the live database untouched.

## First deploy result

On the first Railway deploy with an empty MySQL database, the app will contain:

- sample company and users
- customers and suppliers
- products
- invoices and purchases
- journal entries and lines
- payments
- inventory movements

## If you want your current local data exactly

If you want the exact same local database content instead of regenerated demo data, use a MySQL dump/import workflow:

1. export your source database as SQL
2. import it into Railway MySQL
3. deploy the app with normal `php artisan migrate --force`

That path is for exact data migration.
The custom command in this project is for automatic demo-data bootstrap.
