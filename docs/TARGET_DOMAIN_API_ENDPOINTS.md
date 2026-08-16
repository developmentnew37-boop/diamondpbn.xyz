# API Endpoints to Add on Each Target Domain

## 1. Toggle Visibility Endpoint

Add this to your target domain's `routes/api.php`:

```php
Route::middleware('auth:sanctum')->post('/hidden-links/toggle-visibility', function (Request $request) {
    $validated = $request->validate([
        'show_hidden_links' => 'required|boolean',
    ]);

    $settings = \App\Models\Setting::first();
    
    if (!$settings) {
        return response()->json([
            'status' => false,
            'message' => 'Settings not found.',
        ], 404);
    }

    $settings->show_hidden_links = $validated['show_hidden_links'];
    $settings->save();

    return response()->json([
        'status' => true,
        'message' => 'Hidden links visibility updated successfully.',
        'show_hidden_links' => (bool) $settings->show_hidden_links,
    ]);
});
```

## 2. Delete All Hidden Links Endpoint

Add this to your target domain's `routes/api.php`:

```php
Route::middleware('auth:sanctum')->delete('/hidden-links/delete-all', function (Request $request) {
    try {
        // Delete all hidden links from the database
        $deletedCount = \DB::table('hidden_links')->delete();
        
        // Or if you have a HiddenLink model:
        // $deletedCount = \App\Models\HiddenLink::query()->delete();

        return response()->json([
            'status' => true,
            'message' => 'All hidden links deleted successfully.',
            'deleted_count' => $deletedCount,
        ]);
    } catch (\Exception $e) {
        \Log::error('Failed to delete hidden links: ' . $e->getMessage());
        
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete hidden links: ' . $e->getMessage(),
        ], 500);
    }
});
```

## 3. Database Requirements

Make sure your target domains have:

### Settings Table
```sql
ALTER TABLE settings ADD COLUMN show_hidden_links BOOLEAN DEFAULT FALSE;
```

### Hidden Links Table
Your target domains should have a table to store hidden links (adjust table/column names as needed):
```sql
CREATE TABLE hidden_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## 4. Testing the Endpoints

### Test Toggle Visibility:
```bash
curl -X POST https://yourdomain.com/api/hidden-links/toggle-visibility \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"show_hidden_links": true}'
```

### Test Delete All:
```bash
curl -X DELETE https://yourdomain.com/api/hidden-links/delete-all \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
```

## 5. Important Notes

- Both endpoints require Bearer token authentication via `auth:sanctum` middleware
- The API key is stored in your campaign_domains table (api_key column)
- Make sure your target domains have API routes enabled in `bootstrap/app.php`
- The delete operation is permanent and cannot be undone
- Adjust table/model names according to your target domain's structure
