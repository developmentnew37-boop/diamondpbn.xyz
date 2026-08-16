# Campaign Feature - Implementation Summary

## Overview
A new **Campaign** system has been added to the PBN Link Management application. This feature allows you to distribute a limited number of links across multiple domains with automatic looping and remainder distribution.

## Key Difference from Batches

### Batches (Original System)
- Post ALL links to ALL selected domains
- Example: 100 links × 50 domains = 5,000 total link posts

### Campaigns (New System)
- Distribute LIMITED links with SPECIFIC quantity per domain
- Links are looped and distributed evenly
- Example: 25 links × 100 domains × 5 links per domain = 500 total link posts (25 links looped to fill 500 slots)

## How Link Distribution Works

### Distribution Algorithm
1. **Input**: Source links, target domains, links per domain
2. **Calculation**: Total needed = domains × links_per_domain
3. **Looping**: Source links are repeated cyclically to fill all slots
4. **Remainder**: Any remainder is distributed from the top

### Example 1: Perfect Division
- **Source Links**: 25 links
- **Domains**: 5 domains
- **Links per Domain**: 5
- **Result**: Each domain gets 5 unique links (25 ÷ 5 = 5, no remainder)

### Example 2: With Looping
- **Source Links**: 10 links
- **Domains**: 5 domains
- **Links per Domain**: 5
- **Total Needed**: 25 link slots
- **Result**: 
  - Links loop: 10 → 20 → 25 (2.5 cycles)
  - Each domain gets 5 links (some repeated from the loop)

### Example 3: With Remainder
- **Source Links**: 10 links
- **Domains**: 3 domains
- **Links per Domain**: 3
- **Total Needed**: 9 link slots
- **Result**:
  - Domain 1: Links 1, 2, 3, 4 (3 + 1 remainder)
  - Domain 2: Links 5, 6, 7
  - Domain 3: Links 8, 9, 10

## Database Schema

### New Tables

#### `campaign_domains`
- Separate domain pool for campaigns
- Same structure as regular domains
- Fields: domain, domain_normalized, api_url, api_key, status, etc.

#### `campaigns`
- Campaign metadata
- Fields: name, description, status, total_links, total_domains, **links_per_domain**, total_distributed_links, processed_count, success_count, failed_count

#### `campaign_links`
- Source links for the campaign
- Fields: campaign_id, user_id, url, keyword, no_follow

#### `campaign_domain_chunks`
- Distributed link chunks (100 links per chunk)
- Fields: campaign_id, campaign_domain_id, chunk_index, links_payload, results_payload, status, success_count, failed_count

## Features

### Campaign Domains Management
- **Route**: `/campaign-domains`
- Add domains manually
- Export to CSV
- Bulk delete
- Edit domain details
- Separate from regular batch domains

### Campaign Creation
- **Route**: `/campaigns/create`
- 3-step wizard:
  1. Campaign info (name, description)
  2. Select campaign domains
  3. Add links + specify links per domain

### Campaign Tracking
- **Route**: `/campaigns/{id}`
- View overall statistics
- Per-domain breakdown
- Success/failed/pending counts
- View distributed links per domain

### Campaign Operations
- Publish pending chunks (fix stuck campaigns)
- View domain-specific link distribution
- Delete campaigns
- **Delete individual links from campaigns** (removes from all target domains)
- Real-time progress tracking

## API Integration

Uses the **same API endpoints** as batches:
- `POST {api_url}/hidden-links` - Bulk post links
- `DELETE {api_url}/hidden-links/by-batch-id` - Delete by batch_id (uses campaign_id)
- `DELETE {api_url}/hidden-links/by-url` - Delete by URL

The `PbnApiService` is reused, so no changes needed to your PBN site APIs.

## Queue System

### Queue: `campaign_links`
```bash
php artisan queue:work database --queue=campaign_links
```

### Queue: `remove_link_from_campaign`
```bash
php artisan queue:work database --queue=remove_link_from_campaign
```

### Job: `PublishCampaignChunkJob`
- Posts campaign chunks to remote domains
- 3 retry attempts
- Unique per chunk (prevents duplicates)
- Delayed execution (5 seconds between chunks)

### Job: `RemoveLinkFromCampaignJob`
- Removes individual links from all target domains in a campaign
- Sends DELETE request to each domain that received the link
- Updates campaign statistics and chunk payloads
- Deletes the link record after successful removal
- 1 retry attempt

## Navigation

### Desktop Sidebar
- Campaign Domains
- Campaigns
- Create Campaign

### Mobile Menu
- Same items added to mobile navigation

## Usage Example

### Scenario
You have 25 backlinks and want to distribute them across 100 domains, with each domain getting 5 links.

### Steps
1. Go to **Campaign Domains** and add/import your 100 domains
2. Go to **Create Campaign**
3. Enter campaign name: "Q1 2026 Distribution"
4. Select all 100 domains
5. Paste your 25 URLs and 25 keywords
6. Set **Links Per Domain**: 5
7. Click "Create & Distribute Links"

### Result
- Total distributed links: 500 (100 domains × 5 links each)
- Your 25 source links are looped 20 times to fill all 500 slots
- Each domain receives exactly 5 links
- Links are posted via queue in chunks of 100

## File Structure

```
app/
├── Http/Controllers/
│   ├── CampaignController.php
│   └── CampaignDomainController.php
├── Jobs/
│   └── PublishCampaignChunkJob.php
├── Models/
│   ├── Campaign.php
│   ├── CampaignDomain.php
│   ├── CampaignLink.php
│   └── CampaignDomainChunk.php

database/migrations/
├── 2026_05_11_140402_create_campaign_domains_table.php
├── 2026_05_11_140410_create_campaigns_table.php
├── 2026_05_11_140415_create_campaign_links_table.php
└── 2026_05_11_140420_create_campaign_domain_chunks_table.php

resources/views/
├── campaign-domains/
│   ├── index.blade.php
│   └── edit.blade.php
└── campaigns/
    ├── index.blade.php
    ├── create.blade.php
    ├── show.blade.php
    └── domain.blade.php

routes/web.php (updated with campaign routes)
```

## Testing the Feature

1. **Start the queue worker**:
   ```bash
   php artisan queue:work database --queue=campaign_links
   ```

2. **Add campaign domains**:
   - Navigate to `/campaign-domains`
   - Add at least 3 test domains

3. **Create a test campaign**:
   - Navigate to `/campaigns/create`
   - Add 10 test links
   - Select 3 domains
   - Set 3 links per domain
   - Submit

4. **Monitor progress**:
   - View campaign detail page
   - Refresh to see progress
   - Check per-domain statistics

## Key Differences Summary

| Feature | Batches | Campaigns |
|---------|---------|-----------|
| Domain Pool | Regular domains | Campaign domains (separate) |
| Link Distribution | All links to all domains | Limited links with looping |
| Links Per Domain | All links | Configurable quantity |
| Use Case | Post many links everywhere | Distribute few links evenly |
| Total Posts | links × domains | domains × links_per_domain |
| Queue | `batch_links` | `campaign_links` |

## Benefits

1. **Efficient Distribution**: Post limited links across many domains without duplication
2. **Resource Control**: Control exactly how many links each domain receives
3. **Automatic Looping**: No need to manually duplicate links
4. **Separate Management**: Campaign domains don't interfere with batch domains
5. **Same API**: Uses existing PBN API endpoints

## Next Steps

1. Run the queue worker for `campaign_links`
2. Add your campaign domains
3. Create your first campaign
4. Monitor the distribution progress

The campaign feature is now fully functional and ready to use!
