<?php

namespace App\Models;

use App\Services\GeoJsonGeometry;
use App\Services\VillageBoundaryValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class House extends Model
{
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified')->where('publication_status', 'published')
            ->whereNull('archived_at')->where('public_visible', true);
    }

    /**
     * @return array{status: 'missing'|'incomplete'|'invalid'|'valid', label: string, latitude: ?float, longitude: ?float}
     */
    public function coordinatePresentation(int $precision = 6): array
    {
        $rawLatitude = $this->getRawOriginal('latitude');
        $rawLongitude = $this->getRawOriginal('longitude');
        $latitudeMissing = $rawLatitude === null || $rawLatitude === '';
        $longitudeMissing = $rawLongitude === null || $rawLongitude === '';

        if ($latitudeMissing && $longitudeMissing) {
            return ['status' => 'missing', 'label' => 'Koordinat belum tersedia', 'latitude' => null, 'longitude' => null];
        }

        if ($latitudeMissing || $longitudeMissing) {
            return ['status' => 'incomplete', 'label' => 'Koordinat tidak lengkap', 'latitude' => null, 'longitude' => null];
        }

        if (! is_numeric($rawLatitude) || ! is_numeric($rawLongitude)) {
            return ['status' => 'invalid', 'label' => 'Koordinat tidak valid', 'latitude' => null, 'longitude' => null];
        }

        $latitude = (float) $rawLatitude;
        $longitude = (float) $rawLongitude;
        if (! is_finite($latitude) || ! is_finite($longitude)
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return ['status' => 'invalid', 'label' => 'Koordinat tidak valid', 'latitude' => null, 'longitude' => null];
        }

        return [
            'status' => 'valid',
            'label' => number_format($latitude, $precision, '.', '').', '.number_format($longitude, $precision, '.', ''),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    public function publicGeometry(): ?array
    {
        $geometry = ['type' => 'Point', 'coordinates' => [$this->longitude, $this->latitude]];
        if ($this->latitude === null && $this->longitude === null) {
            $feature = $this->mapFeature;
            $geometry = $feature?->geometry ?: ['type' => 'Point', 'coordinates' => [$feature?->longitude, $feature?->latitude]];
        }

        if (($geometry['type'] ?? null) !== 'Point' || ! GeoJsonGeometry::isValid($geometry)
            || ! app(VillageBoundaryValidator::class)->geometryWithinBoundary($geometry)) {
            return null;
        }

        return ['type' => 'Point', 'coordinates' => array_map('floatval', $geometry['coordinates'])];
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }
        if (! $user->isRtAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if (filled($user->rt_code)) {
                $scope->where('rt_code', $user->rt_code)->orWhere(function (Builder $legacy) use ($user): void {
                    $legacy->whereNull('rt_code')->where('created_by', $user->id);
                });
            } else {
                $scope->whereNull('rt_code')->where('created_by', $user->id);
            }
        });
    }

    protected $fillable = [
        'map_feature_id', 'house_code', 'house_label', 'rt_code', 'address', 'latitude', 'longitude',
        'verification_status', 'publication_status', 'public_visible', 'verified_by',
        'verified_at', 'published_by', 'published_at', 'rejection_note', 'created_by', 'updated_by', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'public_visible' => 'boolean', 'verified_at' => 'datetime', 'published_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function mapFeature(): BelongsTo
    {
        return $this->belongsTo(MapFeature::class, 'map_feature_id');
    }

    public function familyCards(): HasMany
    {
        return $this->hasMany(FamilyCard::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
