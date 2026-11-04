<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FamilyMember extends Model
{
    protected static function booted(): void
    {
        static::saving(function (FamilyMember $member): void {
            if ($member->family_card_id !== null) {
                $card = FamilyCard::findOrFail($member->family_card_id);
                if ($member->isDirty('house_id') && $member->house_id !== null
                    && (int) $member->house_id !== (int) $card->house_id) {
                    throw ValidationException::withMessages([
                        'family_card_id' => 'Rumah anggota harus mengikuti rumah pada kartu keluarga.',
                    ]);
                }
                $member->house_id = $card->house_id;
            }
        });
    }

    protected $fillable = [
        // Relasi baru untuk anggota dalam satu KK.
        'family_card_id',

        // Kolom lama dipertahankan selama masa transisi.
        'house_id',
        'relationship',
        'date_of_birth',
        'gender',

        'full_name',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function familyCard(): BelongsTo
    {
        return $this->belongsTo(FamilyCard::class, 'family_card_id');
    }

    /*
     * Relasi lama dipertahankan sementara.
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class, 'house_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
