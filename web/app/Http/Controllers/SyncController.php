<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SyncBlob;
use App\Models\SyncEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * POST /api/sync/push — body: { sessions: <encrypted blob>, count?: int }
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sessions'    => ['required', 'string'],
            'count'       => ['nullable', 'integer', 'min:0'],
            'source_tool' => ['nullable', 'string', 'max:50'],
        ]);

        $user  = $request->user();
        $count = (int) ($validated['count'] ?? 0);

        // Stored as-is: the payload stays encrypted at rest
        SyncBlob::updateOrCreate(
            ['user_id' => $user->id],
            ['payload' => $validated['sessions'], 'session_count' => $count],
        );

        SyncEvent::create([
            'user_id'       => $user->id,
            'event_type'    => 'push',
            'session_count' => $count,
            'source_tool'   => $validated['source_tool'] ?? null,
        ]);

        return response()->json(['status' => 'ok', 'count' => $count]);
    }

    /**
     * GET /api/sync/pull — returns { sessions: <encrypted blob>|null, count: int }
     */
    public function pull(Request $request): JsonResponse
    {
        $user = $request->user();
        $blob = SyncBlob::where('user_id', $user->id)->first();

        if ($blob === null) {
            return response()->json(['sessions' => null, 'count' => 0]);
        }

        SyncEvent::create([
            'user_id'       => $user->id,
            'event_type'    => 'pull',
            'session_count' => $blob->session_count,
        ]);

        return response()->json(['sessions' => $blob->payload, 'count' => $blob->session_count]);
    }
}
