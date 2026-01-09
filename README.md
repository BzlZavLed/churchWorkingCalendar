# Church Working Calendar

Church Working Calendar is a scheduling and approval platform for church departments. Teams create events, lock them for review, and secretaries approve, request changes, or deny. Approved events can optionally be published to a public ICS feed for calendar subscriptions.

## Project Structure

- `app/`
  - Laravel backend (API controllers, models, policies, events).
- `routes/`
  - API endpoints (`routes/api.php`) for auth, events, objectives, superadmin, and public feeds.
- `database/`
  - Migrations and seeders.
- `frontend/`
  - Vue 3 SPA (Vite + Pinia). Main views are in `frontend/src/components/`.
- `public/`
  - Built SPA assets in `public/spa/`.

## Key Workflows

### Event lifecycle
1. Department creates a hold.
2. Department locks the event.
3. Secretary reviews (approve/deny/changes).
4. Final outcome is recorded (`final_validation`).
5. If approved and marked “publish to feed,” the event appears in the public ICS feed.

### Notes workflow
- Secretaries can leave notes on events.
- Departments can reply once per event.
- Unseen notes are tracked for department users.

## Public Feed

- JSON feed: `GET /api/public/events`
- ICS feed: `GET /api/public/events.ics`
- Example subscription URL: `https://www.mychurchadmin.net/api/public/events.ics`

Only events with:
- `final_validation = accepted`
- `publish_to_feed = true`

are included in the public feed.

## Frontend

- Calendar: `frontend/src/components/CalendarView.vue`
- Reports: `frontend/src/components/ReportsView.vue`
- Objectives: `frontend/src/components/ObjectivesView.vue`
- Superadmin: `frontend/src/components/superadmin/`

Language selection persists via `localStorage` key `ui_locale`.

## Development

Backend:
```bash
composer install
php artisan migrate
php artisan serve
```

Frontend:
```bash
cd frontend
npm install
npm run dev
```

## Seeders

- Update Benjamin password:
```bash
php artisan db:seed --class=UpdateBenjaminPasswordSeeder
```
