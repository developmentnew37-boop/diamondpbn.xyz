# Campaign Domains CSV/Excel Import Guide

## Overview
You can now bulk import campaign domains using CSV or Excel files. This feature allows you to quickly add hundreds of domains to your campaign domain pool.

## Supported File Formats
- **CSV** (.csv, .txt)
- **Excel** (.xlsx, .xls)

## CSV Format

### Required Columns
1. **domain** - Domain name (e.g., example.com)
2. **api_url** - Full API endpoint URL (e.g., https://example.com/api)

### Optional Columns
3. **api_key** - API authentication key (can be left empty)

### Example CSV
```csv
domain,api_url,api_key
example1.com,https://example1.com/api,abc123key
example2.com,https://example2.com/api,def456key
example3.com,https://example3.com/api,
www.example4.com,https://example4.com/api,jkl012key
```

## Excel Format

Same structure as CSV:
- **Column A**: domain
- **Column B**: api_url
- **Column C**: api_key (optional)

The first row should contain headers (domain, api_url, api_key).

## How to Import

### Step 1: Prepare Your File
1. Create a CSV or Excel file with your domains
2. Use the format shown above
3. Include headers in the first row
4. Save the file

### Step 2: Upload
1. Navigate to **Campaign Domains** page (`/campaign-domains`)
2. Click the **"Import CSV/Excel"** button
3. Select your file
4. The file will be uploaded and queued for processing

### Step 3: Monitor Progress
1. The import will be processed in the background
2. Check the **"Import History"** section at the bottom of the page
3. Status will show:
   - **Pending** - Waiting to be processed
   - **Processing** - Currently importing
   - **Completed** - Successfully imported
   - **Failed** - Import failed (check logs)

### Step 4: Run Queue Worker
Make sure the queue worker is running:
```bash
php artisan queue:work database --queue=import_domains
```

## Import Behavior

### Domain Normalization
Domains are automatically normalized:
- `https://example.com` → `example.com`
- `www.example.com` → `example.com`
- `example.com/` → `example.com`

### Duplicate Handling
- If a domain already exists (same normalized domain), it will be **updated** with new data
- No duplicate domains will be created
- The system uses `domain_normalized` to detect duplicates

### Skipped Rows
Rows will be skipped if:
- Domain is empty
- API URL is empty
- Row is the header row

### Status After Import
- All imported domains start with status: **inactive**
- Health checks are NOT automatically run (unlike regular domains)
- You can manually check domain status later if needed

## Import Statistics

After import completes, you'll see:
- **Total Rows**: Number of rows in the file
- **Imported**: Successfully imported/updated domains
- **Skipped**: Rows that were skipped (empty or invalid)

## Example Files

### Sample CSV (5 domains)
See: `docs/campaign-domains-import-sample.csv`

### Sample Excel
Create an Excel file with these columns:

| domain | api_url | api_key |
|--------|---------|---------|
| site1.com | https://site1.com/api | key123 |
| site2.com | https://site2.com/api | key456 |
| site3.com | https://site3.com/api | key789 |

## Troubleshooting

### Import Stuck on "Pending"
**Problem**: Import status stays "pending"  
**Solution**: Start the queue worker:
```bash
php artisan queue:work database --queue=import_domains
```

### Import Failed
**Problem**: Import status shows "failed"  
**Solution**: 
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify file format is correct
3. Ensure file is not corrupted
4. Check file size (max 10MB)

### Some Domains Skipped
**Problem**: Imported count is less than total rows  
**Solution**: 
- Check that all rows have both domain and api_url
- Remove empty rows
- Ensure no extra header rows

### Domains Not Showing
**Problem**: Import completed but domains not visible  
**Solution**:
- Refresh the page
- Check if domains were updated (not created new)
- Search for the domain name

## File Size Limits

- **Maximum file size**: 10MB
- **Recommended**: Up to 10,000 domains per file
- For larger imports, split into multiple files

## API Key Notes

- API key is optional during import
- You can add/update API keys later via the edit page
- If left empty, the system will use the default `SITE_API_KEY` from `.env`

## Differences from Regular Domain Import

| Feature | Regular Domains | Campaign Domains |
|---------|----------------|------------------|
| Import Type | `type: 'regular'` | `type: 'campaign'` |
| Auto Health Check | ✅ Yes | ❌ No |
| Default Status | inactive | inactive |
| Queue | import_domains | import_domains |
| Import History | Separate | Separate |

## Best Practices

1. **Test First**: Import 5-10 domains first to verify format
2. **Backup**: Keep a copy of your CSV file
3. **Clean Data**: Remove duplicates before importing
4. **Valid URLs**: Ensure all API URLs are complete and valid
5. **Queue Worker**: Always run the queue worker during import
6. **Monitor**: Check import history after upload

## Quick Start

1. Download sample: `docs/campaign-domains-import-sample.csv`
2. Edit with your domains
3. Go to `/campaign-domains`
4. Click "Import CSV/Excel"
5. Select your file
6. Run: `php artisan queue:work database --queue=import_domains`
7. Refresh page to see imported domains

## Support

If you encounter issues:
1. Check the import history for error status
2. Review Laravel logs
3. Verify file format matches the examples
4. Ensure queue worker is running

Happy importing! 🚀
