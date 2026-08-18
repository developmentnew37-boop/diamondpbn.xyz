<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpSite extends Model
{
    protected $fillable = [
        'user_id',
        'wp_site_import_id',
        'domain',
        'domain_normalized',
        'api_url',
        'api_key',
        'status',
        'last_checked_at',
        'last_health_error',
        'block_inspect',
        'block_inspect_synced_at',
        'block_inspect_supported',
        'notes',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'block_inspect' => 'boolean',
        'block_inspect_synced_at' => 'datetime',
        'block_inspect_supported' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wpSiteImport(): BelongsTo
    {
        return $this->belongsTo(WpSiteImport::class);
    }

    public static function normalizeDomain(string $domain): string
    {
        $d = strtolower(trim($domain));
        $d = preg_replace('#^https?://#i', '', $d) ?? $d;
        $d = preg_replace('#^www\.#i', '', $d) ?? $d;

        return rtrim($d, "/ \t\n\r\0\x0B");
    }
}
