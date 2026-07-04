<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getValue(string $key, array $default = []): array
    {
        $row = static::query()->where('key', $key)->first();
        if (! $row || ! is_array($row->value)) {
            return $default;
        }

        return $row->value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function putValue(string $key, array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
