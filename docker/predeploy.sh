#!/bin/sh
# Runs once per deploy as App Platform's pre-deploy Job, before traffic
# shifts to the new version. Everything here must be safe to re-run.
set -e

cd /var/www/html

php artisan migrate --force

# Roles/permissions and subscription plans are required for the app to work
# at all (registration assigns the 'listener' role), and both seeders are
# idempotent (findOrCreate / updateOrCreate). Deliberately NOT running
# DatabaseSeeder: it creates demo accounts with known passwords and demo
# catalog data.
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=SubscriptionPlanSeeder --force
