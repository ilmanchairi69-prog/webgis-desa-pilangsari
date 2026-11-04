<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class MapLayer extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (MapLayer $layer): void {
            if (PublicationCategory::where('source_layer_id', $layer->id)->exists()) {
                throw new RuntimeException('Layer sumber kategori sistem tidak boleh dihapus.');
            }
        });
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->whereExists(fn ($category) => $category->selectRaw('1')
            ->from('publication_categories')->where('is_public', true)->whereColumn('source_layer_id', 'map_layers.id'));
    }

    protected $fillable = [
        'name', 'slug', 'description', 'layer_type', 'color',
        'is_public', 'status', 'source_name', 'source_notes',
    ];

    protected $casts = ['is_public' => 'boolean'];

    public function features(): HasMany
    {
        return $this->hasMany(MapFeature::class, 'layer_id');
    }
}
