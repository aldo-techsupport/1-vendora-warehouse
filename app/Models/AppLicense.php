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

    public const OFFLINE_GRACE_DAYS = 7;

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (! $this->is_lifetime && $this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Offline grace period check: If device has been offline longer than grace days limit
        if (! $this->is_lifetime && $this->last_synced_at && $this->last_synced_at->diffInDays(now()) > self::OFFLINE_GRACE_DAYS) {
            return false;
        }

        return true;
    }

    /**
     * Check if the license is running in offline grace period mode (online sync failed recently).
     */
    public function isOfflineGraceActive(): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        if (! $this->last_synced_at) {
            return false;
        }

        // Active offline grace if last sync was more than 24 hours ago but within grace period
        return $this->last_synced_at->diffInHours(now()) >= 24;
    }

    /**
     * Get remaining days in the offline grace period.
     */
    public function daysUntilOfflineExpiry(): int
    {
        if (! $this->last_synced_at) {
            return 0;
        }

        $daysSinceSync = (int) $this->last_synced_at->diffInDays(now());

        return max(0, self::OFFLINE_GRACE_DAYS - $daysSinceSync);
    }

    /**
     * Get human readable reason if license is invalid.
     */
    public function getInvalidReason(): string
    {
        if ($this->status === 'expired' || (! $this->is_lifetime && $this->expires_at && $this->expires_at->isPast())) {
            return 'Masa berlaku lisensi Anda telah berakhir pada '.($this->expires_at ? $this->expires_at->translatedFormat('d F Y') : 'hari ini').'. Silakan perpanjang lisensi Anda.';
        }

        if ($this->status === 'suspended') {
            return 'Lisensi ini ditangguhkan (suspended) oleh administrator cloud.';
        }

        if ($this->status === 'revoked') {
            return 'Lisensi ini telah dicabut (revoked) oleh administrator cloud.';
        }

        if (! $this->is_lifetime && $this->last_synced_at && $this->last_synced_at->diffInDays(now()) > self::OFFLINE_GRACE_DAYS) {
            return 'Batas toleransi verifikasi offline ('.self::OFFLINE_GRACE_DAYS.' hari) telah terlampaui. Sambungkan perangkat ke internet untuk memverifikasi lisensi kembali.';
        }

        if ($this->status !== 'active') {
            return 'Status lisensi tidak aktif ('.$this->status.').';
        }

        return 'Lisensi tidak valid.';
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
