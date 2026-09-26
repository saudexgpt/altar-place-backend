# Deploying to DigitalOcean App Platform

This is a click-through walkthrough for the DO dashboard — no `doctl`/API automation, matching how this repo is set up. Everything here reads from the `Dockerfile` + `docker/` folder in this directory (`backend-laravel/`), since App Platform builds this component from that image rather than auto-detecting a PHP buildpack (a buildpack wouldn't include `ffmpeg`).

Do these roughly in order — later steps need values from earlier ones.

## 1. Push the code

Commit and push this repo (with `Dockerfile`, `docker/`, `.dockerignore`, `.env.production.example`) to GitHub. App Platform builds from a GitHub repo, not a local upload.

## 2. Create a Spaces bucket (object storage)

1. DO dashboard → **Spaces Object Storage** → Create a Spaces bucket. Pick a region (e.g. `nyc3`) — remember it, it becomes `AWS_DEFAULT_REGION`.
2. **API** → **Spaces Keys** → generate a new key pair. These become `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`.
3. Note the bucket name (`AWS_BUCKET`), the endpoint shown on the bucket (`https://<region>.digitaloceanspaces.com` → `AWS_ENDPOINT`), and the CDN URL shown on the bucket settings page (`AWS_URL`).

This one bucket holds both `public/...` (covers, avatars, ad creatives) and `audio/...` + `hls/...` (uploaded tracks and their transcoded renditions) — see `config/filesystems.php`.

## 3. Create the database

**Databases** → Create a Database Cluster → MySQL (or use App Platform's cheaper built-in "Dev Database" if you're just getting started — same connection-detail flow). Note the host, port, database name, username, password for `DB_*`.

## 4. Create the App Platform app

1. **Apps** → Create App → GitHub → select this repo/branch.
2. When it asks how to build the component, choose **Dockerfile**, and set the **Source Directory** to `backend-laravel` (this is a monorepo — the mobile app lives alongside it and isn't part of this component).
3. Set the **HTTP Port** to `8080` (matches `docker/nginx.conf`).
4. Instance size: start on the smallest "Basic" instance; bump it up once you see real traffic.

## 5. Add a pre-deploy migration job

Still in the app's component list, add a **Job** component (also built from the same Dockerfile / `backend-laravel` source directory), with:
- **Run Command**: `sh docker/predeploy.sh`
- **Trigger**: Pre-Deploy

This runs once per deploy, before traffic shifts to the new version — safer than running migrations from the web container's entrypoint, which would race across multiple instances if you ever scale out. The script runs `migrate --force` and then seeds the roles/permissions and subscription plans (both idempotent, and both required — registration fails without the `listener` role). It deliberately does **not** run `DatabaseSeeder`, which creates demo accounts with known passwords.

(`docker/entrypoint.sh` runs whatever command it's given and exits, instead of starting the web stack, so the Job terminates normally.)

## 6. Set environment variables

Copy every value from `.env.production.example` into the web service's **Environment Variables**, filling in the blanks from steps 2–3. A few that need care:

- `APP_KEY` — generate one locally: `php artisan key:generate --show`, paste the `base64:...` output.
- `APP_URL` / `FRONTEND_URL` / the OAuth redirect URIs — use your real domain (see step 7) once you know it; you can use the `*.ondigitalocean.app` URL App Platform gives you as a placeholder and update these after attaching the domain. **Use one domain for everything**: Laravel serves the Vue web app itself, so the web app and the API share an origin (no separate `api.` subdomain needed).
- `SANCTUM_STATEFUL_DOMAINS` — the host(s) the web app is browsed on, no scheme. The web app logs in with a cookie session, and Sanctum only issues one to hosts listed here. It replaces Sanctum's defaults, so include both the `*.ondigitalocean.app` host and your real domain while both are in use; a missing host shows up as login succeeding but every next request being 401.
- Mark `APP_KEY`, `DB_PASSWORD`, `AWS_SECRET_ACCESS_KEY`, mail/payment secrets as **Encrypted**.
- Copy the same variables onto the migration Job component too (it needs `DB_*` and `APP_KEY` to run).

Deploy. Watch the build logs — first build is the slowest (installing PHP extensions).

## 7. Attach your domain

1. App Platform → **Settings** → **Domains** → add your domain (e.g. `yourdomain.com`, or `app.yourdomain.com`).
2. It gives you a CNAME target. Add that CNAME at your DNS provider.
3. Once DNS propagates, App Platform provisions a Let's Encrypt certificate automatically — no separate Certbot step needed here (that's a Droplet-only concern).
4. Update `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, and the two OAuth redirect URIs in the app's env vars to the real domain, then also update the redirect URIs registered in the Google Cloud Console and Facebook Developer Console OAuth apps to match.
5. In the component's **Health Checks**, set the HTTP path to `/up`.

## 8. First-run setup

The deploy seeds roles and plans but creates no accounts and no genres. Open the web component's **Console** tab (App Platform → your app → Console) and run:

```
php artisan tinker --execute='$u = App\Models\User::forceCreate(["name" => "Admin", "email" => "you@yourdomain.com", "password" => Hash::make("CHOOSE-A-STRONG-PASSWORD"), "status" => "active", "email_verified_at" => now()]); $u->assignRole("super-admin");'
```

Sign in at `/login` — a super-admin lands on the Admin Panel. Change that password from Settings afterwards (it will be in your shell history).

Genres are only created by the demo `CatalogSeeder`, so a fresh production database has none and the genre dropdown on track upload will be empty. Add the ones you want (Gospel, Worship, …) via tinker with `App\Models\Genre::create(['name' => 'Gospel', 'slug' => 'gospel'])`.

## 9. Verify

- `curl https://yourdomain.com/up` → should return 200 (Laravel's built-in health check route).
- Open `https://yourdomain.com` — the marketing page and `/login` should load, and after signing in the session should persist across a refresh (if not, re-check `SANCTUM_STATEFUL_DOMAINS`).
- In the app's **Runtime Logs**, you should see three long-lived processes alongside nginx/php-fpm: `queue-worker` and `scheduler` (from `docker/supervisord.conf`). If notifications or subscription auto-expiry stop working, check here first.
- Upload a track through the app and confirm it shows up in the Spaces bucket under `audio/` — that's the real test that the storage config is wired correctly, not just that the app boots.
- ffmpeg is now actually installed (it wasn't in local dev), so `transcoding_status` should progress from `pending` → `ready` a little while after upload instead of sitting on `failed`/direct-streaming fallback. Check the queue-worker logs if it doesn't.

## 10. Point the mobile app at production

In `mobile-ionic-app/.env`:

```
VITE_API_URL=https://yourdomain.com/api
VITE_APP_URL=https://yourdomain.com
```

Rebuild (`npm run build`) before producing the release APK/AAB or iOS build — the LAN-IP value used for local device testing won't work once the app is off your network.

## What's still local-only after this

- `PAYMENT_TESTING_MODE=true` — every Paystack/Flutterwave call resolves to a fake, instantly-successful gateway until you add real keys and flip this to `false`.
- Redis/Reverb were never wired into this app (cache/queue/session all run on the `database` driver) — fine at this scale, worth revisiting if you need real-time push notifications or hit database contention under load.
