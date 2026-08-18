# WordPress Hidden Links Plugin — Build Guide

This document is the **source of truth** for building a WordPress plugin that works with **Diamond PBN / Hidden Links Management** (the Laravel Link Manager). Implement the same REST API routes and behavior described here so batches, campaigns, health checks, and deletions work without custom code per site.

---

## 1. Goal

| Who | What they do |
|-----|----------------|
| **Link Manager (Laravel)** | Sends bulk link jobs to many PBN sites — create, delete by URL, delete by batch |
| **WordPress plugin (each PBN site)** | Stores links in the database, exposes REST API for Link Manager, wp-admin UI for manual links — **links are not shown anywhere on the site unless a shortcode is placed** |

**Hidden links rule:** Links created via API or wp-admin stay **off the public site by default**. They only appear on pages/posts/widgets where the site owner inserts the shortcode (see [Section 7](#7-frontend-rendering-shortcode-only--hidden-output)). Do **not** auto-inject links into `wp_footer`, headers, or theme templates.

The plugin is installed on **each PBN WordPress site**. In Link Manager you add the domain with:

```text
API URL:  http://your-pbn-site.com/wp-json/pbn-hidden-link-manager/v1
          — or —
          http://your-pbn-site.com/api   (if you register legacy routes under /api)

API Key:  (copied from plugin Settings after you generate it)
```

Use **`http://`** if the site has no SSL. Link Manager supports both HTTP and HTTPS.

---

## 2. Recommended plugin structure

```text
wp-content/plugins/pbn-hidden-link-manager/
├── pbn-hidden-link-manager.php   # Bootstrap, constants
├── includes/
│   ├── class-database.php        # Table create/migrate
│   ├── class-api-key.php         # Generate, validate, hash storage
│   ├── class-rest-controller.php # All REST routes
│   ├── class-link-repository.php # CRUD + ordering
│   └── class-shortcode.php       # [pbn_hidden_link_manager] — only public output path
├── admin/
│   ├── class-admin-menu.php
│   ├── views/settings.php        # API key + options
│   └── views/links-list.php      # Manual links + drag reorder
└── assets/
    ├── admin.css
    ├── admin.js                  # SortableJS or WP jQuery UI sortable
    └── frontend.css              # .pbn-hidden-link-manager hide styles (load when shortcode used)
```

**Plugin name:** `PBN Hidden Link Manager`  
**Plugin slug / folder:** `pbn-hidden-link-manager`  
**Main file:** `pbn-hidden-link-manager.php`  
**Text domain:** `pbn-hidden-link-manager`  
**REST namespace:** `pbn-hidden-link-manager/v1` (optional shorter alias: `pbn/v1`) with optional legacy routes under `/api/...` if you want URLs to end in `/api` exactly like the Laravel docs.

---

## 3. Admin features (WordPress dashboard)

### 3.1 Settings — API key (required)

**Location:** `WP Admin → PBN Hidden Link Manager → Settings`

| Feature | Behavior |
|---------|----------|
| **Generate API key** | Button creates a long random key (e.g. 64 chars). Show **once** in full; store **hashed** in `wp_options` (use `password_hash` / `hash_equals` on verify). |
| **Regenerate** | Invalidates old key; user must update Link Manager domain record. |
| **Copy key** | One-click copy for pasting into Link Manager → Domains → API key. |
| **API base URL display** | Show the exact URL to paste in Link Manager, e.g. `http://mysite.com/wp-json/pbn-hidden-link-manager/v1`. |
| **Shortcode instructions** | Display the shortcode to copy, default: `[pbn_hidden_link_manager]`. Explain: links are hidden until this is added to a page, post, or widget. |

**Link Manager sends auth as:**

```http
Authorization: Bearer YOUR_API_KEY
X-API-Key: YOUR_API_KEY
```

Accept **either** header in the plugin middleware.

---

### 3.2 Manual link manager (required)

**Location:** `WP Admin → PBN Hidden Link Manager → Links`

| Feature | Behavior |
|---------|----------|
| **Add link** | Fields: Target URL, Keyword (anchor text), Nofollow (checkbox). |
| **New link = first** | On create, set `sort_order = 0` and increment all other rows by 1 (or use negative timestamps so newest is first). |
| **Drag and drop reorder** | Sortable list; on save, persist `sort_order` (0 = first in shortcode output). |
| **Edit link** | Inline or modal: change URL, keyword, nofollow. |
| **Delete link** | Remove row from DB and refresh frontend cache if used. |
| **List columns** | Order, URL, Keyword, Nofollow, Batch ID (if from API), Created, Actions |

**Frontend output:** Links are **not** rendered site-wide. They only output when `[pbn_hidden_link_manager]` is present on that page (see Section 7). In wp-admin, show a reminder: *“Add `[pbn_hidden_link_manager]` to a page or widget to display links on the front end.”*

---

### 3.3 Batch / bulk from Link Manager (required)

The plugin does **not** need a separate “import UI” for batches. Link Manager calls **`POST /hidden-links`** with a JSON payload. The plugin must:

1. Validate API key  
2. Parse `payload` array  
3. Insert each link with the given `batch_id`, `chunk_id`, optional `domain_id`  
4. Return per-item success/failure in the **same order** as input  
5. Assign `sort_order` so batch links append after existing links **or** follow your “new first” rule consistently (document one rule and stick to it; recommended: **API bulk inserts get sort_order after current max**, manual admin “add” still goes to top)

Batch-created links are **stored only** — they do not appear on the front end until `[pbn_hidden_link_manager]` is on a page.

---

### 3.4 Shortcode placement (site owner workflow)

After links exist (manual or from Link Manager batch), the **PBN site owner** must place the shortcode where links should load:

| Where | How |
|-------|-----|
| **Page / Post** | Edit in block editor → Shortcode block → `[pbn_hidden_link_manager]` |
| **Classic editor** | Paste `[pbn_hidden_link_manager]` in content |
| **Widget** | Text/HTML widget with the shortcode |
| **Theme template** | `<?php echo do_shortcode('[pbn_hidden_link_manager]'); ?>` only if intentional |

**No shortcode on a page = no links on that page**, even if hundreds exist in the database.

---

### 3.5 Optional admin features

| Feature | Notes |
|---------|--------|
| **Enable / disable shortcode output globally** | Option in Settings. When off, `[pbn_hidden_link_manager]` returns empty string (links stay in DB). Optional API: `POST /hidden-links/toggle-visibility` (Link Manager may call this on campaign domains). |
| **View by batch** | Filter list by `batch_id` for debugging. |
| **Logs** | Last 50 API requests (method, batch_id, IP, status) for support. |

---

## 4. Database schema

Use a custom table `{prefix}pbn_hidden_links`:

| Column | Type | Notes |
|--------|------|--------|
| `id` | BIGINT UNSIGNED PK AI | Local link ID returned as `link_id` in API |
| `url` | VARCHAR(2048) | Target URL (indexed prefix or hash for lookup) |
| `keyword` | VARCHAR(512) NULL | Anchor text |
| `nofollow` | TINYINT(1) DEFAULT 0 | 0 = follow, 1 = nofollow |
| `batch_id` | BIGINT UNSIGNED NULL | From Link Manager batch/campaign id |
| `chunk_id` | INT UNSIGNED NULL | Chunk index from Link Manager |
| `domain_id` | BIGINT UNSIGNED NULL | Optional Link Manager domain id |
| `sort_order` | INT UNSIGNED DEFAULT 0 | Display order (lower = first) |
| `status` | VARCHAR(20) DEFAULT 'active' | active / deleted |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**Indexes:**

- `INDEX (batch_id)`  
- `INDEX (url(191))` or normalized URL hash for delete-by-url  
- `INDEX (sort_order)`  

Store API key hashed in `wp_options` key `pbn_hidden_link_manager_api_key_hash`.

---

## 5. REST API endpoints (must match Link Manager)

Base URL examples:

- WordPress REST: `https://site.com/wp-json/pbn-hidden-link-manager/v1`
- Legacy style (if you add rewrite): `https://site.com/api`

All paths below are **relative to the API base** Link Manager stores in `api_url`.

### 5.1 Health check

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/status` |
| **Auth** | Required |

**Response 200:**

```json
{
  "status": true,
  "message": "API is operational."
}
```

**Response 401:**

```json
{
  "message": "Invalid API key."
}
```

---

### 5.2 List links (optional for Link Manager; useful for debugging)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/hidden-links` |
| **Query** | `page`, `per_page` (max 100), `search` |

**Response 200:**

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

### 5.3 Create links (bulk) — **main Link Manager endpoint**

| | |
|---|---|
| **Method** | `POST` |
| **Path** | `/hidden-links` |
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

**Validation:**

- `payload` required, non-empty array (or JSON string that decodes to array)  
- Each item: `url` required, valid URL; `keyword` optional; `nofollow` optional boolean  
- `batch_id` required integer ≥ 0  
- `chunk_id` required integer ≥ 0  

**Response 201:**

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

**Partial failure example:**

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

**Important:** Response `payload` array **must match input order** — Link Manager stores results per chunk index.

**Real flow example:**

1. Link Manager batch **#149** has 500 links and 20 domains.  
2. It splits links into chunks of 25 and queues `PublishBatchChunkJob` per domain.  
3. For domain `dotingdads.shop`, it sends:

```http
POST http://dotingdads.shop/wp-json/pbn-hidden-link-manager/v1/hidden-links
Authorization: Bearer abc123...
Content-Type: application/json

{
  "payload": [ /* 25 links */ ],
  "batch_id": 149,
  "chunk_id": 3,
  "domain_id": 986
}
```

4. Plugin inserts 25 rows with `batch_id = 149` and returns success/fail per row.

---

### 5.4 Delete by URL — **single link removal**

| | |
|---|---|
| **Method** | `DELETE` (must be DELETE, not GET) |
| **Path** | `/hidden-links/by-url` |
| **Body** | JSON `{ "url": "..." }` |

**Example:**

```http
DELETE http://dotingdads.shop/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-url
Authorization: Bearer abc123...
Content-Type: application/json

{
  "url": "https://myclient.com/seo-page"
}
```

**Response 200 (found):**

```json
{
  "status": true,
  "message": "1 link(s) deleted successfully.",
  "deleted_count": 1,
  "url": "https://myclient.com/seo-page"
}
```

**Response 200 (not found — still OK):**

```json
{
  "status": true,
  "message": "No links found with that URL.",
  "deleted_count": 0,
  "url": "https://myclient.com/seo-page"
}
```

**When Link Manager uses this:** User deletes one link from a batch (trash icon). Job calls this on **every domain** in that batch with the **exact** target URL.

**URL matching tip:** Normalize URLs the same way on insert and delete (trim, optional trailing slash policy). Document whether `https://a.com` and `https://a.com/` are the same.

---

### 5.5 Delete by batch ID — **bulk cleanup**

| | |
|---|---|
| **Method** | `DELETE` |
| **Path** | `/hidden-links/by-batch-id` |
| **Body** | JSON `{ "batch_id": 149 }` |

**Example:**

```http
DELETE http://dotingdads.shop/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-batch-id
Authorization: Bearer abc123...
Content-Type: application/json

{
  "batch_id": 149
}
```

**Response 200:**

```json
{
  "status": true,
  "message": "150 link(s) deleted successfully.",
  "deleted_count": 150,
  "batch_id": 149
}
```

**When Link Manager uses this:**

- Delete entire batch or campaign  
- Remove domain from batch (cleanup remote links)  
- Failed batch delete retries  

Campaigns use the **same endpoint**; `batch_id` in the body is the **campaign ID** on campaign target domains.

---

### 5.6 Toggle visibility (optional)

| | |
|---|---|
| **Method** | `POST` |
| **Path** | `/hidden-links/toggle-visibility` |
| **Body** | `{ "show_hidden_links": true }` |

If not implemented, return **404** — Link Manager logs and continues.

---

## 6. What Link Manager does **not** call (plugin-only)

| Action | How it works |
|--------|----------------|
| **Edit link (change keyword/URL)** | No `PUT/PATCH` from Link Manager. Edit in **wp-admin**, or delete via API + POST again. |
| **Reorder from Link Manager** | Reorder is **plugin admin only** (`sort_order` + drag-drop); affects shortcode output order. |
| **Show links on site** | **Shortcode only** — `[pbn_hidden_link_manager]` on a page/post/widget. |
| **Single link POST** | Legacy `/links` exists in old code; batches use **`POST /hidden-links` only**. |

---

## 7. Frontend rendering — shortcode only + hidden output

Links must be **hidden** (not visible to normal visitors) and output **only** through a shortcode. Never auto-print links in `wp_footer`, `wp_head`, or site-wide hooks.

### 7.1 Shortcode

| | |
|---|---|
| **Tag** | `[pbn_hidden_link_manager]` |
| **Registration** | `add_shortcode( 'pbn_hidden_link_manager', [ $shortcode, 'render' ] );` |
| **When it runs** | Only on pages/posts/widgets that contain the shortcode |

**Optional attributes** (implement if useful):

```text
[pbn_hidden_link_manager]
[pbn_hidden_link_manager batch_id="149"]
[pbn_hidden_link_manager limit="50"]
```

- `batch_id` — output only links from that batch (debug / split placements)  
- `limit` — cap number of anchors rendered  

If global “output disabled” option is off, return `''` (empty string).

### 7.2 HTML output (hidden from UI, present in DOM)

Wrap links in a container with a **hidden** CSS class. Links stay in the HTML for crawlers; visitors do not see them.

```html
<div class="pbn-hidden-link-manager" aria-hidden="true">
  <a href="https://client.com/page" rel="nofollow">anchor text</a>
  <a href="https://client.com/other">another keyword</a>
</div>
```

**Default CSS** (enqueue `assets/frontend.css` only when shortcode is used on the page):

```css
.pbn-hidden-link-manager {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
```

Alternative: `display: none;` — document which approach the plugin uses (screen-reader–safe off-screen clip is common for “hidden” PBN links).

**Rules:**

- Output links in **`sort_order ASC`** (matches admin drag order; new manual links first if `sort_order = 0` at top).  
- Omit `rel="nofollow"` when `nofollow = 0`.  
- Only render rows with `status = active`.  
- Escape all URLs and keywords (`esc_url`, `esc_html`).

### 7.3 Shortcode render flow (PHP sketch)

```php
public function render( $atts = [] ) {
    if ( ! get_option( 'pbn_hidden_link_manager_output_enabled', true ) ) {
        return '';
    }

    $links = $this->repository->get_active_links( $atts ); // ordered by sort_order ASC

    if ( empty( $links ) ) {
        return '';
    }

    ob_start();
    echo '<div class="pbn-hidden-link-manager" aria-hidden="true">';
    foreach ( $links as $link ) {
        $rel = $link->nofollow ? ' rel="nofollow"' : '';
        printf(
            '<a href="%s"%s>%s</a>',
            esc_url( $link->url ),
            $rel,
            esc_html( $link->keyword ?: $link->url )
        );
    }
    echo '</div>';
    return ob_get_clean();
}
```

### 7.4 What NOT to do

| Avoid | Why |
|-------|-----|
| Auto-inject on every page load | Links would leak outside intended pages |
| Visible styling by default | Defeats “hidden links” purpose |
| Output in admin bar / feeds | Unintended exposure |

### 7.5 Typical site setup

1. Link Manager posts 200 links via API → stored in DB.  
2. Site owner creates a **Private** page titled “Links holder”, content: `[pbn_hidden_link_manager]`.  
3. Front end of that page contains hidden `<a>` tags; other pages show nothing.  
4. Reorder in wp-admin → shortcode output order updates on next view.

---

## 8. WordPress REST route registration (example)

Register in `rest_api_init`:

```php
register_rest_route('pbn-hidden-link-manager/v1', '/status', [
    'methods'  => 'GET',
    'callback' => [ $controller, 'status' ],
    'permission_callback' => [ $controller, 'verify_api_key' ],
]);

register_rest_route('pbn-hidden-link-manager/v1', '/hidden-links', [
    [
        'methods'  => 'GET',
        'callback' => [ $controller, 'index' ],
        'permission_callback' => [ $controller, 'verify_api_key' ],
    ],
    [
        'methods'  => 'POST',
        'callback' => [ $controller, 'store_bulk' ],
        'permission_callback' => [ $controller, 'verify_api_key' ],
    ],
]);

register_rest_route('pbn-hidden-link-manager/v1', '/hidden-links/by-url', [
    'methods'  => 'DELETE',
    'callback' => [ $controller, 'destroy_by_url' ],
    'permission_callback' => [ $controller, 'verify_api_key' ],
]);

register_rest_route('pbn-hidden-link-manager/v1', '/hidden-links/by-batch-id', [
    'methods'  => 'DELETE',
    'callback' => [ $controller, 'destroy_by_batch' ],
    'permission_callback' => [ $controller, 'verify_api_key' ],
]);
```

**Critical for DELETE:** WordPress must read JSON body on DELETE requests. In `destroy_by_url` / `destroy_by_batch`, use:

```php
$body = json_decode( $request->get_body(), true );
```

Do **not** register these as GET routes.

**Optional legacy alias:** Add rewrite or duplicate routes so `api_url = http://site.com/api` maps to the same handlers (many live domains use `/api` not `/wp-json/pbn-hidden-link-manager/v1`).

---

## 9. Connecting a site in Link Manager

1. Install and activate plugin on WordPress.  
2. **PBN Hidden Link Manager → Settings → Generate API key** → copy key.  
3. In Link Manager → **Domains → Add domain:**  
   - **Domain:** `dotingdads.shop`  
   - **API URL:** `http://dotingdads.shop/wp-json/pbn-hidden-link-manager/v1` (or your `/api` base)  
   - **API key:** pasted key  
4. Run **Health check** → expects `GET /status` → **Connected**.  
5. Create a batch, select domain, publish → `POST /hidden-links` runs per chunk.

---

## 10. Testing checklist

Use the same tests Link Manager relies on:

```bash
# 1. Status
curl -H "Authorization: Bearer KEY" "http://site.com/wp-json/pbn-hidden-link-manager/v1/status"

# 2. Create bulk
curl -X POST "http://site.com/wp-json/pbn-hidden-link-manager/v1/hidden-links" \
  -H "Authorization: Bearer KEY" \
  -H "Content-Type: application/json" \
  -d '{"payload":[{"url":"https://example.com","keyword":"test","nofollow":false}],"batch_id":1,"chunk_id":0}'

# 3. Delete by URL
curl -X DELETE "http://site.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-url" \
  -H "Authorization: Bearer KEY" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}'

# 4. Delete by batch
curl -X DELETE "http://site.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/by-batch-id" \
  -H "Authorization: Bearer KEY" \
  -H "Content-Type: application/json" \
  -d '{"batch_id":1}'
```

| Test | Expected |
|------|----------|
| No API key | 401 |
| Wrong method on delete route (GET) | 405 |
| Invalid URL in payload | Row in response with `"status": "failed"` |
| Duplicate POST same batch | Inserts allowed or idempotent — document behavior |
| HTTP site (no SSL) | Works with `http://` api_url |

---

## 11. Implementation phases

| Phase | Deliverable |
|-------|-------------|
| **Phase 1** | DB table, API key generate/verify, `GET /status` |
| **Phase 2** | `POST /hidden-links`, `DELETE /hidden-links/by-url`, `DELETE /hidden-links/by-batch-id` |
| **Phase 3** | Admin UI: manual add/edit/delete, drag reorder, new link first |
| **Phase 4** | Shortcode `[pbn_hidden_link_manager]`, hidden CSS, optional global on/off |
| **Phase 5** | Legacy `/api` routes, logging, import filter by batch in admin |

---

## 12. Quick reference (Link Manager ↔ Plugin)

| Link Manager action | HTTP | Plugin endpoint |
|--------------------|------|-----------------|
| Health check | `GET` | `/status` |
| Publish batch/campaign chunk | `POST` | `/hidden-links` |
| Remove one link from batch | `DELETE` | `/hidden-links/by-url` |
| Delete batch/campaign on site | `DELETE` | `/hidden-links/by-batch-id` |
| Toggle hidden (optional) | `POST` | `/hidden-links/toggle-visibility` |
| Block view source / inspect (v1.3.0+) | `POST` | `/hidden-links/toggle-inspect` |

---

## 13. Related docs in this repo

- [`docs/API.md`](./API.md) — Full request/response examples (Laravel reference app)  
- [`WP_HIDDEN_LINKS_API_ENDPOINTS.md`](../WP_HIDDEN_LINKS_API_ENDPOINTS.md) — Dashboard integration spec; **implemented** as WP Sites + WP Batches (`/wp-sites/*`, `/wp-batches/*`)  
- [`BLOCK_VIEW_SOURCE_INSPECT.md`](../BLOCK_VIEW_SOURCE_INSPECT.md) — Inspect blocking API; **implemented** at `/wp-sites/block-inspect`  
- [`PBN_API_SPECIFICATION.md`](../PBN_API_SPECIFICATION.md) — Integration notes for PBN domains  
- Link Manager service: `app/Services/PbnApiService.php` — Exact URLs and methods the tool calls for PBN domains  
- WP Link Manager service: `app/Services/WpApiService.php` — Same contract for WordPress plugin sites (`wp-json/pbn-hidden-link-manager/v1`)

Build the WordPress plugin to satisfy **Section 5** and **Section 3**; Link Manager will create, delete, and health-check links without further changes.
