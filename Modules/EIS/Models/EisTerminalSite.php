<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EisTerminalSite extends Model
{
    protected $table = 'eis_terminal_sites';

    protected $fillable = [
        'terminal_configuration_id',
        'site_id',
        'site_name',
        'raw_data',
        'last_synced_at'
    ];

    protected $casts = [
        'last_synced_at' => 'datetime'
    ];

    /**
     * Get the terminal configuration that owns the site.
     */
    public function terminalConfiguration(): BelongsTo
    {
        return $this->belongsTo(EisTerminalConfiguration::class, 'terminal_configuration_id');
    }

    /**
     * Get full site name with ID.
     */
    public function getFullNameAttribute(): string
    {
        return $this->site_name . ' (' . $this->site_id . ')';
    }

    /**
     * Scope for sites by name.
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('site_name', 'like', "%{$name}%");
    }

    /**
     * Scope for sites by ID.
     */
    public function scopeBySiteId($query, string $siteId)
    {
        return $query->where('site_id', $siteId);
    }
}