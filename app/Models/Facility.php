<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNull('archived_at');
    }

    protected $fillable = ['name', 'category', 'description', 'address', 'latitude', 'longitude', 'status', 'created_by', 'updated_by', 'archived_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'archived_at' => 'datetime'];
    }
}
