# Jobs & Queues

This document lists all queue jobs in the application, which queue each one uses, where they are dispatched from, and why they are used.

---

## Quick reference

| Job | Queue name | Dispatched from | Purpose |
|-----|------------|-----------------|---------|
| **PublishBatchChunkJob** | `batch_links` | BatchController (create batch, publish pending) | Publish a chunk of links to remote domains via API |
| **DeleteBatchJob** | `delete_batch_links` | BatchController (delete batch) | Remove batch links from all remote sites, then delete the batch locally |
| **RemoveLinkFromBatchJob** | `remove_link_from_batch` | BatchController (delete single link) | Remove one link from all remote sites, then from the batch |
| **DomainHealthCheckJob** | `domains` | DomainController (add domain, recheck), ImportDomainsJob | Check if a domain is reachable and API key is valid |
| **ImportDomainsJob** | `import_domains` | DomainController (bulk import) | Parse Excel/CSV and create domains; queues health check per domain |

---

## 1. PublishBatchChunkJob

- **Queue:** `batch_links`
- **Class:** `App\Jobs\PublishBatchChunkJob`

### Where it is used

- **BatchController::store()** — When a new batch is created. One job is dispatched per `BatchDomainChunk` (each chunk = one domain’s portion of links), with a staggered delay between jobs.
- **BatchController::publishPending()** — When the user clicks “Publish pending chunks” on a batch. Dispatches one job per pending chunk, again with a staggered delay.

### Why it is used

Publishing many links to many domains can be slow and can fail partway through. Running this in a queue:

- Keeps the HTTP request fast (no long wait for all API calls).
- Allows retries (job has `tries = 3`) if a remote API is temporarily down.
- Spreads load with a delay between chunks (configurable via `services.pbn.link_delay_seconds`).
- Uses a unique job ID per chunk so the same chunk is not published twice.

### What it does

- Sends the chunk’s `links_payload` to the remote domain via **POST /hidden-links** (see `api.md`).
- Updates the chunk’s `results_payload`, `success_count`, `failed_count`, and `status`.
- Updates the batch’s `processed_count`, `success_count`, `failed_count`, and overall status.

### Run the queue

```bash
php artisan queue:work --queue=batch_links
```

---

## 2. DeleteBatchJob

- **Queue:** `delete_batch_links`
- **Class:** `App\Jobs\DeleteBatchJob`

### Where it is used

- **BatchController::destroy()** — When the user deletes a batch (e.g. from batch list or batch detail). The controller dispatches this job instead of deleting the batch immediately.

### Why it is used

Before removing the batch from the app, all links for that batch must be removed from every remote domain (via **DELETE /hidden-links/by-batch-id**). Doing that in the request would:

- Make the request very slow (one API call per domain, each with long timeout).
- Risk timeouts or failures with no easy retry.

By using a job:

- The user gets an immediate “Batch delete queued” response.
- The job runs in the background, calls the delete-by-batch-id API for each domain, then deletes the batch locally.
- If one domain fails, the job logs it and continues; the batch is still deleted locally so it disappears from the dashboard.

### What it does

- Loads the batch and its chunks (per domain).
- For each domain: calls **DELETE /hidden-links/by-batch-id** with the batch ID (see `api.md`).
- On API failure for a domain: logs a warning and continues.
- After all domains are processed: deletes the batch (and related chunks/links via cascade).

### Run the queue

```bash
php artisan queue:work --queue=delete_batch_links
```

---

## 3. RemoveLinkFromBatchJob

- **Queue:** `remove_link_from_batch`
- **Class:** `App\Jobs\RemoveLinkFromBatchJob`

### Where it is used

- **BatchController::destroyLink()** — When the user clicks the trash icon to delete a **single link** from the “All Links” table on the batch detail page.

### Why it is used

That single link may exist on many remote domains. The app must:

1. Remove the link from every remote domain (via **DELETE /hidden-links/by-url** with the link’s URL).
2. Then remove the link from the batch (chunk payloads and link record).

Doing this in the request would be slow and fragile. The job:

- Runs in the background so the request returns quickly.
- Calls the API per domain; on failure for one domain it logs and continues, then still removes the link from the batch.

### What it does

- Finds the link’s position in the batch and in each chunk.
- For each domain (chunk): calls **DELETE /hidden-links/by-url** with the link’s URL.
- Updates each chunk’s `links_payload` and `results_payload` (removes the link), and adjusts counts.
- Decrements batch totals and deletes the link record.

### Run the queue

```bash
php artisan queue:work --queue=remove_link_from_batch
```

---

## 4. DomainHealthCheckJob

- **Queue:** `domains`
- **Class:** `App\Jobs\DomainHealthCheckJob`

### Where it is used

- **DomainController::store()** — After a single domain is added; dispatches one job for that domain.
- **DomainController::recheck()** — When the user clicks “Re-check” for a domain; dispatches one job for that domain.
- **ImportDomainsJob** — After each domain is created during bulk import; dispatches one job per new/updated domain.

### Why it is used

New domains should not be shown as “connected” until the app has verified they are reachable and the API key works. The health check:

- Calls the remote **GET /status** endpoint (see `api.md`).
- Sets the domain to `active` if the call succeeds, or `error` and stores `last_health_error` if it fails.
- Uses retries (e.g. 5 attempts with backoff) so temporary network or server issues don’t immediately mark the domain as failed.
- Uses a cache lock so only one health check runs per domain at a time, even if several jobs are queued.

### What it does

- Acquires a lock for the domain.
- Calls `PbnApiService::ping($domain)` (GET `/status`).
- On success: sets `status = 'active'`, clears `last_health_error`, updates `last_checked_at`.
- On failure: updates `last_health_error` and either re-releases the job with delay (if retries left) or marks the domain as `error` when retries are exhausted.

### Run the queue

```bash
php artisan queue:work --queue=domains
```

---

## 5. ImportDomainsJob

- **Queue:** `import_domains`
- **Class:** `App\Jobs\ImportDomainsJob`

### Where it is used

- **DomainController::import()** — When the user uploads an Excel/CSV file on the Domains page to bulk import domains. One job is dispatched per import (per file).

### Why it is used

Parsing a large file and creating many domains (and then queuing a health check for each) can be slow. Running it in a job:

- Keeps the upload request quick.
- Processes the file in the background.
- Updates the `DomainImport` record with status and counts.
- Dispatches **DomainHealthCheckJob** for each created/updated domain so they are checked asynchronously.

### What it does

- Reads the stored file (Excel/CSV), parses rows (domain, api_url, optional api_key).
- For each row: creates or updates a `Domain` with `status = 'inactive'` and dispatches **DomainHealthCheckJob** for that domain.
- Updates the import record with `status` (e.g. `completed`), `imported_count`, and `skipped_count`.

### Run the queue

```bash
php artisan queue:work --queue=import_domains
```

---

## Running all queues

To process every queue in one worker (order = priority):

```bash
php artisan queue:work --queue=import_domains,domains,batch_links,remove_link_from_batch,delete_batch_links
```

Or run separate workers per queue if you want different concurrency or scaling per job type.
