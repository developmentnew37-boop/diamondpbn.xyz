# API Documentation

This document describes all API endpoints for the Hidden Links application. Use it to integrate with the API and to test endpoints.

---

## Base URL

- **Local:** `http://127.0.0.1:8000/api`  
- **Production:** `https://your-domain.com/api`

All endpoints below are relative to this base URL (e.g. `/api/status` → `GET {base}/status`).

---

## Authentication

Every API request **must** include a valid API key. You can generate API keys in the app: **Settings → Generate API Key**.

### How to send the API key

Use **one** of these methods:

| Method | Example |
|--------|--------|
| **Bearer token (recommended)** | `Authorization: Bearer your_api_key_here` |
| **Custom header** | `X-API-Key: your_api_key_here` |

### Example with cURL

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" "http://127.0.0.1:8000/api/status"
```

```bash
curl -H "X-API-Key: YOUR_API_KEY" "http://127.0.0.1:8000/api/status"
```

### Unauthorized responses

| HTTP | Body |
|------|------|
| **401** | `{"message": "API key required."}` — No key sent |
| **401** | `{"message": "Invalid API key."}` — Key missing or invalid |

---

## Response format

- All responses are **JSON**.
- Success responses include `"status": true` where applicable.
- Error responses use standard HTTP status codes (401, 422, etc.) and a `message` (and sometimes other fields) in the body.

---

## Endpoints

### 1. Check API status

Simple health check. Use it to verify your API key and that the API is up.

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `/status` |
| **Auth** | Required (API key) |
| **Request body** | None |

#### Response (200 OK)

```json
{
  "status": true,
  "message": "API is operational."
}
```

#### Example

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" "http://127.0.0.1:8000/api/status"
```

---

### 2. List hidden links

Returns hidden links with optional search and pagination.

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `/hidden-links` |
| **Auth** | Required (API key) |
| **Request body** | None |

#### Query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | integer | 50 | Items per page (max 100) |
| `page` | integer | 1 | Page number |
| `search` | string | — | Filter by URL or keyword (partial match) |

#### Response (200 OK)

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "url": "https://example.com/page",
      "keyword": "example keyword",
      "nofollow": false,
      "chunk_id": 0,
      "batch_id": 1,
      "domain_id": null,
      "status": "success",
      "created_at": "2026-03-01T12:00:00.000000Z",
      "updated_at": "2026-03-01T12:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 50,
    "total": 230
  }
}
```

#### Example

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" "http://127.0.0.1:8000/api/hidden-links?per_page=10&page=1"
curl -H "X-API-Key: YOUR_API_KEY" "http://127.0.0.1:8000/api/hidden-links?search=casino"
```

---

### 3. Create hidden links (bulk)

Creates multiple hidden links in one request. Each item in the payload becomes one row (or one failed entry in the response).

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/hidden-links` |
| **Auth** | Required (API key) |
| **Content-Type** | `
 (recommended) or form body with JSON `payload` |

#### Request body (JSON)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `payload` | array or JSON string | **Yes** | Array of link objects (see below) |
| `batch_id` | integer (≥ 0) | **Yes** | Batch identifier (e.g. 1, 2, 7) |
| `chunk_id` | integer (≥ 0) | **Yes** | Chunk identifier within the batch (e.g. 0, 1, 2) |
| `domain_id` | integer (≥ 1) or null | No | Optional domain identifier; omit or `null` if not used |

**Each item in `payload`:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | **Yes** | Valid full URL (e.g. `https://example.com`) |
| `keyword` | string or null | No | Keyword for the link |
| `nofollow` | boolean | No | Default `false` |

#### Valid request body example

```json
{
  "payload": [
    {
      "url": "https://www.example.com",
      "keyword": "example",
      "nofollow": false
    },
    {
      "url": "https://www.another-site.com/page",
      "keyword": "another keyword",
      "nofollow": true
    }
  ],
  "batch_id": 7,
  "chunk_id": 0,
  "domain_id": 5
}
```

- `payload` can be sent as a **JSON array** (as above) or as a **JSON string** (e.g. form field `payload` = `"[{\"url\":\"...\",\"keyword\":\"...\"}]"`). The API accepts both.

