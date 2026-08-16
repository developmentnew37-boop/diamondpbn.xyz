# PBN Domain API Specification

## Overview
Each PBN domain must expose these API endpoints to work with the Link Management system. The API handles bulk link posting, deletion, and health checks.

## Authentication
All endpoints require authentication via:
- **Header**: `Authorization: Bearer {api_key}`
- **Header**: `X-API-Key: {api_key}`

The system sends both headers, so your API can check either one.

---

## 1. Health Check / Status

**Endpoint**: `GET {api_url}/status`

**Purpose**: Verify the domain is online and API is accessible

**Headers**:
```
Authorization: Bearer {api_key}
X-API-Key: {api_key}
Accept: application/json
```

**Response** (200 OK):
```json
{
  "status": "ok",
  "message": "API is working"
}
```

**Response** (401 Unauthorized):
```json
{
  "message": "Invalid API key"
}
```

---

## 2. Bulk Post Hidden Links

**Endpoint**: `POST {api_url}/hidden-links`

**Purpose**: Post multiple hidden links in bulk (up to 100 per request)

**Headers**:
```
Authorization: Bearer {api_key}
X-API-Key: {api_key}
Content-Type: application/json
Accept: application/json
```

**Request Body**:
```json
{
  "payload": [
    {
      "url": "https://example.com/page1",
      "keyword": "anchor text 1",
      "nofollow": false
    },
    {
      "url": "https://example.com/page2",
      "keyword": "anchor text 2",
      "nofollow": true
    }
  ],
  "batch_id": 123,
  "chunk_id": 0,
  "domain_id": 456
}
```

**Request Fields**:
- `payload` (array, required): Array of link objects
  - `url` (string, required): Target URL for the link
  - `keyword` (string, required): Anchor text
  - `nofollow` (boolean, optional): Whether to add rel="nofollow"
- `batch_id` (integer, required): Batch or campaign ID (for tracking)
- `chunk_id` (integer, optional): Chunk index
- `domain_id` (integer, optional): Domain ID

**Response** (200 OK):
```json
{
  "success": 95,
  "failed": 5,
  "payload": [
    {
      "status": "success",
      "link_id": "post_123"
    },
    {
      "status": "success",
      "link_id": "post_124"
    },
    {
      "status": "failed",
      "error": "Post not found for insertion"
    }
  ]
}
```

**Response Fields**:
- `success` (integer): Number of successfully posted links
- `failed` (integer): Number of failed links
- `payload` (array): Array of results matching input order
  - `status` (string): "success", "completed", "posted", or "failed"
  - `link_id` (string): Remote post/link ID (for successful posts)
  - `error` (string): Error message (for failed posts)

**Important**: The `payload` array in the response must match the order of the input `payload` array.

---

## 3. Delete Link by URL

**Endpoint**: `DELETE {api_url}/hidden-links/by-url`

**Purpose**: Delete a specific hidden link by its exact URL

**Headers**:
```
Authorization: Bearer {api_key}
X-API-Key: {api_key}
Content-Type: application/json
Accept: application/json
```

**Request Body**:
```json
{
  "url": "https://example.com/page1"
}
```

**Response** (200 OK):
```json
{
  "deleted": 1,
  "message": "Link removed successfully"
}
```

**Response** (404 Not Found):
```json
{
  "deleted": 0,
  "message": "Link not found"
}
```

---

## 4. Delete Links by Batch ID

**Endpoint**: `DELETE {api_url}/hidden-links/by-batch-id`

**Purpose**: Delete all hidden links associated with a batch/campaign ID

**Headers**:
```
Authorization: Bearer {api_key}
X-API-Key: {api_key}
Content-Type: application/json
Accept: application/json
```

**Request Body**:
```json
{
  "batch_id": 123
}
```

**Response** (200 OK):
```json
{
  "deleted": 250,
  "message": "All links for batch removed"
}
```

**Response** (404 Not Found):
```json
{
  "deleted": 0,
  "message": "No links found for this batch"
}
```

---

## Implementation Notes

### 1. Where to Store Hidden Links
You can store hidden links in:
- **Database table** (recommended): Create a `hidden_links` table
- **Post meta**: Store as post metadata
- **Custom field**: Store in a custom field
- **Footer/sidebar**: Inject into theme footer or sidebar

### 2. How to Insert Links
Common approaches:
- **Random posts**: Insert into random existing posts
- **Dedicated posts**: Create hidden posts for links
- **Footer injection**: Add to site footer
- **Widget area**: Add to a hidden widget

### 3. Link Format
```html
<a href="https://example.com/page" rel="nofollow">anchor text</a>
```

