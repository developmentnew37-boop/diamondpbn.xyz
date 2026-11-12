<?php

namespace Tests\Unit;

use App\Support\ImportSiteRow;
use PHPUnit\Framework\TestCase;

class ImportSiteRowTest extends TestCase
{
    public function test_named_export_headers_map_to_fields(): void
    {
        $row = [
            'Domain' => 'example.com',
            'API URL' => 'https://example.com/api',
            'API Key' => 'secret-key',
            'Status' => 'active',
        ];

        $this->assertSame('example.com', ImportSiteRow::domain($row));
        $this->assertSame('https://example.com/api', ImportSiteRow::apiUrl($row));
        $this->assertSame('secret-key', ImportSiteRow::apiKey($row));
    }

    public function test_old_export_status_column_is_not_used_as_api_key(): void
    {
        $row = [
            0 => 'example.com',
            1 => 'https://example.com/api',
            2 => 'active',
        ];

        $this->assertNull(ImportSiteRow::apiKey($row));
    }

    public function test_positional_api_key_is_kept(): void
    {
        $row = [
            0 => 'example.com',
            1 => 'https://example.com/api',
            2 => 'real-key',
        ];

        $this->assertSame('real-key', ImportSiteRow::apiKey($row));
    }
}