#### Response (201 Created)

```json
{
  "status": true,
  "payload": [
    { "link_id": 101, "status": "success" },
    { "link_id": 102, "status": "success" },
    {
      "link_id": null,
      "status": "failed",
      "url": "not-a-valid-url",
      "keyword": "bad"
    }
  ],
  "success": 2,
  "failed": 1,
  "batch_id": 7,
  "chunk_id": 0
}
```

- `payload`: one entry per input item — either `link_id` + `status: "success"` or `status: "failed"` with `url` and `keyword` for debugging.
- `success`: number of links created.
- `failed`: number of items that failed (e.g. invalid URL).

#### Validation error (422)

- Missing or invalid fields (e.g. missing `payload`, invalid `batch_id`):

```json
{
  "message": "The payload field is required.",
  "errors": { ... }
}
```

```json
{
  "status": false,
  "message": "The payload must be a valid JSON array."
}
```

#### Example (JSON body)

```bash
curl -X POST "http://127.0.0.1:8000/api/hidden-links" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"payload\":[{\"url\":\"https://example.com\",\"keyword\":\"test\",\"nofollow\":false}],\"batch_id\":1,\"chunk_id\":0}"
```

---

### 4. Delete hidden links by URL

Deletes **all** hidden link rows that match the given URL (exact match).

| | |
|---|---|
| **Method** | `DELETE` |
| **URL** | `/hidden-links/by-url` |
| **Auth** | Required (API key) |
| **Content-Type** | `application/json` (recommended) |

#### Request body (JSON)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string (valid URL) | **Yes** | Exact URL to delete (e.g. `https://outlookkorea.com/`) |

#### Example body

```json
{
  "url": "https://outlookkorea.com/"
}
```

#### Response (200 OK)

```json
{
  "status": true,
  "message": "3 link(s) deleted successfully.",
  "deleted_count": 3,
  "url": "https://outlookkorea.com/"
}
```

If no links exist for that URL:

```json
{
  "status": true,
  "message": "No links found with that URL.",
  "deleted_count": 0,
  "url": "https://outlookkorea.com/"
}
```

#### Example

```bash
curl -X DELETE "http://127.0.0.1:8000/api/hidden-links/by-url" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"url\":\"https://outlookkorea.com/\"}"
```

---

### 5. Delete hidden links by batch ID

Deletes **all** hidden link rows with the given `batch_id`.

| | |
|---|---|
| **Method** | `DELETE` |
| **URL** | `/hidden-links/by-batch-id` |
| **Auth** | Required (API key) |
| **Content-Type** | `application/json` (recommended) |

#### Request body (JSON)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `batch_id` | integer (≥ 0) | **Yes** | Batch ID used when creating links |

#### Example body

```json
{
  "batch_id": 7
}
```

#### Response (200 OK)

```json
{
  "status": true,
  "message": "150 link(s) deleted successfully.",
  "deleted_count": 150,
  "batch_id": 7
}
```

If no links exist for that batch:

```json
{
  "status": true,
  "message": "No links found with that batch_id.",
  "deleted_count": 0,
  "batch_id": 7
}
```

#### Example

```bash
curl -X DELETE "http://127.0.0.1:8000/api/hidden-links/by-batch-id" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"batch_id\":7}"
```

---

## Quick reference

| Action | Method | Endpoint | Body (key fields) |
|--------|--------|----------|--------------------|
| Check API | GET | `/status` | — |
| List links | GET | `/hidden-links` | — (optional: `per_page`, `page`, `search`) |
| Create links | POST | `/hidden-links` | `payload`, `batch_id`, `chunk_id`, optional `domain_id` |
| Delete by URL | DELETE | `/hidden-links/by-url` | `url` |
| Delete by batch | DELETE | `/hidden-links/by-batch-id` | `batch_id` |

---

## Testing in the app

You can test these endpoints from the authenticated area:

1. Log in.
2. Go to **Settings** and generate an API key if needed.
3. Open **API Test** (or the route you use for the API test page).
4. Use the test page to send GET, POST, and DELETE requests with your API key and inspect responses.

Use this document as the source of truth for request/response formats and examples when testing or integrating with the API.
