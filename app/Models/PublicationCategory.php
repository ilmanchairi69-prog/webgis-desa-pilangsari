<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationCategory extends Model
{
    public const Houses = 'houses';

    public const Facilities = 'facilities';

    public const Labels = [self::Houses => 'Rumah Warga', self::Facilities => 'Fasilitas Umum'];

    protected $primaryKey = 'system_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['source_layer_id', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    public function sourceLayer(): BelongsTo
    {
        return $this->belongsTo(MapLayer::class, 'source_layer_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public static function source(string $key): ?MapLayer
    {
        return self::find($key)?->sourceLayer;
    }
}
