# Project Flow – How It Works

This document explains how the application works: which routes call which controllers and methods, what those methods do, and which other files or functions they call. Use it to follow a user action from the browser to the database and back.

---

## 1. Request flow (high level)

```
User action (click / form submit)
    → Route (web.php or auth.php)
    → Controller method
    → Model / Job / Service / View
    → Response (redirect or HTML)
```

- **Routes** live in `routes/web.php` and `routes/auth.php`.
- **Controllers** live in `app/Http/Controllers/`.
- **Models** in `app/Models/`, **Jobs** in `app/Jobs/`, **Services** in `app/Services/`.
- **Views** (Blade) in `resources/views/`. The app uses the **dashboard layout** (`layouts.dashboard`) for all authenticated pages (sidebar, navbar, main content).

---

## 2. Authentication (guest)

These routes use the `guest` middleware (no login required).

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /login` | `AuthenticatedSessionController` | `create` | Shows **auth.login** view (login form). |
| `POST /login` | `AuthenticatedSessionController` | `store` | Validates credentials, logs user in, redirects to **dashboard**. |
| `GET /register` | `RegisteredUserController` | `create` | If **no users exist**: shows **auth.register**. If users exist: redirects to login with “Registration disabled”. |
| `POST /register` | `RegisteredUserController` | `store` | If no users: validates input, creates **User**, fires `Registered` event, logs in, redirects to **dashboard**. Otherwise redirects to login. |
| `GET /forgot-password` | `PasswordResetLinkController` | `create` | Shows **auth.forgot-password** (email form). |
| `POST /forgot-password` | `PasswordResetLinkController` | `store` | Sends password reset email (uses Laravel’s password reset), redirects with status. |
| `GET /reset-password/{token}` | `NewPasswordController` | `create` | Shows **auth.reset-password** form. |
| `POST /reset-password` | `NewPasswordController` | `store` | Validates token and new password, updates user password, redirects to login. |

---

## 3. Authentication (after login)

These use the `auth` middleware (and sometimes `verified`).

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `PUT /password` | `Auth\PasswordController` | `update` | Updates current user’s password (used by profile “Update password” form). |
| `POST /logout` | `AuthenticatedSessionController` | `destroy` | Logs user out, invalidates session. |

---

## 4. Dashboard and app shell

All app routes below use **middleware: `auth`, `verified`**. They all render content inside **layouts.dashboard** (sidebar + navbar + main).

### 4.1 Dashboard (home)

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /` | `DashboardController` | `index` | **Uses:** `Domain`, `Batch` (queries by `user_id`). **Returns:** view **dashboard.index** with `$stats` (domains count, batches count, links_posted, active_batches) and `$recentBatches` (latest 5). Named route: `dashboard`. |
| `GET /dashboard` | — | — | Permanent redirect (301) to `/`. |

**Flow:**
`DashboardController::index()` → queries `Domain` and `Batch` → passes data to `resources/views/dashboard/index.blade.php`. Unauthenticated users visiting `/` are redirected to the login page.

---

