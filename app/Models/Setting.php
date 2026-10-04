<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['outlet_id', 'scope_key', 'group', 'key', 'value', 'is_encrypted', 'updated_by'];

    protected function casts(): array { return ['value' => 'array', 'is_encrypted' => 'boolean']; }

    public static function value(string $key, mixed $default = null, ?int $outletId = null): mixed
    {
        $scope = $outletId ? 'outlet:'.$outletId : 'global';
        $setting = self::query()->where('scope_key', $scope)->where('key', $key)->first();
        if (! $setting && $outletId) {
            $setting = self::query()->where('scope_key', 'global')->where('key', $key)->first();
        }

        $value = $setting?->value;
        if ($setting?->is_encrypted && is_array($value) && isset($value['ciphertext'])) {
            return decrypt($value['ciphertext']);
        }

        return $value ?? $default;
    }

    public static function write(string $key, mixed $value, string $group = 'general', ?int $outletId = null, bool $encrypted = false): self
    {
        $scope = $outletId ? 'outlet:'.$outletId : 'global';
        $stored = $encrypted ? ['ciphertext' => encrypt($value)] : $value;

        return self::query()->updateOrCreate(
            ['scope_key' => $scope, 'key' => $key],
            ['outlet_id' => $outletId, 'group' => $group, 'value' => $stored, 'is_encrypted' => $encrypted, 'updated_by' => auth()->id()],
        );
    }
}
