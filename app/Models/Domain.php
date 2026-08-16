<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_import_id',
        'domain',
        'domain_normalized',
        'api_url',
        'api_key',
        'api_secret',
        'status',
        'last_checked_at',
        'last_health_error',
        'notes',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domainImport(): BelongsTo
    {
        return $this->belongsTo(DomainImport::class);
    }

    public static function normalizeDomain(string $domain): string
    {
        $d = strtolower(trim($domain));
        $d = preg_replace('#^https?://#i', '', $d) ?? $d;
        $d = preg_replace('#^www\.#i', '', $d) ?? $d;
        $d = rtrim($d, "/ \t\n\r\0\x0B");
        return $d;
    }
}
