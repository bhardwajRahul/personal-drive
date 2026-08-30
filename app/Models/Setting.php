<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public static string $storagePath = 'storage_path';

    public static string $oldStoragePath = '';

    protected $fillable = ['key', 'value'];

    public static function revertStoragePath(): bool
    {
        if (Setting::$oldStoragePath) {
            return Setting::updateStoragePath(Setting::$oldStoragePath);
        }
        return false;
    }

    public static function updateStoragePath(string $value): bool
    {
        Setting::$oldStoragePath = Setting::getStoragePath();
        return self::setSettingByKeyName(Setting::$storagePath, $value);
    }

    public static function getStoragePath(): string
    {
        return Setting::getSettingByKeyName(Setting::$storagePath);
    }

    public static function getSettingByKeyName(string $key): string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : '';
    }

    public static function setSettingByKeyName(string $key, string $value): bool
    {
        $result = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        return $result->wasRecentlyCreated || $result->wasChanged() || $result->exists;
    }
}
