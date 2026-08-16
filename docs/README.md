# PBN Hidden Link Management

A Laravel-based centralized dashboard for managing hidden/SEO links across a Private Blog Network (PBN) of 100+ websites. Post and manage links in bulk with queue-based processing.

## Features

- **Dashboard** – Overview stats (domains, batches, links posted)
- **Domain Management** – Add domains manually or import via CSV
- **Batch Creation** – 3-step wizard: Batch info → Select domains → Add links
- **Link Posting** – Queue-based bulk posting to PBN site APIs
- **Batch Tracking** – Per-domain status, success/fail counts
- **Reports** – Filter by batch/domain/date (UI ready)
- **Settings** – API timeout, rate limits configuration
- **Database Queue** – No Redis required; uses Laravel's database queue driver

## Tech Stack

- Laravel 12
- Laravel Breeze (Blade + Alpine.js)
- Tailwind CSS
- Database Queue (no Redis)
- Maatwebsite Excel removed – CSV import only (add PhpSpreadsheet for .xlsx if needed)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Running

1. **Web server:** `php artisan serve`
2. **Queue worker** (required for link posting): `php artisan queue:work database --queue=links`
3. **Vite dev** (optional): `npm run dev`

Or use the dev script: `composer run dev` (runs server, queue, and Vite together).

## CSV Import Format

Columns: `domain`, `api_url`, `api_key` (optional)

```csv
domain,api_url,api_key
example.com,https://example.com/api,abc123
```

## PBN API Contract

Your PBN sites should expose:

- **POST** `{api_url}/links` – Body: `{ "url": "...", "keyword": "...", "nofollow": 0/1 }` → Response: `{ "post_id": "..." }` or `{ "id": "..." }`
- **DELETE** `{api_url}/links/{remote_post_id}`
- **GET** `{api_url}/health` (optional) – For domain health check

## Routes

| Route | Description |
|-------|-------------|
| `/dashboard` | Overview |
| `/domains` | Domain list, add, import |
| `/batches` | Batch list |
| `/batches/create` | Create batch wizard |
| `/batches/{id}` | Batch detail & per-domain stats |
| `/reports` | Reports (filters UI) |
| `/settings` | Configuration |

## Note on Redis

Redis is **not** used. The queue uses the database driver. Configure `QUEUE_CONNECTION=database` in `.env`. Redis can be added in a future project if desired.
