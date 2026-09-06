<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppLicense extends Model
{
    use HasFactory;

    protected $table = 'app_licenses';

    protected $fillable = [
        'license_key',
        'status',
        'plan',
        'client_name',
        'client_email',
        'custom_app_name',
        'custom_warehouse_name',
        'custom_logo_url',
        'allowed_modules',
        'max_users',
        'max_devices',
        'expires_at',
        'is_lifetime',
        'last_synced_at',
        'raw_data',
    ];

    protected $casts = [
        'allowed_modules' => 'array',
        'raw_data' => 'array',
        'expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_lifetime' => 'boolean',
        'max_users' => 'integer',
        'max_devices' => 'integer',
    ];

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->is_lifetime) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getClientNameAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        return $this->raw_data['client_name']
            ?? $this->raw_data['company_name']
            ?? $this->raw_data['branding']['client_name']
            ?? $this->raw_data['branding']['company_name']
            ?? null;
    }

    public function getEffectiveAppNameAttribute(): string
    {
        if (!empty($this->custom_app_name)) {
            return $this->custom_app_name;
        }

        if (!empty($this->client_name)) {
            return $this->client_name;
        }

        return config('app.name', 'Vendora Shopee Management');
    }

    public function getEffectiveWarehouseNameAttribute(): string
    {
        return !empty($this->custom_warehouse_name)
            ? $this->custom_warehouse_name
            : 'Gudang Utama';
    }

    public function getEffectiveLogoUrlAttribute(): ?string
    {
        return !empty($this->custom_logo_url)
            ? $this->custom_logo_url
            : null;
    }
}
