# WP Hidden Links API — Dashboard Integration Spec

**Plugin:** PBN Hidden Link Manager  
**API base (canonical):** `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1`  
**Version:** 1.1.x  
**Audience:** Hidden Links Management dashboard (new section, separate from Campaigns / Batches)

---

## 1. Purpose of the new dashboard section

Add a **separate section** in Hidden Links Management for publishing link batches directly to WordPress sites that run the **PBN Hidden Link Manager** plugin.

| Existing areas | New section |
|----------------|-------------|
| **Campaigns** — campaign domains & campaign flows | **WP Link Sites** (name TBD) — WP plugin domains only |
| **Batches** — existing batch/campaign publish pipeline | **WP Link Batches** — create / publish / delete link batches on WP sites |

### Rules

1. Domains in this section are **not** shared with Campaigns or the old Batches domain pool.
2. Each domain stores its own **API base URL** + **API key** from the WP plugin Settings page.
3. The dashboard talks to WordPress only through the endpoints in this document.
4. Links on the WP site stay **hidden** until the site owner places `[pbn_hidden_link_manager]` (shortcode). The API only stores/deletes rows in the WP database.

### Suggested dashboard domain fields

| Field | Example | Notes |
|-------|---------|--------|
| Domain / host | `yoursite.com` | Display only |
| API URL | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1` | No trailing slash preferred |
| API key | `(64-char key from plugin)` | Sent as Bearer / X-API-Key |
| Status | Connected / Failed | From `GET /status` |

---

## 2. Authentication (all endpoints)

Send **either** header (plugin accepts both):

```http
Authorization: Bearer YOUR_API_KEY
```

```http
X-API-Key: YOUR_API_KEY
```

| Code | Meaning |
|------|---------|
| `401` | Missing or invalid API key |
| `404` | Wrong path (route not found) |
| `405` | Wrong HTTP method |

---

## 2.1 HTTP vs HTTPS (important for the dashboard)

The plugin supports **both** `http://` and `https://`. Prefer HTTPS when the site has SSL. Fresh sites without SSL should use HTTP.

| Site | API URL to store |
|------|------------------|
| SSL installed | `https://domain.com/wp-json/pbn-hidden-link-manager/v1` |
| No SSL (fresh site) | `http://domain.com/wp-json/pbn-hidden-link-manager/v1` |

### Why `http://` sometimes returns `401`

The plugin does **not** reject HTTP. A `401` usually means the API key never arrived.

Common case:

1. App calls `http://domain.com/wp-json/...` with `Authorization: Bearer ...`
2. Host redirects `301/302` → `https://domain.com/...`
3. Many HTTP clients (Postman, curl, Guzzle by default) **drop the Authorization header** on redirect
4. WordPress receives the follow-up request **without** the key → plugin returns `401 Invalid API key`

### Dashboard / app rules

1. Store the scheme that matches the live site (`http` or `https`) per domain — do not hardcode HTTPS for every site.
2. Prefer the final URL that does **not** redirect (health-check both if unsure; save the one that returns `200` with the key).
3. When following redirects in your HTTP client, **re-attach** `Authorization` and/or `X-API-Key` on the redirected request — or disable redirects for WP API calls and use the correct scheme from the start.
4. Sending **both** headers can help some proxies:

```http
Authorization: Bearer YOUR_API_KEY
X-API-Key: YOUR_API_KEY
```

---

## 3. Endpoint quick reference

Base: `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1`

| Action | Method | Full URL |
|--------|--------|----------|
| Health check | `GET` | `{base}/status` |
| List links | `GET` | `{base}/hidden-links` |
| **Create links (bulk)** | `POST` | `{base}/hidden-links` |
| **Delete by URL** | `DELETE` | `{base}/hidden-links/by-url` |
| **Delete by batch ID** | `DELETE` | `{base}/hidden-links/by-batch-id` |
| Toggle shortcode output | `POST` | `{base}/hidden-links/toggle-visibility` |
| Block view source / inspect | `POST` | `{base}/hidden-links/toggle-inspect` |

### Important path note

Delete routes are under **`/hidden-links/...`**, not under `/v1/by-batch-id` alone.

- Correct: `.../v1/hidden-links/by-batch-id`  
- Wrong: `.../v1/by-batch-id` → `404 rest_no_route`

### Update (edit) links

There is **no** `PUT` / `PATCH` endpoint on the plugin.

To “update” a link from the dashboard:

1. `DELETE /hidden-links/by-url` with the old URL, then  
2. `POST /hidden-links` with the new URL/keyword/nofollow  

Or edit manually in **WP Admin → PBN Hidden Link Manager → Links**.

---

## 4. Endpoints in detail

### 4.1 Health check — connect domain

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/status` |
| **Body** | None |

**Success `200`:**

```json
{
  "status": true,
  "message": "API is operational.",
  "block_inspect": false,
  "show_hidden_links": true
}
```

**Unauthorized `401`:**

```json
{
  "code": "pbn_hlm_invalid_api_key",
  "message": "Invalid API key.",
  "data": { "status": 401 }
}
```

**Dashboard use:** “Test connection” / mark domain Connected.

---

### 4.2 List links — optional sync / debug

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links` |
| **Query** | `page` (default 1), `per_page` (max 100), `search` |

