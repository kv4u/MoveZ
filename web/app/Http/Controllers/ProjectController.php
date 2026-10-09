<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = $request->user()->projects()
            ->withCount('aiSessions')
            ->latest()
            ->paginate(20);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        // 404 rather than 403 so other users' project ids are not revealed
        abort_unless($project->user_id === $request->user()->id, 404);

        return Inertia::render('Projects/Show', [
            'project'  => $project,
            'sessions' => $project->aiSessions()->latest()->get(),
        ]);
    }
}