### 4. Batch ID Tracking
Store the `batch_id` with each link so you can:
- Delete all links from a specific batch
- Track which batch a link belongs to
- Prevent duplicate insertions

### 5. Security
- Validate API key on every request
- Sanitize URLs and keywords
- Prevent SQL injection
- Rate limit requests (optional)

### 6. Performance
- Use database transactions for bulk inserts
- Index the `batch_id` column
- Consider queuing for large batches

---

## Example Database Schema

```sql
CREATE TABLE hidden_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    nofollow BOOLEAN DEFAULT FALSE,
    post_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch_id (batch_id),
    INDEX idx_url (url(255))
);
```

---

## Example Laravel Controller

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HiddenLinkApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.key'); // Your API key middleware
    }

    // GET /api/status
    public function status()
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'API is working'
        ]);
    }

    // POST /api/hidden-links
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payload' => 'required|array',
            'payload.*.url' => 'required|url',
            'payload.*.keyword' => 'required|string',
            'payload.*.nofollow' => 'nullable|boolean',
            'batch_id' => 'required|integer',
        ]);

        $batchId = $validated['batch_id'];
        $links = $validated['payload'];
        
        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($links as $link) {
            try {
                // Insert link into database or post
                $linkId = DB::table('hidden_links')->insertGetId([
                    'batch_id' => $batchId,
                    'url' => $link['url'],
                    'keyword' => $link['keyword'],
                    'nofollow' => $link['nofollow'] ?? false,
                    'created_at' => now(),
                ]);

                $results[] = [
                    'status' => 'success',
                    'link_id' => (string) $linkId,
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $failedCount++;
            }
        }

        return response()->json([
            'success' => $successCount,
            'failed' => $failedCount,
            'payload' => $results,
        ]);
    }

    // DELETE /api/hidden-links/by-url
    public function deleteByUrl(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        $deleted = DB::table('hidden_links')
            ->where('url', $validated['url'])
            ->delete();

        return response()->json([
            'deleted' => $deleted,
            'message' => $deleted > 0 ? 'Link removed successfully' : 'Link not found',
        ], $deleted > 0 ? 200 : 404);
    }

    // DELETE /api/hidden-links/by-batch-id
    public function deleteByBatchId(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => 'required|integer',
        ]);

        $deleted = DB::table('hidden_links')
            ->where('batch_id', $validated['batch_id'])
            ->delete();

        return response()->json([
            'deleted' => $deleted,
            'message' => $deleted > 0 ? 'All links for batch removed' : 'No links found for this batch',
        ], $deleted > 0 ? 200 : 404);
    }
}
```

---

## Example Routes (Laravel)

```php
// routes/api.php
Route::middleware('api.key')->group(function () {
    Route::get('/status', [HiddenLinkApiController::class, 'status']);
    Route::post('/hidden-links', [HiddenLinkApiController::class, 'store']);
    Route::delete('/hidden-links/by-url', [HiddenLinkApiController::class, 'deleteByUrl']);
    Route::delete('/hidden-links/by-batch-id', [HiddenLinkApiController::class, 'deleteByBatchId']);
});
```

---

## Testing Your API

### Using cURL

**Health Check**:
```bash
curl -X GET "https://your-domain.com/api/status" \
  -H "Authorization: Bearer your_api_key" \
  -H "Accept: application/json"
```

**Post Links**:
```bash
curl -X POST "https://your-domain.com/api/hidden-links" \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "payload": [
      {"url": "https://example.com/page1", "keyword": "test link", "nofollow": false}
    ],
    "batch_id": 1
  }'
```

**Delete by URL**:
```bash
curl -X DELETE "https://your-domain.com/api/hidden-links/by-url" \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com/page1"}'
```

**Delete by Batch**:
```bash
curl -X DELETE "https://your-domain.com/api/hidden-links/by-batch-id" \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"batch_id": 1}'
```

---

## Common Issues

### 1. SSL Certificate Errors
The management system uses `withoutVerifying()` to bypass SSL verification. This is useful for self-signed certificates.

### 2. Timeout Issues
- Default timeout: 30 seconds (configurable in settings)
- For bulk operations, increase timeout in your API
- Process links asynchronously if needed

### 3. API Key Not Working
- Check both `Authorization` and `X-API-Key` headers
- Ensure API key matches in both systems
- Check middleware is applied to routes

### 4. Response Format Mismatch
- Ensure `payload` array order matches input order
- Always return `success` and `failed` counts
- Include `link_id` for successful posts

---

## Next Steps

1. Implement the API on your PBN domains
2. Test with cURL or Postman
3. Add the domain to Campaign Domains or Domains
4. Run a health check from the management system
5. Create a test batch/campaign

The API specification is complete and ready for implementation!
