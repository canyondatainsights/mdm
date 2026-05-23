<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Encrypt the value at rest; decrypt transparently on read. */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => filled($v) ? rescue(fn () => Crypt::decryptString($v), $v, false) : null,
            set: fn ($v) => filled($v) ? Crypt::encryptString($v) : null,
        );
    }

    public static function get(string $key, $default = null)
    {
        return static::query()->where('key', $key)->first()?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
