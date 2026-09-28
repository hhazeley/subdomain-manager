<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function selfRegistrationEnabled(): bool
    {
        return static::query()
            ->where('key', 'self_registration_enabled')
            ->value('value') === '1';
    }

    public static function setSelfRegistrationEnabled(bool $enabled): void
    {
        static::query()->updateOrCreate(
            ['key' => 'self_registration_enabled'],
            ['value' => $enabled ? '1' : '0'],
        );
    }
}
