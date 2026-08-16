# Campaign Individual Link Deletion Feature

## Overview
This feature allows you to delete individual links from within a campaign. When you delete a link, it will be automatically removed from **all live sites** that received that link as part of the campaign distribution.

## How It Works

### User Flow
1. Navigate to a campaign detail page (`/campaigns/{id}`)
2. Find the link you want to remove in the links table
3. Click the delete button (trash icon) next to the link
4. Confirm the deletion
5. The system queues a job to remove the link from all target domains

### Backend Process
1. **Job Dispatch**: `RemoveLinkFromCampaignJob` is dispatched to the `remove_link_from_campaign` queue
2. **Find Affected Domains**: The job identifies all campaign domain chunks that contain the specific link
3. **Remote Deletion**: For each domain that received the link:
   - Sends `DELETE /hidden-links/by-url` request with the exact URL
   - Logs success or failure for each domain
4. **Local Cleanup**: 
   - Removes the link from chunk payloads
   - Updates chunk success/failed counts
   - Updates campaign statistics (total_links, processed_count, success_count, failed_count)
   - Deletes the link record from the database

## Files Modified/Created

### New Files
- `app/Jobs/RemoveLinkFromCampaignJob.php` - Job to remove links from all domains

### Modified Files
- `app/Services/PbnApiService.php` - Added `deleteLinkByUrlFromCampaignDomain()` method
- `app/Http/Controllers/CampaignController.php` - Added `destroyLink()` method
- `routes/web.php` - Added DELETE route for campaign links
- `resources/views/campaigns/show.blade.php` - Added delete buttons for each link

## API Endpoint Used

The feature uses the existing PBN API endpoint:
```
DELETE {api_url}/hidden-links/by-url
Content-Type: application/json
Authorization: Bearer {api_key}

{
  "url": "https://example.com/page"
}
```

This is the same endpoint used by the batch link deletion feature.

## Queue Configuration

### Queue Name
`remove_link_from_campaign`

### Running the Queue Worker
```bash
php artisan queue:work database --queue=remove_link_from_campaign
```

### Or run all campaign queues together
```bash
php artisan queue:work database --queue=campaign_links,remove_link_from_campaign
```

## Job Details

### RemoveLinkFromCampaignJob
- **Queue**: `remove_link_from_campaign`
- **Timeout**: 600 seconds (10 minutes)
- **Retries**: 1 attempt
- **Unique**: Yes (prevents duplicate deletion jobs for the same campaign/link)

### Job Flow
```
1. Load fresh campaign and link data
2. Find all chunks containing the link URL
3. For each affected domain:
   a. Send DELETE request to remote site
   b. Log result (success or warning)
4. Update local chunk payloads:
   a. Remove link from links_payload
   b. Remove result from results_payload
   c. Update chunk counts
5. Update campaign statistics
6. Delete the link record
```

## Usage Example

### Scenario
You created a campaign with 25 links distributed across 100 domains. After publishing, you realize one of the links is incorrect and needs to be removed from all sites.

### Steps
1. Navigate to the campaign detail page
2. Find the incorrect link in the "Campaign Links" table
3. Click the trash icon in the "Actions" column
4. Confirm: "Remove this link from all target domains? This action cannot be undone."
5. The system shows: "Link removal queued. The link will be removed from all target domains shortly."
6. The queue worker processes the job and removes the link from all 100 domains

### Result
- The link is deleted from all remote sites via API
- Campaign statistics are updated (total_links decremented)
- The link no longer appears in the campaign

## Error Handling

### Remote Deletion Failures
- If a DELETE request fails for a specific domain, the job logs a warning but continues
- Other domains are still processed
- The link is still removed locally after attempting all remote deletions

### Job Failures
- If the entire job fails, it's logged to the failed_jobs table
- You can retry failed jobs using: `php artisan queue:retry {job-id}`

## Logging

All deletion operations are logged:

### Success Log
```php
Log::info("Link removed from campaign domain: {domain}", [
    'campaign_id' => $campaign->id,
    'link_id' => $link->id,
    'domain_id' => $domain->id,
    'url' => $linkUrl,
]);
```

### Failure Log
```php
Log::warning('RemoveLinkFromCampaignJob: failed to delete link on domain', [
    'campaign_id' => $campaign->id,
    'link_id' => $link->id,
    'domain_id' => $domain->id,
    'domain_api_url' => $domain->api_url,
    'url' => $linkUrl,
    'message' => $e->getMessage(),
]);
```

## Security

### Authorization
- Only the campaign owner can delete links from their campaigns
- The controller checks: `$campaign->user_id === auth()->id()`
- The controller also verifies: `$link->campaign_id === $campaign->id`

### Confirmation
- Browser confirmation dialog prevents accidental deletions
- Message: "Remove this link from all target domains? This action cannot be undone."

## UI/UX

### Delete Button
- Located in the "Actions" column of the links table
- Red hover state to indicate destructive action
- Trash icon for clear visual indication
- Tooltip: "Delete link from all domains"

### Feedback
- Success message: "Link removal queued. The link will be removed from all target domains shortly."
- The link remains visible until the page is refreshed (job runs asynchronously)

## Comparison with Batch Link Deletion

| Feature | Batch Link Deletion | Campaign Link Deletion |
|---------|-------------------|----------------------|
| Scope | Single batch, all domains | Single campaign, all domains |
| Domain Type | Regular domains | Campaign domains |
| API Method | `deleteLinkByUrl()` | `deleteLinkByUrlFromCampaignDomain()` |
| Queue | `remove_link_from_batch` | `remove_link_from_campaign` |
| Job | `RemoveLinkFromBatchJob` | `RemoveLinkFromCampaignJob` |

Both features use the same API endpoint but handle different domain types and data structures.

## Testing

### Manual Testing Steps
1. Create a test campaign with 3 domains and 5 links
2. Wait for the campaign to publish successfully
3. Delete one link from the campaign
4. Check the queue worker logs
5. Verify the link is removed from all 3 remote sites
6. Refresh the campaign page and verify the link is gone
7. Check campaign statistics are updated correctly

### What to Verify
- ✓ Link is removed from all remote sites
- ✓ Campaign total_links count decreases by 1
- ✓ Campaign processed_count decreases by number of domains
- ✓ Campaign success_count/failed_count updated correctly
- ✓ Chunk payloads no longer contain the link
- ✓ Link record is deleted from database
- ✓ No errors in logs

## Troubleshooting

### Link not removed from remote sites
- Check queue worker is running: `php artisan queue:work database --queue=remove_link_from_campaign`
- Check failed_jobs table: `SELECT * FROM failed_jobs WHERE queue = 'remove_link_from_campaign'`
- Check logs: `storage/logs/laravel.log`

### Job stuck in queue
- Check jobs table: `SELECT * FROM jobs WHERE queue = 'remove_link_from_campaign'`
- Restart queue worker
- Check for unique constraint violations (duplicate jobs)

### API errors
- Verify campaign domain API URLs are correct
- Verify API keys are valid
- Check remote site logs for DELETE request errors
- Test API endpoint manually with Postman

## Future Enhancements

Potential improvements:
1. Bulk link deletion (select multiple links to delete)
2. Soft delete with restore capability
3. Deletion history/audit log
4. Email notification when deletion completes
5. Retry mechanism for failed remote deletions

## Related Documentation
- `CAMPAIGN_FEATURE.md` - Main campaign feature documentation
- `PBN_API_SPECIFICATION.md` - API endpoint specifications
- `CSV_IMPORT_FEATURE.md` - Batch link deletion feature (similar implementation)
