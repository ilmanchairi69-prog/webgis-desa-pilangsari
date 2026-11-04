<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyCard extends Model
{
    protected static function booted(): void
    {
        static::saving(function (FamilyCard $card): void {
            if ($card->isDirty('head_name') && filled($card->head_name)) {
                $card->head_of_family_name = $card->head_name;
            } elseif ($card->isDirty('head_of_family_name')) {
                $card->head_name = $card->head_of_family_name;
            }
        });
    }

    protected $fillable = [
        'house_id',
        'head_of_family_name',
        'card_number',
        'head_name',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['card_number' => 'encrypted'];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'family_card_id');
    }

    public function members(): HasMany
    {
        return $this->familyMembers();
    }
}
