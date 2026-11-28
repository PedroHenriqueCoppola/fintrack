[Português](README.pt-BR.md)

<img src="public/brand/fintrack-mark.svg" alt="Fintrack mark: a purple rounded square with a white card and three rising bars" width="64" height="64">

# Fintrack

A personal finance web app for daily use: transactions, credit card invoices, monthly planning, goals, net worth and reports.

<img src="docs/screenshots/dark/01-dashboard.png" alt="Dark dashboard for the current month: period balance, income, expenses, amount set aside, category usage against the plan, and the open invoice of each card" width="1440">

## Features

**Dashboard.** The period at a glance: money in, money out, what is left, and how much of that balance was set aside. The month shows spending against the plan, the open invoice of each card, and the latest transactions.

**Analysis.** Transactions grouped by day, with the totals for the period. Filters for text, category, bank, payment method, tags and amount stay in the address, and a filter can be saved and opened again later.

**Invoices.** Each card has its own closing day. Posted purchases and forecast ones sit on the invoice named after the month it closes. A closed invoice can be marked paid; that mark does not create a new expense.

**Planning.** Set the income you expect and an amount for each category. The month shows what was spent, what is already committed, and what is left to assign. Fixed bills and installments generate their own pending rows.

**Goals and reserves.** A goal follows one reserve category. Saving and withdrawing move that balance, and a withdrawal cannot take it below zero. A finished goal stays finished, and a later goal on the same category starts from its own date.

**Net worth.** Once a month, record the balance of each account and each investment. The total and the change since the previous closing are calculated when the page opens.

**Reports.** Choose a ready-made report or build one on the page, read the preview, then download the same numbers as PDF or Excel. The file is not stored.

**Manage.** Categories, banks, cards, payment methods and tags live on one page. An item that is already in use is archived instead of deleted.

## Screenshots

<table>
<tr>
<td width="50%"><strong>Dark</strong></td>
<td width="50%"><strong>Light</strong></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/01-dashboard.png" alt="Dark dashboard with the period balance, planned usage and open card invoices" width="640"></td>
<td><img src="docs/screenshots/light/01-dashboard.png" alt="Light dashboard with the same period balance, planned usage and open card invoices" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/02-analysis.png" alt="Dark analysis list for the current month, with saved filters and transactions grouped by day" width="640"></td>
<td><img src="docs/screenshots/light/02-analysis.png" alt="Light analysis list for the current month, with saved filters and transactions grouped by day" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/03-invoices.png" alt="Dark invoice of Itaú Click, with posted and forecast purchases and paid invoices below" width="640"></td>
<td><img src="docs/screenshots/light/03-invoices.png" alt="Light invoice of Itaú Click, with posted and forecast purchases and paid invoices below" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/04-planning.png" alt="Dark monthly plan with expected income, category amounts and nothing left to assign" width="640"></td>
<td><img src="docs/screenshots/light/04-planning.png" alt="Light monthly plan with expected income, category amounts and nothing left to assign" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/06-goals.png" alt="Dark goals page with two active goals, one completed goal and the reserve chart" width="640"></td>
<td><img src="docs/screenshots/light/06-goals.png" alt="Light goals page with two active goals, one completed goal and the reserve chart" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/08-net-worth.png" alt="Dark net worth page at 42,000, with the yearly chart and the latest closing by account" width="640"></td>
<td><img src="docs/screenshots/light/08-net-worth.png" alt="Light net worth page at 42,000, with the yearly chart and the latest closing by account" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/09-reports.png" alt="Dark yearly spending-by-category report with PDF and Excel download buttons" width="640"></td>
<td><img src="docs/screenshots/light/09-reports.png" alt="Light yearly spending-by-category report with PDF and Excel download buttons" width="640"></td>
</tr>
<tr>
<td><img src="docs/screenshots/dark/11-new-transaction.png" alt="Dark new-transaction dialog filled in as a paid grocery expense and not yet saved" width="640"></td>
<td><img src="docs/screenshots/light/11-new-transaction.png" alt="Light new-transaction dialog filled in as a paid grocery expense and not yet saved" width="640"></td>
</tr>
<tr>
<td colspan="2"><img src="docs/screenshots/dark/14-dashboard-tablet.png" alt="Dark dashboard at tablet width, with the sidebar collapsed to icons" width="640"><br>Tablet, dark theme, 1024 × 768.</td>
</tr>
</table>

