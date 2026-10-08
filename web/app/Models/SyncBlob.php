<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest encrypted session payload pushed by a user's CLI.
 */
class SyncBlob extends Model
{
    protected $fillable = ['user_id', 'payload', 'session_count'];

    protected $hidden = ['payload'];

    protected $casts = [
        'session_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
