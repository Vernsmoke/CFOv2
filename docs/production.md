# Production setup

Serve the application's `public/` directory. Copy `.env.example` to `.env` and set
the production `APP_URL`, database host, database name, dedicated database user,
database password, and SMTP settings. Generate `APP_KEY` on the target deployment.

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate --force
php artisan migrate --force
```

The migrations create the application's tables, including database sessions,
cache, cache locks, jobs, job batches, and failed jobs. `CACHE_STORE` takes
precedence over `CACHE_DRIVER`; the example sets both to `database`.

To create the first administrator, set `CFO_DEFAULT_ADMIN_EMAIL` and
`CFO_DEFAULT_ADMIN_PASSWORD`, then run `php artisan db:seed --force`. There is no
default administrator login. Repeated seeding preserves an existing admin account.

Run `php artisan config:cache`, `php artisan route:cache`, and
`php artisan view:cache` after configuring the environment. Run
`php artisan queue:work` under your process manager for the database queue.

Student QR links use `APP_URL`, with an optional `CFO_ACCESS_URL` override. Set
`TRUSTED_PROXIES` to the IP addresses or CIDRs of your deployment proxies.
`CFO_LIVE_TRANSPORT` defaults to `sse`; set it to `polling` if the proxy buffers
event streams. Upload storage uses `FILESYSTEM_UPLOAD_DISK=local` or `s3`; supply
the AWS settings when using S3.
