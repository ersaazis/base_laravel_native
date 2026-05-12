<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileCredential extends Model
{
    protected $fillable = [
        'client_id',
        'plain_text_token',
        'user',
        'access',
        'locale',
        'site_config',
        'site_config_fetched_at',
        'last_validated_at',
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
            'last_validated_at' => 'datetime',
            'site_config_fetched_at' => 'datetime',
        ];
    }
}
