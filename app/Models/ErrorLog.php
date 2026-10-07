<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint',
        'level',
        'status_code',
        'exception_class',
        'message',
        'code',
        'file',
        'line',
        'method',
        'url',
        'route_name',
        'user_id',
        'user_role',
        'ip_address',
        'user_agent',
        'context',
        'stack_trace',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
