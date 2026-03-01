<?php

declare(strict_types=1);

namespace Plugs\Security\Identity\Models;

use Plugs\Base\Model\PlugModel;

class IdentityChallenge extends PlugModel
{
    protected $table = 'identity_challenges';

    protected $fillable = [
        'email',
        'nonce',
        'used',
        'expires_at',
    ];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Find a valid (unused and non-expired) challenge by email and nonce.
     */
    public static function findValid(string $email, string $nonce): ?self
    {
        return static::where('email', '=', $email)
            ->where('nonce', '=', $nonce)
            ->where('used', '=', false)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Mark the challenge as used.
     */
    public function use(): void
    {
        $this->fill(['used' => true])->save();
    }
}
