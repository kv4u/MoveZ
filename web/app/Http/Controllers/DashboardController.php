<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiSession;
use App\Models\Project;
use App\Models\SyncEvent;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_sessions' => AiSession::count(),
                'total_projects' => Project::count(),
                'last_sync'      => SyncEvent::latest('created_at')->value('created_at'),
            ],
        ]);
    }
}