**Example:**  
`GET .../hidden-links?page=1&per_page=50&search=coupon`

**Success `200`:**

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "url": "https://client.com/page",
      "keyword": "anchor text",
      "nofollow": false,
      "batch_id": 149,
      "chunk_id": 0,
      "domain_id": 986,
      "sort_order": 0,
      "created_at": "2026-08-11T12:00:00.000000Z",
      "updated_at": "2026-08-11T12:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1
  }
}
```

---

### 4.3 Create links (bulk) — publish a WP link batch chunk

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links` |
| **Content-Type** | `application/json` |

**Request body:**

```json
{
  "payload": [
    {
      "url": "https://myclient.com/seo-page",
      "keyword": "best seo tools",
      "nofollow": false
    },
    {
      "url": "https://myclient.com/contact",
      "keyword": "contact us",
      "nofollow": true
    }
  ],
  "batch_id": 149,
  "chunk_id": 0,
  "domain_id": 986
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `payload` | Yes | Non-empty array (or JSON string that decodes to array) |
| `payload[].url` | Yes | Valid URL |
| `payload[].keyword` | No | Anchor text |
| `payload[].nofollow` | No | Boolean; default false |
| `batch_id` | Yes | Integer ≥ 0 — your dashboard batch / job id |
| `chunk_id` | Yes | Integer ≥ 0 — chunk index when splitting large batches |
| `domain_id` | No | Dashboard domain id for tracing |

**Success `201`:**

```json
{
  "status": true,
  "payload": [
    { "link_id": 101, "status": "success" },
    { "link_id": 102, "status": "success" }
  ],
  "success": 2,
  "failed": 0,
  "batch_id": 149,
  "chunk_id": 0
}
```

**Partial failure (still `201`, per-item status):**

```json
{
  "status": true,
  "payload": [
    { "link_id": 101, "status": "success" },
    {
      "link_id": null,
      "status": "failed",
      "url": "not-a-valid-url",
      "keyword": "bad"
    }
  ],
  "success": 1,
  "failed": 1,
  "batch_id": 149,
  "chunk_id": 0
}
```

**Critical:** Response `payload` order **must match** request order (map by index when storing remote `link_id`).

**Plugin behavior:**

- Bulk inserts append after current max `sort_order`.
- Duplicate POSTs create new rows (not idempotent).
- Links are stored only; front-end needs the shortcode.

**Recommended chunk size:** 25 links per request (same pattern as existing Link Manager jobs).

---

### 4.4 Delete by URL — remove one target URL from a site

| | |
|---|---|
| **Method** | `DELETE` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-url` |
| **Content-Type** | `application/json` |

**Body:**

```json
{
  "url": "https://myclient.com/seo-page"
}
```

**Success `200` (deleted):**

```json
{
  "status": true,
  "message": "1 link(s) deleted successfully.",
  "deleted_count": 1,
  "url": "https://myclient.com/seo-page"
}
```

**Success `200` (not found — still OK):**

```json
{
  "status": true,
  "message": "No links found with that URL.",
  "deleted_count": 0,
  "url": "https://myclient.com/seo-page"
}
```

**URL matching:** Plugin normalizes URLs (trim; trailing slash treated as equivalent for delete). Use the same URL string style you used on create when possible.

**Dashboard use:** User removes one link from a WP batch → call this on **each selected domain**.

---

### 4.5 Delete by batch ID — remove entire WP batch from a site

| | |
|---|---|
| **Method** | `DELETE` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-batch-id` |
| **Content-Type** | `application/json` |

**Body:**

```json
{
  "batch_id": 149
}
```

**Success `200`:**

```json
{
  "status": true,
  "message": "150 link(s) deleted successfully.",
  "deleted_count": 150,
  "batch_id": 149
}
```

**Dashboard use:**

- Delete whole WP link batch  
- Remove a domain from a batch (cleanup remote rows)  
- Retry failed cleanup jobs  

`batch_id` in the body is whatever integer the dashboard stored when publishing (your WP-batch id).

---

### 4.6 Toggle visibility (optional)

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/toggle-visibility` |

**Body:**

```json
{
  "show_hidden_links": true
}
```

When `false`, shortcode returns empty (links remain in DB).  
If a site returns `404` for this route, log and continue.

---

