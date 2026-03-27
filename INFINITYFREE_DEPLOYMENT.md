# InfinityFree Deployment

## 1. Database setup

Create a MySQL database from the InfinityFree control panel, then update `.env` with values like:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.infinityfreeapp.com

DB_CONNECTION=mysql
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_12345678_accounting
DB_USERNAME=if0_12345678
DB_PASSWORD=your_mysql_password
```

## 2. Build the project locally

InfinityFree does not provide Composer/Artisan shell access on free hosting, so prepare the project locally first:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

On Windows, you can automate most of this and create a ready-to-upload bundle with:

```powershell
pwsh -File .\prepare-infinityfree.ps1 -RunComposer
```

If the project uses Vite-built assets, use:

```powershell
pwsh -File .\prepare-infinityfree.ps1 -RunComposer -BuildAssets
```

This creates a prepared upload bundle inside `.deployment/infinityfree`.

If your InfinityFree database allows external MySQL access, you can then run:

```bash
php artisan migrate --force
```

If external MySQL access is blocked, create the tables by importing SQL through phpMyAdmin instead of running remote migrations from Artisan.

If your views require Vite assets, run:

```bash
npm install
npm run build
```

## 3. Upload structure

Upload the Laravel application folder outside `htdocs`, for example as `laravel_accounting`.

Then upload the contents of `infinityfree/htdocs/` into the InfinityFree `htdocs/` directory.

Final layout should look like:

```text
/
|-- htdocs/
|   |-- .htaccess
|   `-- index.php
`-- laravel_accounting/
    |-- app/
    |-- bootstrap/
    |-- config/
    |-- public/
    |-- storage/
    |-- vendor/
    `-- .env
```

The included `htdocs/index.php` file already points to a sibling folder named `laravel_accounting`.
If you upload the app under another name, update this line accordingly:

```php
$appRoot = realpath(__DIR__.'/../laravel_accounting');
```

## 4. Required writable directories

Ensure these directories are writable on the server:

- `storage/`
- `bootstrap/cache/`

## 5. Important notes

- Do not use SQLite on InfinityFree for this project.
- Upload the `vendor/` folder because Composer is usually not available on the server.
- On free InfinityFree plans, remote MySQL access may be blocked. In that case, use phpMyAdmin to import the schema instead of relying on `php artisan migrate` against the hosted database.
- If you change `.env`, clear Laravel caches locally and upload the updated files again.