## 5. Domains

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /domains` | `DomainController` | `index` | **Uses:** `Domain`, `DomainImport`. **Returns:** **domains.index** with paginated `$domains` and recent `$imports`. |
| `POST /domains` | `DomainController` | `store` | Validates input. **Creates** `Domain` with `status = 'inactive'`. **Dispatches** `DomainHealthCheckJob` for the new domain. Redirects to **domains.index** with success. |
| `GET /domains/{domain}/edit` | `DomainController` | `edit` | Ensures domain belongs to user. **Returns:** **domains.edit** with `$domain`. |
| `PATCH /domains/{domain}` | `DomainController` | `update` | Validates, updates `Domain`. Redirects to **domains.index**. |
| `DELETE /domains/{domain}` | `DomainController` | `destroy` | Ensures ownership, **deletes** `Domain` (cascade removes related data). Redirects to **domains.index**. |
| `POST /domains/{domain}/recheck` | `DomainController` | `recheck` | **Dispatches** `DomainHealthCheckJob` for that domain. Redirects with “Health check queued”. |
| `POST /domains/bulk-delete` | `DomainController` | `destroyBulk` | Validates `domain_ids[]`. **Deletes** all matching domains for the user. Redirects to **domains.index**. |
| `POST /domains/import` | `DomainController` | `import` | Validates file (xlsx, csv, etc.). **Stores** file, creates `DomainImport` record. **Dispatches** `ImportDomainsJob` on queue `import_domains`. Redirects with “Import queued”. |
| `DELETE /domains/imports/{domainImport}` | `DomainController` | `destroyImport` | Deletes file from storage and `DomainImport` record. Redirects to **domains.index**. |

**Flow examples:**

- **Add domain:** `DomainController::store` → `Domain::create` → `DomainHealthCheckJob::dispatch` → redirect.  
  Later, when the **domains** queue runs, `DomainHealthCheckJob` calls `PbnApiService::ping($domain)` and updates domain `status` and `last_health_error`.

- **Bulk import:** `DomainController::import` → store file, `DomainImport::create` → `ImportDomainsJob::dispatch`.  
  When **import_domains** queue runs, `ImportDomainsJob` parses the file, creates/updates `Domain` rows, and dispatches `DomainHealthCheckJob` for each.

---

## 6. Batches

### 6.1 List and create

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /batches` | `BatchController` | `index` | **Uses:** `Batch` (with optional search). **Returns:** **batches.index** with `$batches`, `$search`. |
| `GET /batches/create` | `BatchController` | `create` | **Uses:** `Domain` (active only). **Returns:** **batches.create** with `$domains`. |
| `POST /batches` | `BatchController` | `store` | Validates name, description, domain_ids, links. **Creates** `Batch`. **Creates** one `Link` per link row. Builds `$linksArray`, chunks by `BatchDomainChunk::CHUNK_SIZE`. **Creates** one `BatchDomainChunk` per (domain × chunk index). **Dispatches** `PublishBatchChunkJob` for each chunk with staggered delay. Updates batch `status` to `processing`. Redirects to **batches.show** with “Run queue worker (batch_links)”. |

**Flow for “Create batch”:**

1. `BatchController::store`  
   → `Batch::create`  
   → `Link::create` for each link  
   → `BatchDomainChunk::create` for each (domain, chunk)  
   → `PublishBatchChunkJob::dispatch($chunk)` for each chunk (queue: **batch_links**).

2. When **batch_links** queue runs, `PublishBatchChunkJob::handle` loads the chunk and domain, calls `PbnApiService::postChunk($domain, $chunk)` (POST `/hidden-links`), then updates the chunk’s `results_payload`, counts, and batch totals.

### 6.2 View batch and domain

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /batches/{batch}` | `BatchController` | `show` | **Uses:** `Batch`, `BatchDomainChunk`, `Domain`. Builds `$domainStats` from chunks, `$links` from batch, `$failedLinks` and `$linkStatusByDomain` via private helpers `getFailedLinksFromChunks`, `getLinkStatusByDomainFromChunks`. **Returns:** **batches.show** with batch, domain stats, links, failed links, link status, `hasPendingChunks`. |
| `GET /batches/{batch}/domains/{domain}` | `BatchController` | `showDomain` | **Uses:** `BatchDomainChunk` for that batch+domain. Builds `$linksWithStatus` from chunk payloads. **Returns:** **batches.domain** with batch, domain, chunks, linksWithStatus, batchLinks. |

### 6.3 Batch actions

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `DELETE /batches/{batch}/links/{link}` | `BatchController` | `destroyLink` | Ensures batch/link ownership. Finds link index in batch. **Dispatches** `RemoveLinkFromBatchJob` (queue: **remove_link_from_batch**). Redirects with “Link removal queued”. |
| `POST /batches/{batch}/retry-failed` | `BatchController` | `retryFailed` | Loads chunks with `failed_count` > 0. For each, finds failed link indices, creates **new** `BatchDomainChunk` rows (status `pending`) with only failed links. Updates original chunk payloads (removes failed entries). Decrements batch `failed_count`, sets batch `status` to `processing`. Does **not** dispatch jobs; user must use “Publish pending chunks” to send the new chunks. Redirects with “Retrying X failed link(s). New chunks queued.” |
| `POST /batches/{batch}/publish-pending` | `BatchController` | `publishPending` | Loads chunks with status `pending` or `processing` with `error_message`. Resets those to `pending` if needed. **Dispatches** `PublishBatchChunkJob` for each pending chunk (staggered delay). Redirects with “Queued X pending chunk(s)”. |
| `DELETE /batches/{batch}` | `BatchController` | `destroy` | **Dispatches** `DeleteBatchJob` on queue **delete_batch_links**. Redirects to **batches.index** with “Batch delete queued”. |

**Flow for “Delete one link” (trash icon):**  
`BatchController::destroyLink` → `RemoveLinkFromBatchJob::dispatch($batch, $link)`.  
When **remove_link_from_batch** runs, the job calls `PbnApiService::deleteLinkByUrl($domain, $link->url)` per domain, then updates chunk payloads and deletes the `Link`.

**Flow for “Delete batch”:**  
`BatchController::destroy` → `DeleteBatchJob::dispatch($batch)`.  
When **delete_batch_links** runs, the job calls `PbnApiService::deleteLinksByBatchId($domain, $batch->id)` per domain, then `$batch->delete()`.

---

## 7. Reports

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /reports` | `ReportController` | `index` | **Uses:** `Batch`, `Domain`, `BatchDomainChunk`. Filters chunks by batch_id, domain_id, from_date, to_date. If `?export=csv`: calls `exportCsv($query)` and returns CSV download. Otherwise: builds `$rows` from `chunksToReportRows` (flattens chunk link/result data), paginates. **Returns:** **reports.index** with `$batches`, `$domains`, `$rows`. |

