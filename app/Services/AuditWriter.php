<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditWriter
{
    public static function write(string $action, ?Model $subject = null, array $before = [], array $after = []): void
    {
        $request = app(Request::class);
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => $subject ? $subject::class : 'system',
            'entity_id' => $subject?->getKey(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before_data' => self::safeSnapshot($before) ?: null,
            'after_data' => self::safeSnapshot($after) ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    public static function houseSnapshot(Model $house): array
    {
        return [
            'house_code' => $house->house_code,
            'rt_code' => $house->rt_code,
            'latitude' => $house->latitude,
            'longitude' => $house->longitude,
            'verification_status' => $house->verification_status,
            'publication_status' => $house->publication_status,
            'public_visible' => $house->public_visible,
        ];
    }

    private static function safeSnapshot(array $snapshot): array
    {
        $sensitiveKeys = [
            'password', 'passwordconfirmation', 'remembertoken', 'token',
            'accesstoken', 'apikey', 'secret', 'nik', 'cardnumber',
            'headname', 'headoffamilyname', 'fullname', 'members',
            'households', 'properties',
            'authorization', 'cookie', 'sessionid',
        ];
        $safe = [];
        foreach ($snapshot as $key => $value) {
            $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if (in_array($normalizedKey, $sensitiveKeys, true)
                || str_contains($normalizedKey, 'password')
                || str_ends_with($normalizedKey, 'token')
                || (is_object($value) || is_resource($value))) {
                continue;
            }
            if ($normalizedKey === 'geometry' && is_array($value)) {
                $value = array_intersect_key($value, array_flip(['type', 'coordinates']));
            }
            $safe[$key] = is_array($value) ? self::safeSnapshot($value) : $value;
        }

        return $safe;
    }
}
