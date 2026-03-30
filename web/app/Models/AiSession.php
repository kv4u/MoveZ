<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSession extends Model
{
    use HasFactory;

    protected $table = 'ai_sessions';

    protected $fillable = [
        'project_id', 'source_tool', 'session_id', 'title', 'session_data', 'exported_at',
    ];

    protected $casts = [
        'exported_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Decrypt and decode session_data on the fly.
     */
    public function getDecodedSessionData(): array
    {
        $raw = $this->session_data;
        return is_array($raw) ? $raw : (json_decode($raw, true) ?? []);
    }
}