## Tech stack

Versions below are the ones installed in this repository.

| Piece | Version | Source |
| --- | --- | --- |
| PHP | 8.5.12 | `docker/php/Dockerfile` |
| Laravel | 13.42.0 | `composer.lock` (`laravel/framework`) |
| Inertia (Laravel) | 3.7.0 | `composer.lock` (`inertiajs/inertia-laravel`) |
| Inertia (React) | 3.11.0 | `package-lock.json` |
| React | 19.3.1 | `package-lock.json` |
| Vite | 8.3.8 | `package-lock.json` |
| PostgreSQL | 18.6 | `docker-compose.yml` (`postgres:18.6-bookworm`) |
| Node | 26.13.1 | `docker-compose.yml` (`node:26.13.1-bookworm`) |
| Recharts | 3.11.0 | `package-lock.json` |
| Dompdf | 3.1.2 (wrapper), 3.1.6 (library) | `composer.lock` (`barryvdh/laravel-dompdf`, `dompdf/dompdf`) |
| OpenSpout | 5.15.3 | `composer.lock` |
| Playwright | 1.65.1 | `package-lock.json` |
| Composer | 2.11.2 | `docker/php/Dockerfile` |
| pgAdmin | 9.19.0 | `docker-compose.yml` |

The interface is Inertia with React (JSX, no TypeScript) and plain CSS. Icons are Lucide (`lucide-react` 0.554.0). The typeface is Plus Jakarta Sans.

## Getting started

Docker Desktop on Windows, with the project on the WSL filesystem. There is no need for PHP, Composer, Node or PostgreSQL on the host: every command goes through Docker Compose.

```bash
docker compose up -d
```

The first boot installs PHP dependencies, generates the application key and runs the migrations. The Vite container installs the front-end packages. That can take a few minutes:

```bash
docker compose logs -f app vite
```

The app is ready when `app` is listening on `0.0.0.0:8000` and Vite prints its local address.

| Service | Address |
| --- | --- |
| Application | http://localhost:8000 |
| Vite (development assets) | http://localhost:5173 |
| pgAdmin | http://localhost:5050 |

Open http://localhost:8000. Without an account, the login screen is at http://localhost:8000/login. Vite only serves the front-end files; navigation stays on port 8000.

pgAdmin, local only:

- Email: `admin@example.com`
- Password: `fintrack`

The **Fintrack** server is already in the list. The first connection asks for the database password: `fintrack` (user `fintrack`, database `fintrack`). These passwords are for development only.

### Demo account

This recreates only the demo account. Other users are left as they are. In production the command refuses to run unless `--force` is passed.

```bash
docker compose exec app php artisan fintrack:demo
```

- Email: `demo@fintrack.app`
- Password: `fintrack-demo`
- Name: Ana Demo

The data is a little more than a year, dated from today in `America/Sao_Paulo`. Two runs on the same day build the same account.

A new account can also be created at http://localhost:8000/register. It starts with the default categories and payment methods.

### Screenshots

The script signs in through the login screen. `APP_URL` defaults to `http://localhost:8000`.

On the host, when that address is the app:

```bash
npx playwright install chromium
npm run screenshots
```

In this repository Node lives in the Vite container. Chromium also needs its system libraries once per container:

```bash
docker compose exec vite npx playwright install chromium
docker compose exec -u root vite npx playwright install-deps chromium
docker compose exec vite npm run screenshots
```

Pictures are written to `docs/screenshots/dark` and `docs/screenshots/light`. If the demo account does not exist, the script stops with `Rode php artisan fintrack:demo antes`.

### Useful commands

