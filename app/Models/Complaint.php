<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    public const CATEGORIES = ['infrastructure' => 'Infrastruktur dan Jalan', 'administration' => 'Pelayanan Administrasi', 'environment' => 'Kebersihan dan Lingkungan', 'social' => 'Sosial dan Kemasyarakatan', 'security' => 'Keamanan dan Ketertiban', 'agriculture' => 'Pertanian dan Peternakan', 'facilities' => 'Fasilitas Umum', 'aspiration' => 'Aspirasi dan Saran', 'other' => 'Lainnya'];

    public const STATUSES = ['new' => 'Pengaduan Baru', 'reviewing' => 'Sedang Ditinjau', 'in_progress' => 'Sedang Ditindaklanjuti', 'resolved' => 'Selesai', 'rejected' => 'Tidak Dapat Ditindaklanjuti'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
