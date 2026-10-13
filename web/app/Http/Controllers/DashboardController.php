<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiSession;
use App\Models\SyncEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_sessions' => AiSession::ownedBy($user)->count(),
                'total_projects' => $user->projects()->count(),
                'last_sync'      => SyncEvent::where('user_id', $user->id)->latest('created_at')->value('created_at'),
            ],
        ]);
    }
}