---

## 8. Settings and profile

| Route | Controller | Method | What it does |
|-------|------------|--------|---------------|
| `GET /settings` | `SettingsController` | `index` | **Returns:** **settings.index** (dashboard layout). No models; page includes link to profile. |
| `GET /profile` | `ProfileController` | `edit` | **Returns:** **profile.edit** with `$user` (dashboard layout). Form sections: profile info, update password, delete account. |
| `PATCH /profile` | `ProfileController` | `update` | Uses `ProfileUpdateRequest`. Updates `$request->user()` (name, email). If email changed, clears `email_verified_at`. Saves user. Redirects to **profile.edit** with `status = profile-updated`. |
| `DELETE /profile` | `ProfileController` | `destroy` | Validates password (bag `userDeletion`). Logs out, deletes user, invalidates session. Redirects to `/`. |

Password update is handled by the **profile** view: the “Update password” form posts to **PUT /password** (auth route), handled by `Auth\PasswordController::update`.

---

## 9. Controllers → models, jobs, services (summary)

| Controller | Uses models | Dispatches jobs | Uses services |
|------------|-------------|-----------------|---------------|
| **DashboardController** | Domain, Batch | — | — |
| **DomainController** | Domain, DomainImport | DomainHealthCheckJob, ImportDomainsJob | — |
| **BatchController** | Batch, BatchDomainChunk, Domain, Link | PublishBatchChunkJob, RemoveLinkFromBatchJob, DeleteBatchJob | — |
| **ReportController** | Batch, BatchDomainChunk, Domain | — | — |
| **SettingsController** | — | — | — |
| **ProfileController** | User (via $request->user()) | — | — |

**Jobs** use:

- **PublishBatchChunkJob:** `BatchDomainChunk`, `PbnApiService::postChunk`
- **DeleteBatchJob:** `Batch`, `BatchDomainChunk`, `PbnApiService::deleteLinksByBatchId`
- **RemoveLinkFromBatchJob:** `Batch`, `Link`, `BatchDomainChunk`, `PbnApiService::deleteLinkByUrl`
- **DomainHealthCheckJob:** `Domain`, `PbnApiService::ping`
- **ImportDomainsJob:** `DomainImport`, `Domain`, `DomainHealthCheckJob::dispatch`

---

## 10. Views and layout

- **Layout:** Authenticated pages use `resources/views/layouts/dashboard.blade.php` (sidebar, navbar, main). Content is injected via `@yield('content')`.
- **Sidebar:** `layouts/partials/sidebar.blade.php` – links to Dashboard, Domains, Batches, Create Batch, Profile, Reports, Settings.
- **Navbar:** `layouts/partials/navbar.blade.php` – page title and user dropdown (Profile, Log out).

Main view files:

- **dashboard/index** – stats cards, recent batches, quick actions.
- **domains/index** – domain list, bulk delete, import form; **domains/edit** – edit form.
- **batches/index** – batch list; **batches/create** – create form; **batches/show** – batch detail and domain table; **batches/domain** – links for one domain in a batch.
- **reports/index** – filters and report table (or CSV export).
- **settings/index** – app settings and link to profile.
- **profile/edit** – profile info form, password form, delete account.

---

## 11. Queue workers to run

For background jobs to run, start a worker for the relevant queue(s). See **docs/jobs-queues.md** for full details.

```bash
# All queues (one worker)
php artisan queue:work --queue=import_domains,domains,batch_links,remove_link_from_batch,delete_batch_links
```

- **import_domains** – bulk domain import.
- **domains** – domain health checks (after add, recheck, or import).
- **batch_links** – publish batch chunks to remote APIs.
- **remove_link_from_batch** – delete one link from all remotes and from batch.
- **delete_batch_links** – delete batch from all remotes, then delete batch locally.
