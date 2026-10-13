<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /** The raw payload can be large; pages receive the decoded form explicitly. */
    protected $hidden = ['session_data'];

    protected $casts = [
        'exported_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Sessions belonging to the user's projects. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    /**
     * Decode session_data, falling back to an empty array for anything
     * that is not a JSON object/array.
     *
     * @return array<string, mixed>
     */
    public function getDecodedSessionData(): array
    {
        $raw = $this->session_data;

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
