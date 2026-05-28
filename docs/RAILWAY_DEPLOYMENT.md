# Deploy WEBAPP backend to Railway

This Symfony 7 API powers the mobile app (APPDEV). Deploy **only this repository**; point the mobile app at the Railway public URL.

## Prerequisites

- GitHub repo with this project
- [Railway](https://railway.com) account
- MySQL database on Railway (not local Docker)

## 1. Create Railway project

1. **New Project** → **Deploy from GitHub repo** → select `WEBAPP`.
2. **Add service** → **Database** → **MySQL**.
3. Open the **web service** → **Variables** → **Add reference** / link variables from the MySQL service.

Railway usually injects `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLPORT`, `MYSQLDATABASE`, and sometimes `DATABASE_URL`. The Docker entrypoint builds `DATABASE_URL` from `MYSQL*` when `DATABASE_URL` is empty.

## 2. Required environment variables

| Variable | Example / notes |
|----------|-----------------|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | Random 32+ char string (`openssl rand -hex 16`) |
| `APP_URL` | `https://your-app.up.railway.app` (public HTTPS URL) |
| `DATABASE_URL` | Optional if MySQL vars are linked; else set full URL |
| `JWT_PASSPHRASE` | Same as local, or empty if using auto-generated keys |
| `JWT_PRIVATE_KEY_BASE64` | Recommended — see below |
| `JWT_PUBLIC_KEY_BASE64` | Recommended — see below |
| `MAILER_DSN` | e.g. Brevo SMTP DSN |
| `MAILER_FROM_ADDRESS` | Sender email |
| `MAILER_FROM_NAME` | Sender name |
| `GOOGLE_CLIENT_ID` | For `/api/auth/google` |
| `GOOGLE_ANDROID_CLIENT_ID` | Android OAuth client ID |

Optional:

| Variable | Notes |
|----------|--------|
| `RUN_MIGRATIONS` | Set to `1` once to run migrations on startup, then remove |
| `RUN_FIXTURES` | Set to `1` **once** to create admin + default services, then remove |
| `INITIAL_ADMIN_EMAIL` | Admin login email (or use `ADMIN_EMAIL`) |
| `INITIAL_ADMIN_PASSWORD` | Plain password; hashed in DB (or use `ADMIN_PASSWORD`) |
| `INITIAL_ADMIN_NAME` | Display name (or use `ADMIN_NAME`) |
| `SYNC_INITIAL_ADMIN_PASSWORD` | Set to `1` for one deploy — entrypoint runs `app:sync-initial-admin` against Railway MySQL |
| `PROMOTE_INITIAL_ADMIN` | Optional; entrypoint always passes `--promote` so client accounts become admin |
| `RUN_ADMIN_SYNC` | Alias for `SYNC_INITIAL_ADMIN_PASSWORD=1` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Aliases for `INITIAL_ADMIN_*` if you used those names earlier |
| `LOGIN_DEBUG` | Set to `1` temporarily — logs login failure diagnostics (no passwords); remove after fixing |
| `CORS_ALLOWED_ORIGINS` | `*` default in prod config; native mobile ignores CORS |
| Firebase `FIREBASE_WEB_*` | Only if web client pages need Firebase |

Do **not** set `DATABASE_URL` to `localhost` or `db` in production.

## 3. JWT keys for Railway

Generate locally (once):

```bash
php bin/console lexik:jwt:generate-keypair
```

Encode for Railway (PowerShell):

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("config/jwt/private.pem"))
[Convert]::ToBase64String([IO.File]::ReadAllBytes("config/jwt/public.pem"))
```

Set `JWT_PRIVATE_KEY_BASE64` and `JWT_PUBLIC_KEY_BASE64` in Railway.  
If omitted, the container generates new keys on each deploy (all existing JWTs become invalid).

## 4. Database migrations

**Recommended (one-time):** In Railway → web service → **Variables**:

```
RUN_MIGRATIONS=1
```

Deploy, confirm logs show `Running database migrations...`, then **remove** `RUN_MIGRATIONS` and redeploy.

**Alternative (Railway shell / one-off command):**

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Migrations are forward-only; review `migrations/` before running in production.

## 5. Deploy

Railway builds from `Dockerfile` (`railway.toml` points to it). Health check: `GET /health`.

After deploy, verify:

- `GET https://YOUR_URL/health` → `{"status":"ok"}`
- `GET https://YOUR_URL/api/client/services` → services JSON
- `POST https://YOUR_URL/api/login` with client credentials

## 6. Mobile app

Update APPDEV:

```text
API_BASE_URL=https://your-app.up.railway.app
```

Use HTTPS. No trailing slash.

## 7. Local development unchanged

Keep using `.env` (gitignored) with local `DATABASE_URL` and `symfony serve`. Docker/Railway settings do not affect local dev unless you copy production values into `.env`.

## 8. Uploads note

Avatar uploads live in `public/uploads/avatars`. Railway filesystem is ephemeral unless you add a volume or external storage.
