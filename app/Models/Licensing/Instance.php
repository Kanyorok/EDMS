<?php

namespace App\Models\Licensing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Instance extends Model
{
    protected $table = 't_Instance';
    public $timestamps = false;

    protected $fillable = [
        'DbGuid', 'HostFingerprint', 'MaxSeenNonce', 'CreatedOn',
    ];

    /**
     * Get the current instance record, creating one if it doesn't exist.
     */
    public static function current(): static
    {
        $instance = static::first();

        if (! $instance) {
            $instance = static::create([
                'DbGuid' => Str::uuid()->toString(),
                'HostFingerprint' => static::generateFingerprint(),
                'MaxSeenNonce' => 0,
                'CreatedOn' => now(),
            ]);
        }

        return $instance;
    }

    /**
     * Generate a fingerprint string based on the host system info.
     */
    public static function generateFingerprint(): string
    {
        return implode('|', [
            php_uname('n'), // hostname
            php_uname('s'), // OS name
            php_uname('r'), // OS release
        ]);
    }
}
