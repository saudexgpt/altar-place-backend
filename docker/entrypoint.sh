#!/bin/sh
set -e

cd /var/www/html

# A Job component (e.g. the pre-deploy migration) passes its own command
# here — run that and exit, instead of starting the whole web stack, which
# would never return and hang the deploy.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# Config/route caching must happen at container start, not image build time —
# App Platform injects env vars (DB creds, APP_KEY, Spaces keys, ...) at
# runtime, so caching them into the image itself would bake in empty values.
php artisan config:cache
php artisan route:cache
php artisan event:cache

# No-op (and safe to ignore failures on) when the 'public' disk is
# S3-backed — only relevant when FILESYSTEM_DISK_PUBLIC is left as 'local'.
php artisan storage:link || true

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
