# Batch Domain Chunks – Optimized Schema

## Overview

The chunk-based schema stores up to **100 links per row** in JSON instead of 1 row per link. This reduces database rows and supports bulk API workflows.

| Scenario | Old (batch_domain_links) | New (batch_domain_chunks) |
|----------|--------------------------|---------------------------|
| 500 links × 10 domains | 5,000 rows | 50 rows (5 chunks × 10 domains) |
| 100 links × 50 domains | 5,000 rows | 50 rows |

---

## Tables

### `batch_domain_chunks`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | PK | |
| `batch_id` | FK | Batch |
| `domain_id` | FK | PBN domain |
| `chunk_index` | smallint | 0, 1, 2… per domain |
| `links_payload` | JSON | `[{url, keyword, nofollow}, ...]` max 100 |
| `results_payload` | JSON nullable | `[{status, remote_post_id?, error?}, ...]` from API response |
| `status` | enum | `pending` \| `processing` \| `completed` \| `partial` |
| `attempts` | tinyint | Retry count |
| `success_count` | smallint | Links succeeded in this chunk |
| `failed_count` | smallint | Links failed in this chunk |
| `sent_at` | timestamp | When sent to remote |
| `completed_at` | timestamp | When API response received |
| `error_message` | text | Chunk-level error |

**Unique:** `(batch_id, domain_id, chunk_index)`

---

## Batch Creation (Chunk Flow)

For each domain:

1. Split links into chunks of 100.
2. Create one `batch_domain_chunks` row per chunk with `links_payload`.

Example: 250 links, 3 domains → 9 rows (3 chunks × 3 domains).

---

## Migration

Run:

```bash
php artisan migrate
```

This creates `batch_domain_chunks`. The existing `batch_domain_links` table remains for the current 1-by-1 flow.

---

## Switching to Chunk Flow

To use the chunk-based flow:

1. `PublishBatchChunkJob` sends bulk payload to the PBN bulk API (POST `/hidden-links`).
2. `BatchController::store` creates `batch_domain_chunks` and dispatches jobs per chunk.
3. The remote PBN API returns per-link results in the same request; the job updates `results_payload` and chunk status.
