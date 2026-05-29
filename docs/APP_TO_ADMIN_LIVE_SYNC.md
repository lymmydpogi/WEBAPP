# App → Admin website live sync

## Flow

```
React Native (appdev)
  POST /api/client/orders/from-service  (JWT)
       ↓
Symfony WEBAPP → MySQL `order` table
       ↓
Admin browser polls GET /admin/live/orders every 800ms (no overlapping requests)
       ↓
Orders table on /order updates without refresh
```

## Requirements

1. **App API URL** must be production Railway (`config.ts` default).
   - Do **not** use `apiConfig.override.ts` pointing at localhost unless your admin site uses the same local DB.

2. **Admin** must be logged in and stay on `/order` or `/home` (or any admin page — polling runs on all admin pages).

3. **Railway** must deploy latest WEBAPP with `public/js/admin-live.js`.

## Verify

1. Open admin **Orders** in Chrome.
2. DevTools → Network → filter `admin/live/orders`.
3. You should see requests every **~500ms** with JSON `{ success: true, orders: [...] }`.
4. Create an order in the app → new row appears within ~1 second.

## Troubleshooting

| Symptom | Cause |
|---------|--------|
| No `admin/live` requests | Not logged in, or old deploy without `admin-live.js` |
| 401/403 on poll | Session expired — log in again |
| HTML instead of JSON | Redirect to login |
| Poll OK but no new rows | App writing to a **different** database (local API override) |
