# Campaign Links Deletion - API Endpoint for Target Domains

## Overview
When you delete a campaign from the management system, it will automatically send requests to all target domains to delete the specific links that were created during that campaign.

## API Endpoint to Add on Each Target Domain

Add this endpoint to your target domain's `routes/api.php`:

```php
Route::middleware('auth:sanctum')->delete('/hidden-links/delete-by-ids', function (Request $request) {
    $validated = $request->validate([
        'post_ids' => 'required|array',
        'post_ids.*' => 'required|integer',
    ]);

    try {
        $postIds = $validated['post_ids'];
        
        // Delete hidden links by their remote post IDs
        // Adjust table/column names according to your database structure
        $deletedCount = \DB::table('hidden_links')
            ->whereIn('id', $postIds)
            ->delete();
        
        // Or if you have a HiddenLink model:
        // $deletedCount = \App\Models\HiddenLink::whereIn('id', $postIds)->delete();

        \Log::info('Campaign links deleted', [
            'post_ids' => $postIds,
            'deleted_count' => $deletedCount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Campaign links deleted successfully.',
            'deleted_count' => $deletedCount,
            'requested_ids' => count($postIds),
        ]);
    } catch (\Exception $e) {
        \Log::error('Failed to delete campaign links: ' . $e->getMessage(), [
            'post_ids' => $validated['post_ids'] ?? [],
        ]);
        
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete campaign links: ' . $e->getMessage(),
        ], 500);
    }
});
```

## How It Works

### 1. Campaign Creation
When you create a campaign:
- Links are distributed to target domains
- Each domain stores the links and returns `remote_post_id` (the ID of the created link on that domain)
- These IDs are stored in the `results_payload` of `campaign_domain_chunks` table

### 2. Campaign Deletion
When you delete a campaign:
- System extracts all `remote_post_id` values from the campaign's chunks
- Groups them by domain
- Dispatches `DeleteCampaignLinksJob` for each domain with the list of post IDs
- Each job sends: `DELETE /api/hidden-links/delete-by-ids` with `{"post_ids": [123, 456, 789]}`

### 3. Target Domain Response
The target domain:
- Receives the array of post IDs
- Deletes those specific links from its database
- Returns the count of deleted links

## Request Example

```bash
curl -X DELETE https://yourdomain.com/api/hidden-links/delete-by-ids \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "post_ids": [123, 456, 789, 1011]
  }'
```

## Response Example

```json
{
  "status": true,
  "message": "Campaign links deleted successfully.",
  "deleted_count": 4,
  "requested_ids": 4
}
```

## Important Notes

1. **Authentication**: Uses Bearer token from `campaign_domains.api_key`
2. **Queue**: Jobs are processed via `delete_campaign_links` queue
3. **Retries**: Each job retries up to 3 times on failure
4. **404 Handling**: If endpoint doesn't exist, job logs warning and skips (no retry)
5. **Selective Deletion**: Only deletes links from the specific campaign, not all links
6. **Database Structure**: Adjust table/column names to match your target domain's structure

## Database Requirements

Your target domains should have a table structure similar to:

```sql
CREATE TABLE hidden_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    -- other columns...
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

The `id` column is what gets stored as `remote_post_id` in the management system.

## Testing

After adding the endpoint, test it:

```bash
# Test with sample IDs
curl -X DELETE https://yourdomain.com/api/hidden-links/delete-by-ids \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"post_ids": [1, 2, 3]}'
```

## Monitoring

Check logs on your target domains:
```bash
tail -f storage/logs/laravel.log | grep "Campaign links"
```

Check logs on management system:
```bash
tail -f storage/logs/laravel.log | grep "DeleteCampaignLinksJob"
```
