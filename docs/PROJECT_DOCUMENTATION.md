# PBN Hidden Link Management System - Project Documentation

## Table of Contents
1. [Overview](#overview)
2. [Tech Stack](#tech-stack)
3. [Architecture](#architecture)
4. [Database Schema](#database-schema)
5. [Application Flow](#application-flow)
6. [Features](#features)
7. [API Integration](#api-integration)
8. [Queue System](#queue-system)
9. [Key Components](#key-components)
10. [How It Works](#how-it-works)

---

## Overview

**PBN Hidden Link Management System** is a centralized Laravel-based dashboard for managing hidden/SEO links across a Private Blog Network (PBN) of 100+ websites. The application allows users to post and manage backlinks in bulk with queue-based processing, track success/failure rates, and generate reports.

### Purpose
- Centralized management of multiple PBN domains
- Bulk posting of hidden links to improve SEO
- Track link posting success/failure across domains
- Generate reports for link campaigns
- Health monitoring of PBN domains

### Key Capabilities
- Manage 100+ domains with API credentials
- Create batches with multiple links and target domains
- Queue-based bulk posting (prevents timeouts)
- Per-domain and per-batch tracking
- Retry failed links
- Remove links from remote sites
- CSV import/export for domains
- Health check monitoring

---

## Tech Stack

### Backend
- **Laravel 12** - PHP framework
- **PHP 8.2+** - Programming language
- **MySQL/SQLite** - Database
- **Laravel Breeze** - Authentication scaffolding

### Frontend
- **Blade Templates** - Server-side rendering
- **Alpine.js** - Lightweight JavaScript framework
- **Tailwind CSS** - Utility-first CSS framework
- **Vite** - Frontend build tool

### Queue & Jobs
- **Database Queue Driver** - No Redis required
- **Laravel Queue System** - Background job processing

### Libraries
- **OpenSpout** - Excel/CSV file processing
- **Guzzle/HTTP Client** - API communication

---

## Architecture

### Application Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── BatchController.php       # Batch CRUD and operations
│   │   ├── DomainController.php      # Domain management
│   │   ├── ReportController.php      # Reporting and exports
│   │   ├── DashboardController.php   # Dashboard stats
│   │   └── SettingsController.php    # App settings
│   └── Requests/
├── Models/
│   ├── Batch.php                     # Batch model
│   ├── BatchDomainChunk.php          # Chunk processing model
│   ├── Domain.php                    # Domain model
│   ├── Link.php                      # Link model
│   ├── DomainImport.php              # Import tracking
│   └── User.php                      # User model
├── Jobs/
│   ├── PublishBatchChunkJob.php      # Post links to remote domains
│   ├── DeleteBatchJob.php            # Delete batch from remote sites
│   ├── RemoveLinkFromBatchJob.php    # Remove single link
│   ├── DomainHealthCheckJob.php      # Health check domains
│   └── ImportDomainsJob.php          # Import domains from CSV/Excel
├── Services/
│   └── PbnApiService.php             # API communication service
└── Support/
    └── PbnSettings.php               # App settings helper
```

### Design Patterns
- **Repository Pattern** - Models encapsulate data access
- **Service Layer** - PbnApiService handles external API calls
- **Job Queue Pattern** - Background processing for long-running tasks
- **Chunking Pattern** - Links split into chunks of 100 for processing

---

## Database Schema

### Core Tables

#### `users`
- `id` - Primary key
- `name` - User name
- `email` - Email (unique)
- `password` - Hashed password
- `role` - User role (admin/user)
- `timestamps`

#### `domains`
- `id` - Primary key
- `user_id` - Foreign key to users
- `domain` - Domain name (normalized)
- `domain_normalized` - Normalized domain for deduplication
- `api_url` - API endpoint URL
- `api_key` - API authentication key
- `api_secret` - API secret (optional)
- `status` - Domain status (active/inactive/error)
- `last_checked_at` - Last health check timestamp
- `last_health_error` - Last error message
- `notes` - User notes
- `timestamps`

#### `batches`
- `id` - Primary key
- `user_id` - Foreign key to users
- `name` - Batch name
- `description` - Batch description
- `status` - Batch status (pending/processing/completed/partial/delete_failed)
- `total_links` - Total number of links
- `total_domains` - Total number of domains
- `processed_count` - Number of processed links
- `success_count` - Number of successful posts
- `failed_count` - Number of failed posts
- `started_at` - Processing start time
- `completed_at` - Processing completion time
- `timestamps`

#### `links`
- `id` - Primary key
- `batch_id` - Foreign key to batches
- `user_id` - Foreign key to users
- `url` - Target URL for the link
- `keyword` - Anchor text/keyword
- `no_follow` - Boolean (nofollow attribute)
- `link_type` - Link type (text/image)
- `extra_data` - JSON field for additional data
- `timestamps`

#### `batch_domain_chunks`
- `id` - Primary key
- `batch_id` - Foreign key to batches
- `domain_id` - Foreign key to domains
- `chunk_index` - Chunk number (0, 1, 2...)
- `links_payload` - JSON array of links (max 100)
- `results_payload` - JSON array of API responses
- `status` - Chunk status (pending/processing/completed/partial)
- `attempts` - Number of retry attempts
- `success_count` - Successful links in chunk
- `failed_count` - Failed links in chunk
- `sent_at` - When chunk was sent to API
- `completed_at` - When chunk processing completed
- `error_message` - Error message if failed
- `timestamps`

#### `domain_imports`
- `id` - Primary key
- `user_id` - Foreign key to users
- `filename` - Uploaded file path
- `status` - Import status (pending/processing/completed/failed)
- `total_rows` - Total rows in file
- `imported_count` - Successfully imported domains
- `skipped_count` - Skipped rows
- `timestamps`

#### `jobs` (Queue table)
- `id` - Primary key
- `queue` - Queue name
- `payload` - Serialized job data
- `attempts` - Number of attempts
- `reserved_at` - When job was picked up
- `available_at` - When job becomes available
- `created_at` - Job creation time

### Relationships

```
User
├── hasMany → Domains
├── hasMany → Batches
└── hasMany → Links

Batch
├── belongsTo → User
├── hasMany → Links
├── hasMany → BatchDomainChunks
└── hasManyThrough → Domains (via BatchDomainChunks)

Domain
├── belongsTo → User
└── hasMany → BatchDomainChunks

Link
├── belongsTo → Batch
└── belongsTo → User

BatchDomainChunk
├── belongsTo → Batch
└── belongsTo → Domain
```

---

## Application Flow

### 1. Domain Management Flow

```
User adds domain manually or imports CSV
    ↓
Domain saved with status='inactive'
    ↓
DomainHealthCheckJob dispatched to queue
    ↓
Job calls GET {api_url}/status with API key
    ↓
Success: status='active' | Failure: status='error' + error message
```

### 2. Batch Creation & Link Posting Flow

```
Step 1: User creates batch
    - Enters batch name and description
    - Selects target domains (multiple)
    - Adds links (URL + keyword pairs)
    ↓
Step 2: Batch saved to database
    - Batch record created
    - Link records created
    - Links split into chunks of 100
    ↓
Step 3: BatchDomainChunk records created
    - For each domain × each chunk
    - Example: 250 links × 3 domains = 9 chunks
      (3 chunks per domain: 100, 100, 50)
    ↓
Step 4: PublishBatchChunkJob dispatched for each chunk
    - Jobs queued with delay (5 seconds between each)
    - Queue: 'batch_links'
    ↓
Step 5: Queue worker processes jobs
    - Calls POST {api_url}/hidden-links
    - Sends chunk payload (up to 100 links)
    - Receives response with success/failure per link
    ↓
Step 6: Results stored in chunk
    - results_payload updated
    - success_count and failed_count updated
    - Batch counters incremented
    ↓
Step 7: Batch completion check
    - If all chunks processed: status='completed' or 'partial'
    - User can view per-domain stats
```

### 3. Link Removal Flow

```
User clicks delete on a link
    ↓
RemoveLinkFromBatchJob dispatched
    ↓
Job removes link from ALL remote domains
    - Calls DELETE {api_url}/hidden-links/by-url
    - One request per domain
    ↓
Link removed from chunk payloads
    ↓
Link record deleted from database
    ↓
Batch counters decremented
```

### 4. Batch Deletion Flow

```
User deletes batch
    ↓
DeleteBatchJob dispatched
    ↓
Job removes ALL batch links from ALL domains
    - Calls DELETE {api_url}/hidden-links/by-batch-id
    - One request per domain
    ↓
If all deletions succeed:
    - Batch deleted from database
If any deletion fails:
    - Batch status='delete_failed'
    - User can retry later
```

---

## Features

### 1. Dashboard
- **Overview Statistics**
  - Total domains (active/inactive/error)
  - Total batches
  - Total links posted
  - Recent activity

### 2. Domain Management
- **Add Domain Manually**
  - Domain name
  - API URL
  - API key (optional)
  - Notes
  - Auto health check on creation

- **Import Domains (CSV/Excel)**
  - Upload CSV or Excel file
  - Columns: domain, api_url, api_key
  - Background processing via queue
  - Import status tracking

- **Domain List**
  - Search/filter domains
  - View status (active/inactive/error)
  - Last health check time
  - Error messages
  - Edit/delete domains
  - Bulk delete
  - Export to CSV

- **Health Check**
  - Manual recheck button
  - Automatic retry with backoff (5 attempts)
  - Status endpoint: GET {api_url}/status

### 3. Batch Management
- **Create Batch (3-step wizard)**
  - Step 1: Batch info (name, description)
  - Step 2: Select target domains
  - Step 3: Add links (URL + keyword pairs)
  - Bulk input: paste URLs and keywords (line-by-line)

- **Batch List**
  - View all batches
  - Search by name/description/URL/keyword
  - Status indicators
  - Success/failure counts

- **Batch Detail View**
  - Overall statistics
  - Per-domain breakdown
  - Success/failed/pending counts per domain
  - View all links in batch
  - Failed links list with error messages

- **Batch Operations**
  - Retry failed links
  - Publish pending chunks (fix stuck batches)
  - Delete individual links
  - Delete entire batch

- **Domain-Specific View**
  - View all links for a specific domain in a batch
  - See chunk-by-chunk status
  - Individual link status (success/failed/pending)
  - Remote post IDs
  - Error messages

### 4. Reports
- **Filter Options**
  - By batch
  - By domain
  - Date range (from/to)

- **Report Data**
  - Batch name
  - Domain
  - URL
  - Keyword
  - Status (success/failed/pending)
  - Posted date
  - Error message

- **Export**
  - CSV export with filters
  - Streaming export (memory efficient)
  - Paginated view (200 rows per page)

### 5. Settings
- **API Configuration**
  - API timeout (10-600 seconds)
  - Link delay between chunks (0-300 seconds)
  - Cached settings for performance

### 6. Authentication
- **Laravel Breeze**
  - Login/Register
  - Email verification
  - Password reset
  - Profile management

---

## API Integration

### PBN Site API Contract

The application expects each PBN domain to expose the following API endpoints:

#### 1. Health Check
```
GET {api_url}/status
Headers:
  - Authorization: Bearer {api_key}
  - X-API-Key: {api_key}

Response:
  200 OK - Domain is healthy
  401 Unauthorized - Invalid API key
  500 Error - Domain has issues
```

#### 2. Post Links (Bulk)
```
POST {api_url}/hidden-links
Headers:
  - Authorization: Bearer {api_key}
  - X-API-Key: {api_key}
  - Content-Type: application/json

Body:
{
  "payload": [
    {
      "url": "https://example.com/page",
      "keyword": "anchor text",
      "nofollow": false
    },
    ...
  ],
  "batch_id": 123,
  "chunk_id": 0,
  "domain_id": 456
}

Response:
{
  "success": 95,
  "failed": 5,
  "payload": [
    {
      "status": "success",
      "link_id": "remote_post_id_123"
    },
    {
      "status": "failed",
      "error": "Post not found"
    },
    ...
  ]
}
```

#### 3. Delete Link by URL
```
DELETE {api_url}/hidden-links/by-url
Headers:
  - Authorization: Bearer {api_key}
  - X-API-Key: {api_key}
  - Content-Type: application/json

Body:
{
  "url": "https://example.com/page"
}

Response:
{
  "deleted": 1,
  "message": "Link removed"
}
```

#### 4. Delete Links by Batch ID
```
DELETE {api_url}/hidden-links/by-batch-id
Headers:
  - Authorization: Bearer {api_key}
  - X-API-Key: {api_key}
  - Content-Type: application/json

Body:
{
  "batch_id": 123
}

Response:
{
  "deleted": 250,
  "message": "All links for batch removed"
}
```

### API Service Implementation

The `PbnApiService` class handles all API communication:
- Automatic timeout configuration
- API key authentication (Bearer + X-API-Key)
- SSL verification disabled (for self-signed certs)
- Error handling and exceptions
- Response parsing

---

## Queue System

### Queue Configuration
- **Driver**: Database (no Redis required)
- **Connection**: `QUEUE_CONNECTION=database` in `.env`
- **Tables**: `jobs`, `failed_jobs`

### Queue Names

#### 1. `batch_links` (PublishBatchChunkJob)
- Posts link chunks to remote domains
- 3 retry attempts
- Unique per chunk (prevents duplicates)
- Delayed execution (5 seconds between chunks)

#### 2. `delete_batch_links` (DeleteBatchJob)
- Deletes all batch links from remote sites
- 1 attempt (no retry)
- Long timeout (3600 seconds)
- Unique per batch

#### 3. `remove_link_from_batch` (RemoveLinkFromBatchJob)
- Removes single link from all domains
- 1 attempt
- Timeout: 600 seconds
- Unique per batch+link

#### 4. `domains` (DomainHealthCheckJob)
- Health checks for domains
- 5 retry attempts with backoff
- Backoff: 10s, 1m, 5m, 10m, 15m
- Lock prevents concurrent checks

#### 5. `import_domains` (ImportDomainsJob)
- Imports domains from CSV/Excel
- Processes in background
- Triggers health checks for each domain

### Running Queue Workers

```bash
# Single worker for all queues
php artisan queue:work database

# Specific queue
php artisan queue:work database --queue=batch_links

# Multiple workers (recommended)
php artisan queue:work database --queue=batch_links &
php artisan queue:work database --queue=domains &
php artisan queue:work database --queue=delete_batch_links &
php artisan queue:work database --queue=remove_link_from_batch &
php artisan queue:work database --queue=import_domains &
```

### Job Monitoring
```bash
# View failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job_id}

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## Key Components

### Controllers

#### BatchController
- `index()` - List batches with search
- `create()` - Show batch creation form
- `store()` - Create batch and dispatch jobs
- `show()` - Batch detail with per-domain stats
- `showDomain()` - Domain-specific view in batch
- `publishPending()` - Retry stuck/pending chunks
- `retryFailed()` - Retry failed links
- `destroyLink()` - Remove single link
- `destroy()` - Delete entire batch

#### DomainController
- `index()` - List domains with deduplication
- `store()` - Add domain manually
- `import()` - Import domains from file
- `edit()` - Edit domain form
- `update()` - Update domain
- `destroy()` - Delete domain
- `destroyBulk()` - Bulk delete domains
- `recheck()` - Trigger health check
- `export()` - Export domains to CSV
- `destroyImport()` - Delete import record

#### ReportController
- `index()` - Generate reports with filters
- `exportCsv()` - Stream CSV export
- Pagination with cursor (memory efficient)
- Handles large datasets (100k+ links)

#### DashboardController
- `index()` - Show dashboard statistics

#### SettingsController
- `index()` - Show settings form
- `update()` - Update app settings

### Models

#### Batch
- Relationships: user, links, batchDomainChunks, domains
- `isComplete()` - Check if batch is fully processed

#### BatchDomainChunk
- Chunk size: 100 links
- Statuses: pending, processing, completed, partial
- Accessors: `getLinksAttribute()`, `getResultsAttribute()`

#### Domain
- `normalizeDomain()` - Static method to normalize domain names
- Removes protocol, www, trailing slashes

#### Link
- Simple model with batch and user relationships

### Jobs

#### PublishBatchChunkJob
- Implements `ShouldBeUnique` (prevents duplicate processing)
- Updates chunk status: pending → processing → completed/partial
- Stores results in `results_payload`
- Updates batch counters
- Marks batch as completed when all chunks done

#### DeleteBatchJob
- Deletes links from all remote domains first
- Only deletes batch locally if all remote deletions succeed
- Sets status to 'delete_failed' if any domain fails

#### RemoveLinkFromBatchJob
- Removes link from all domains by URL
- Updates chunk payloads (removes link from array)
- Decrements batch counters
- Deletes link record

#### DomainHealthCheckJob
- Calls GET {api_url}/status
- Retries with exponential backoff
- Uses cache lock to prevent concurrent checks
- Updates domain status and error message

#### ImportDomainsJob
- Parses CSV/Excel files using OpenSpout
- Creates or updates domains
- Triggers health check for each domain
- Deletes file after successful import

### Services

#### PbnApiService
- `postChunk()` - Post link chunk to domain
- `postLink()` - Post single link (legacy)
- `deleteLinkByUrl()` - Delete link by URL
- `deleteLinksByBatchId()` - Delete all batch links
- `ping()` - Health check domain
- Handles authentication, timeouts, errors

### Support Classes

#### PbnSettings
- Cached settings (API timeout, link delay)
- Fallback to config/env
- `getApiTimeoutSeconds()` - Get timeout (10-600s)
- `getLinkDelaySeconds()` - Get delay (0-300s)
- `set()` - Update settings

---

## How It Works

### Complete Workflow Example

**Scenario**: User wants to post 250 links to 3 domains

1. **User creates batch**
   - Name: "Q1 2026 Campaign"
   - Selects 3 active domains
   - Pastes 250 URLs and 250 keywords

2. **System creates records**
   - 1 Batch record
   - 250 Link records
   - 9 BatchDomainChunk records (3 domains × 3 chunks)
     - Domain A: chunks 0, 1, 2 (100, 100, 50 links)
     - Domain B: chunks 0, 1, 2 (100, 100, 50 links)
     - Domain C: chunks 0, 1, 2 (100, 100, 50 links)

3. **Jobs dispatched**
   - 9 PublishBatchChunkJob instances
   - Delayed: 0s, 5s, 10s, 15s, 20s, 25s, 30s, 35s, 40s

4. **Queue worker processes jobs**
   - Job 1: Posts chunk 0 to Domain A (100 links)
   - Job 2: Posts chunk 1 to Domain A (100 links)
   - Job 3: Posts chunk 2 to Domain A (50 links)
   - ... and so on for Domains B and C

5. **Results tracked**
   - Each chunk stores results in `results_payload`
   - Batch counters updated: processed_count, success_count, failed_count
   - User can view progress in real-time

6. **Batch completion**
   - When all 9 chunks complete: status='completed'
   - If any failures: status='partial'
   - User can retry failed links

7. **Reporting**
   - User filters reports by batch
   - Sees all 750 link posts (250 × 3 domains)
   - Exports to CSV for analysis

### Performance Characteristics

- **Chunking**: Prevents timeout on large batches
- **Queue delays**: Prevents overwhelming remote APIs
- **Unique jobs**: Prevents duplicate processing
- **Cursor pagination**: Handles 100k+ report rows
- **Cache locks**: Prevents concurrent health checks
- **Streaming exports**: Memory-efficient CSV generation

### Security Features

- **Authentication**: Laravel Breeze (email verification)
- **Authorization**: User-scoped queries (user_id checks)
- **SQL Injection**: Parameterized queries, wildcard escaping
- **API Security**: Bearer token + X-API-Key headers
- **Input Validation**: Laravel validation rules
- **CSRF Protection**: Laravel CSRF tokens

---

## Modification Guidelines

### Adding New Features

1. **New API Endpoint**
   - Add method to `PbnApiService`
   - Create job if long-running
   - Update API contract documentation

2. **New Batch Operation**
   - Add route in `routes/web.php`
   - Add method to `BatchController`
   - Create job if needed
   - Update batch status logic

3. **New Report Filter**
   - Add filter to `ReportController::index()`
   - Update view with filter UI
   - Ensure CSV export includes filter

4. **New Queue**
   - Create job class
   - Set queue name constant
   - Document in queue section
   - Update worker commands

### Common Modifications

1. **Change chunk size**
   - Update `BatchDomainChunk::CHUNK_SIZE` constant
   - Affects memory and API load

2. **Change retry attempts**
   - Update `$tries` property in job class
   - Update backoff strategy if needed

3. **Change API timeout**
   - Update via Settings page
   - Or modify `PbnSettings::DEFAULTS`

4. **Add new domain field**
   - Create migration
   - Update `Domain` model fillable
   - Update forms and validation

5. **Change link delay**
   - Update via Settings page
   - Or modify `PbnSettings::DEFAULTS`

---

## Troubleshooting

### Common Issues

1. **Batch stuck in "processing"**
   - Click "Publish Pending Chunks" button
   - Checks for stale chunks (>10 minutes)
   - Re-queues pending/stuck chunks

2. **Domain shows "error" status**
   - Check `last_health_error` field
   - Common: Invalid API key, wrong URL, SSL issues
   - Click "Recheck" to retry

3. **Links not posting**
   - Ensure queue worker is running
   - Check `jobs` table for pending jobs
   - Check `failed_jobs` table for errors
   - Verify domain API is accessible

4. **Import stuck**
   - Check import status in domains page
   - Ensure `import_domains` queue worker is running
   - Check file format (CSV/Excel)

5. **Slow reports**
   - Reports use cursor pagination (efficient)
   - Large datasets (100k+ rows) may take time
   - Use filters to narrow results
   - Export to CSV for offline analysis

---

## Environment Variables

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pbn_links
DB_USERNAME=root
DB_PASSWORD=

# Queue
QUEUE_CONNECTION=database

# API (fallback if domain has no api_key)
SITE_API_KEY=your_default_api_key

# App
APP_NAME="PBN Link Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
```

---

## Installation & Setup

```bash
# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate

# Build assets
npm run build

# Run application
php artisan serve

# Run queue workers (separate terminals)
php artisan queue:work database --queue=batch_links
php artisan queue:work database --queue=domains
php artisan queue:work database --queue=delete_batch_links
php artisan queue:work database --queue=remove_link_from_batch
php artisan queue:work database --queue=import_domains
```

---

## Conclusion

This PBN Hidden Link Management System provides a robust, scalable solution for managing backlinks across a large network of domains. The queue-based architecture ensures reliability, while the chunking strategy prevents timeouts and API overload. The comprehensive reporting and tracking features give users full visibility into their link campaigns.

**Key Strengths**:
- Scalable queue-based processing
- Comprehensive error handling and retry logic
- Per-domain and per-batch tracking
- Memory-efficient reporting
- Flexible API integration
- User-friendly interface

**Future Enhancement Opportunities**:
- Real-time progress updates (WebSockets/Pusher)
- Advanced scheduling (post links at specific times)
- Link rotation strategies
- A/B testing for anchor text
- Integration with SEO tools
- Multi-tenant support
- API rate limiting per domain
- Webhook notifications
