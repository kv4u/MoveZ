<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::with('aiSessions')
            ->withCount('aiSessions')
            ->latest()
            ->paginate(20);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load('aiSessions');

        return Inertia::render('Projects/Show', [
            'project'  => $project,
            'sessions' => $project->aiSessions()->latest()->get(),
        ]);
    }
}
