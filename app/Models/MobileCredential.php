<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileCredential extends Model
{
    protected $fillable = [
        'plain_text_token',
        'user',
        'access',
        'locale',
        'site_config',
        'site_config_fetched_at',
        'biometrics_enabled',
        'locked',
        'last_validated_at',
        'unlocked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plain_text_token' => 'encrypted',
            'user' => 'encrypted:array',
            'access' => 'encrypted:array',
            'site_config' => 'encrypted:array',
            'biometrics_enabled' => 'boolean',
            'locked' => 'boolean',
            'last_validated_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'site_config_fetched_at' => 'datetime',
        ];
    }
}
