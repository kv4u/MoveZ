<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiSession;
use App\Models\SyncEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => ['required', 'string'],
        ]);

        $user = auth()->user();

        // Store the encrypted blob (it stays encrypted at rest)
        AiSession::updateOrCreate(
            ['session_id' => 'sync_blob_' . $user->id],
            [
                'project_id'   => null,
                'source_tool'  => 'sync',
                'title'        => 'Synced sessions',
                'session_data' => $validated['data'],
                'exported_at'  => now(),
            ]
        );

        SyncEvent::create([
            'user_id'       => $user->id,
            'event_type'    => 'push',
            'session_count' => 0,
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function pull(Request $request): JsonResponse
    {
        $user = auth()->user();

        $blob = AiSession::where('session_id', 'sync_blob_' . $user->id)->first();

        if (!$blob) {
            return response()->json(['data' => null]);
        }

        SyncEvent::create([
            'user_id'    => $user->id,
            'event_type' => 'pull',
        ]);

        return response()->json(['data' => $blob->session_data]);
    }
}
