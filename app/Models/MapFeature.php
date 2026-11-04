<?php

namespace App\Models;

use App\Services\GeoJsonGeometry;
use App\Services\PublicFeatureData;
use App\Services\VillageBoundaryValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MapFeature extends Model
{
    protected $fillable = [
        'layer_id', 'name', 'description', 'geometry', 'properties',
        'category', 'verification_status', 'publication_status',
        'source_name', 'latitude', 'longitude', 'created_by',
    ];

    protected $casts = [
        'geometry' => 'array',
        'properties' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MapLayer::class, 'layer_id');
    }

    public function house(): HasOne
    {
        return $this->hasOne(House::class, 'map_feature_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $visibility): void {
            $visibility->whereHas('house', fn (Builder $house) => $house->publiclyVisible())
                ->orWhere(function (Builder $legacy): void {
                    $legacy->whereDoesntHave('house')->where('verification_status', 'verified')
                        ->where('publication_status', 'published')->whereNotNull('category')->where('category', '!=', 'rumah');
                });
        });
    }

    public function toGeoJsonFeature(): array
    {
        return (new PublicFeatureData($this))->toArray();
    }

    public function publicName(): string
    {
        return $this->house?->house_code ?? $this->name;
    }

    public function publicGeometry(): ?array
    {
        if ($this->house) {
            return $this->house->publicGeometry();
        }

        $geometry = $this->geometry;
        if (($geometry === null || $geometry === []) && $this->latitude !== null && $this->longitude !== null) {
            $geometry = ['type' => 'Point', 'coordinates' => [$this->longitude, $this->latitude]];
        }

        if (! GeoJsonGeometry::matchesLayerType($geometry, $this->layer?->layer_type)
            || ! app(VillageBoundaryValidator::class)->geometryWithinBoundary($geometry)) {
            return null;
        }

        return ['type' => $geometry['type'], 'coordinates' => $geometry['coordinates']];
    }
}