### 4.7 Block view source / inspect (plugin v1.3.0+)

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/toggle-inspect` |

**Body:**

```json
{
  "block_inspect": true
}
```

**Success `200`:**

```json
{
  "status": true,
  "block_inspect": true,
  "message": "Inspect / view-source blocking enabled."
}
```

Read current state from `GET /status` → `block_inspect`.  
Dashboard: [BLOCK_VIEW_SOURCE_INSPECT.md](BLOCK_VIEW_SOURCE_INSPECT.md), route `wp-sites.block-inspect`.

---

## 5. Suggested dashboard flows (new section)

### 5.1 Add WP domain

1. User pastes **API URL** + **API key** from WP plugin Settings.  
2. Dashboard calls `GET /status`.  
3. On `200` → save domain in **WP Link Sites** table (not campaign domains).

### 5.2 Create & publish WP link batch

1. User creates a **WP Link Batch** (links list + selected WP domains).  
2. Split links into chunks (e.g. 25).  
3. For each domain × chunk:  
   `POST /hidden-links` with `batch_id`, `chunk_id`, `domain_id`, `payload`.  
4. Store per-item `link_id` / failed status from response order.

### 5.3 Remove one link from batch

For each domain in the batch:  
`DELETE /hidden-links/by-url` with `{ "url": "..." }`.

### 5.4 Delete entire batch

For each domain in the batch:  
`DELETE /hidden-links/by-batch-id` with `{ "batch_id": <dashboard_batch_id> }`.

### 5.5 “Update” a link

`DELETE by-url` (old) → `POST /hidden-links` (new fields, same `batch_id` if desired).

---

## 6. Postman / cURL cheat sheet

Replace `YOUR_SITE` and `YOUR_API_KEY`.

### Status

```http
GET https://YOUR_SITE/wp-json/pbn-hidden-link-manager/v1/status
Authorization: Bearer YOUR_API_KEY
```

### Create bulk

```http
POST https://YOUR_SITE/wp-json/pbn-hidden-link-manager/v1/hidden-links
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "payload": [
    {
      "url": "https://example.com",
      "keyword": "test",
      "nofollow": false
    }
  ],
  "batch_id": 1,
  "chunk_id": 0,
  "domain_id": 1
}
```

### Delete by URL

```http
DELETE https://YOUR_SITE/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-url
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "url": "https://example.com"
}
```

### Delete by batch

```http
DELETE https://YOUR_SITE/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-batch-id
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "batch_id": 1
}
```

---

## 7. Optional aliases (same handlers)

| Style | Base |
|-------|------|
| Canonical (use this) | `https://yoursite.com/wp-json/pbn-hidden-link-manager/v1` |
| Short REST alias | `https://yoursite.com/wp-json/pbn/v1` |
| Legacy rewrite | `https://yoursite.com/api` (after permalinks flush) |

Prefer storing the **canonical** `/wp-json/pbn-hidden-link-manager/v1` base in the new dashboard section.

HTTP and HTTPS are both supported (match the live site). See **§2.1** for redirect/`401` pitfalls when using `http://` on sites that force SSL.

---

## 8. Implementation checklist (dashboard)

- [x] New nav section separate from Campaigns / Batches  
- [x] Separate domains table / model for WP plugin sites (`wp_sites`, `WpSite`)  
- [x] Domain CRUD + health check (`GET /status`)  
- [x] Store `http` or `https` per domain (no global HTTPS-only rule)  
- [x] HTTP client: re-send API key after redirects, or avoid redirects (`WpApiService`)  
- [x] WP Link Batch create UI  
- [x] Publish job: chunked `POST /hidden-links` (`PublishWpBatchChunkJob` / `wp_batch_links`)  
- [x] Delete one link: `DELETE /hidden-links/by-url` on all batch domains  
- [x] Delete batch: `DELETE /hidden-links/by-batch-id` on all batch domains  
- [x] Map response payload by index; store remote `link_id`  
- [x] Do not reuse campaign domain records for this section  
- [x] Block inspect: read `block_inspect` from `GET /status`; toggle via `POST /hidden-links/toggle-inspect`  

### Implemented in Laravel

| Area | Route names | Controller |
|------|-------------|------------|
| WP Sites | `wp-sites.*` | `WpSiteController` |
| WP Batches | `wp-batches.*` | `WpBatchController` |
| Block inspect | `wp-sites.block-inspect*`, `wp-sites.toggle-inspect` | `WpBlockInspectController` |

**Paths:** `/wp-sites/*`, `/wp-batches/*`, `/wp-sites/block-inspect` — see `routes/web.php`.

**Jobs:** `ToggleWpInspectJob` on `wp_sites` queue.

**Models:** `WpSite`, `WpBatch`, `WpLink`, `WpBatchSiteChunk`, `WpSiteImport`.

**API service:** `App\Services\WpApiService` — same endpoints as this document; accepts `WpSite` with `api_url` base `.../wp-json/pbn-hidden-link-manager/v1`.

**Queues (Supervisor):** `wp_batch_links`, `delete_wp_batch_links`, `remove_link_from_wp_batch`, `wp_sites`, `import_wp_sites` — see `docs/supervisor.conf.example`.

**Plugin guide:** [docs/WORDPRESS_PLUGIN_GUIDE.md](docs/WORDPRESS_PLUGIN_GUIDE.md)

---

## 9. Related plugin shortcode (site owner)

Links appear on the front end only when the WP site includes:

```text
[pbn_hidden_link_manager]
```

Optional: `batch_id="149"` or `limit="50"`.

---

*Source of truth for WordPress behavior: plugin `pbn-hidden-link-manager` + `WORDPRESS_PLUGIN_GUIDE.md`.*
