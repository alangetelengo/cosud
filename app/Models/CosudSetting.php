<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CosudSetting extends Model
{
    public const LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT = 'lecture_dossier_lors_partage_document';

    protected $table = 'cosud_settings';

    protected $fillable = [
        'cle',
        'valeur',
    ];

    public static function bool(string $cle, bool $default = false): bool
    {
        $cacheKey = 'cosud_setting_bool:'.$cle;

        return (bool) Cache::remember($cacheKey, 300, function () use ($cle, $default) {
            $raw = static::query()->where('cle', $cle)->value('valeur');
            if ($raw === null) {
                return $default;
            }

            $decoded = json_decode((string) $raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $default;
            }

            return (bool) $decoded;
        });
    }

    public static function setBool(string $cle, bool $value): void
    {
        static::query()->updateOrInsert(
            ['cle' => $cle],
            [
                'valeur' => json_encode($value),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        Cache::forget('cosud_setting_bool:'.$cle);
    }

    public static function lectureDossierLorsPartageDocument(): bool
    {
        return static::bool(
            self::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT,
            (bool) config('cosud.defaults.lecture_dossier_lors_partage_document', false)
        );
    }
}
