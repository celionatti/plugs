<?php

declare(strict_types=1);

namespace Plugs\Database\Models;

use Plugs\Base\Model\PlugModel;

class Audit extends PlugModel
{
    protected $table = 'audits';
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'old_values',
        'new_values',
        'reason',
        'context',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array'
    ];
}
