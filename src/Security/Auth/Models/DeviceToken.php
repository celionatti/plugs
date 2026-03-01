<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Models;

use Plugs\Base\Model\PlugModel;

class DeviceToken extends PlugModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'device_tokens';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'token_hash',
        'device_name',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Find a valid (non-expired) device token by its hash.
     */
    public static function findValidToken(string $tokenHash): ?self
    {
        return static::where('token_hash', '=', $tokenHash)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Create or update a device token for a user.
     * Enforces a single trusted device per user.
     *
     * @return array{model: self, raw_token: string}
     */
    public static function createForUser(
        int $userId,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        int $lifetimeDays = 90
    ): array {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$lifetimeDays} days"));

        $model = static::where('user_id', '=', $userId)->first();

        if ($model) {
            $model->fill([
                'token_hash' => $tokenHash,
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'last_used_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
            ]);
            $model->save();
        } else {
            $model = static::create([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'last_used_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
            ]);
        }

        return [
            'model' => $model,
            'raw_token' => $rawToken,
        ];
    }

    /**
     * Touch the last_used_at timestamp.
     */
    public function touchLastUsed(?string $ipAddress = null): void
    {
        $this->fill([
            'last_used_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ipAddress ?: $this->ip_address,
        ]);
        $this->save();
    }
}