```bash
docker compose logs -f
docker compose stop
docker compose down
docker compose up -d
docker compose restart app
docker compose exec app php artisan migrate
docker compose exec app bash
```

`docker compose down` stops the containers and keeps the database volume. Do not use `down -v` if you want to keep the data.

After a change in `composer.json`:

```bash
docker compose exec app composer install
```

After a change in `package.json`:

```bash
docker compose exec vite npm install
```

The database port is not published. A manual backup is taken from inside the container:

```bash
docker compose exec -T db pg_dump -U fintrack -d fintrack > backup-fintrack.sql
```

## Business rules worth knowing

Money is `DECIMAL(15, 2)` and arithmetic uses bcmath. Derived numbers are not stored: invoice totals, reserve balances, goal progress and net-worth totals are calculated from the rows that were actually saved.

An invoice follows the card's closing day. A purchase on the closing day belongs to the next invoice. Posted means a paid expense in that cycle. Forecast means a pending one.

Fixed bills and installments generate pending transactions. The current month is generated by the system. A future month is generated when that month's plan is confirmed.

Reserve is a third transaction type, with deposit and withdrawal. The balance of a reserve category is never allowed to go negative.

A goal belongs to one reserve category. The first goal counts the whole history. A later goal starts from its own date, and finishing one goal freezes it so the next one can begin.

Net worth is a monthly closing: one balance per account and per investment. Reports show a preview, a PDF and an Excel file from the same structure, and none of the three is saved.

## Architecture

Controllers stay thin. Form Requests validate input, Services apply the rules, and Resources shape what each Inertia page receives. There is no separate JSON API. A global scope ties every user-owned query to the signed-in user and sets `user_id` on create. In the console, where nobody is signed in, that scope does not filter.

More detail: [architecture](docs/architecture.md), [routes](docs/route-map.md), [product rules](docs/product-vision.md) and the [design system](docs/design-system.md).

## How it was built with AI agents

The author owned the product: the decisions, the manual test scripts, and the merge of each module. Claude orchestrated: the decision questions, the specification of each module as a prompt, a code review of each commit, and the test scripts. The Cursor agent wrote the implementation.

Each module followed the same cycle: decision questions, then a prompt with the specification and the updated docs, then the implementation with a mandatory self-review, a code review, correction rounds, manual tests, and the merge.

The files that guided the agents are `docs/product-vision.md`, `docs/design-system.md` and `.cursor/rules/`.

`git rev-list --count HEAD` reports 59 commits. `git log --grep='Merge pull request' --oneline` lists 12 merged pull requests. Those pull requests brought in the twelve branches from `feat/module-02-auth` through `feat/module-11b-polish`, all contained in `main`.

The written spec kept the modules consistent. Invoice cycles and goal restarts needed more correction rounds than the simpler screens.

## Deploying

Before pointing a public address at the app:

- Set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL` to the `https` address.
- Set `SESSION_SECURE_COOKIE=true`.
- Set `REGISTRATION_ENABLED` to `true` only while open sign-up should exist. With `false`, `/register` answers 404 and the login screen hides "Criar conta".
- Replace every default password: the database user and pgAdmin. Do not publish pgAdmin.
- Run `php artisan migrate --force`, then `php artisan config:cache`, `php artisan route:cache` and `php artisan view:cache`.
- Run `npm run build`.

`php artisan fintrack:demo --force` works in production and recreates the demo account. That account stays public with the password printed in this README. Do not run it on a public server unless that is intentional.

## Limitations and next steps

There is no automated test suite. Testing was done with manual scripts. There is no dedicated phone layout. There is no automatic backup. Investment accounts are balances recorded at each closing, with no yield. There is no password reset by email.


## 🔗 Links

[![linkedin](https://img.shields.io/badge/linkedin-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/pedro-henrique-coppola-071baa225/) [![instagram](https://img.shields.io/badge/Instagram-E4405F?style=for-the-badge&logo=instagram&logoColor=white)](https://www.instagram.com/pedrocoppola_/)

## License

All rights reserved.
