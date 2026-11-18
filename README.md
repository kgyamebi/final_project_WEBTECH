# InsideAccra

InsideAccra is a map-first platform for selected parts of Accra. It joins a small trotro route dataset with community reports of road and transport conditions. A verified report near a stored route can warn commuters. The app does not invent routes, fares, journey times, or live vehicle positions.

This build uses PHP 8.3, MariaDB, HTML, CSS, JavaScript, and Leaflet. There is no PHP framework. Routing, sessions, and SQL live in `src/`.

## What is real in this copy

The routes, stops, seeded incidents, and analytics are **sample / demo data**. Stop coordinates are approximate public landmarks so the journey search and disruption engine can be demonstrated. They are not an official GPRTU, municipal, or operator listing. Every demo route is labelled, and fares and durations are shown as not verified.

## Requirements

- PHP 8.1+ with `pdo_mysql`
- MariaDB or MySQL 8
- A browser

On this machine PHP is at `C:\php\php.exe` and is not on `PATH`. The commands below call it directly. MariaDB is expected on `127.0.0.1:3306`.

## Setup

1. Copy `.env.example` to `.env` and set the database user and password. Do not commit `.env`.
2. Create a database user that can use the `insideaccra` database (and `insideaccra_test` if you run tests). The app does not need global privileges.
3. Apply the schema and seed the demo data:

```powershell
& "C:\php\php.exe" -d extension_dir=C:\php\ext -d extension=pdo_mysql bin\migrate.php --fresh
& "C:\php\php.exe" -d extension_dir=C:\php\ext -d extension=pdo_mysql bin\seed.php --force
```

`--fresh` drops tables in the database named by `DB_NAME` and recreates them. `--force` replaces demo rows.

4. Start the site:

```powershell
powershell -ExecutionPolicy Bypass -File bin\serve.ps1
```

Open http://127.0.0.1:8080

`pdo_mysql` must be enabled. The serve script loads it with `-d extension=pdo_mysql` because the machine `php.ini` leaves that extension commented out.

## Demo accounts

Password for all three: `DemoPass123!`

| Role | Email |
| --- | --- |
| Commuter | ama@example.com |
| Moderator | kofi.mod@example.com |
| Administrator | admin@example.com |

## Five-minute walkthrough

1. Open the map and search **Madina** to **Circle**. The direct result is sample route IA-01. Fare and duration stay unverified.
2. Search **Madina** to **Kaneshie**. The result transfers at Kwame Nkrumah Circle. IA-01 already carries a demo flood warning.
3. Sign in as Ama and submit a pothole or flood report with a pin near a route. The reference starts as Pending Review.
4. Sign in as Kofi, open the review queue, and mark that report Verified with a reason.
5. Return to the map or the journey. The verified report can show “Potential disruption reported near this route”, with the incident link and the time the match was calculated.
6. Resolve the report. The active warning is removed. A pending report never claims a closure.

## Tests

```powershell
& "C:\php\php.exe" -d extension_dir=C:\php\ext -d extension=pdo_mysql tests\run.php
```

The script rebuilds `insideaccra_test` and checks permissions, place search, direct and one-transfer routing, the rule that pending reports do not warn, and that verifying then resolving a report creates and then clears a disruption flag.

## Main areas

- Home, explore map, journey search, route and stop pages
- Report submission, My Reports, incident page, notifications, profile
- Moderator review with status history, duplicate suggestions, and notes
- Admin routes, stops, users, categories, settings, analytics, and audit log
- JSON API under `/api`

Journey search treats stops as a graph. Ride edges follow each route’s stop order. A transfer is allowed only at the same stop or between stops inside the configured straight-line radius (400 metres by default). Results prefer fewer transfers, then less walking. Duration is not used.

The disruption engine flags a route only for a verified report that is not stale, and only when the point is within the category’s distance rules of the stored line or a stop. The highest level a category can assign on its own is “likely”. “Confirmed closure” requires an administrator and a written source. Resolving, rejecting, or duplicating a report deactivates its flags.

## Security notes

- Passwords are stored with `password_hash`.
- Session cookies are HttpOnly and SameSite=Lax, and Secure on HTTPS.
- State-changing requests need a CSRF token.
- SQL uses prepared statements.
- Public incident responses omit reporter email, phone, and internal notes.
- Uploads are limited to JPEG, PNG, and WebP, checked with `getimagesize`, and stored in `storage/uploads`, outside `public/`.
- Role checks run on the server. A hidden button is not treated as access control.

## Known limits

- Coverage is a handful of demo corridors, not Accra as a whole.
- Route lines are straight connections between stops, not surveyed road centrelines.
- Walking distance is straight-line, not a footpath network.
- There is no live vehicle feed, no verified fare table, and no email or push notification.
- Map tiles come from OpenStreetMap and need a network connection. The lists still work if tiles fail.
- Analytics that include seeded rows are labelled demo analytics.
