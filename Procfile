web: heroku-php-apache2 public/
worker: php artisan queue:work --tries=3 --sleep=3 --max-time=3600 --timeout=590
scheduler: bash -c "while true; do php artisan schedule:run --no-interaction; sleep 60; done"
