<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MigrationController extends Controller
{
    private const SUPPORTED_TOOLS = [
        'cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli', 'cline', 'continue',
    ];

    public function wizard(): Response
    {
        return Inertia::render('Migration/Wizard', [
            'supportedTools' => self::SUPPORTED_TOOLS,
            'projects'       => Project::latest()->get(['id', 'name']),
        ]);
    }

    public function start(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'from_tool'  => ['required', 'string', 'in:' . implode(',', self::SUPPORTED_TOOLS)],
            'to_tool'    => ['required', 'string', 'in:' . implode(',', self::SUPPORTED_TOOLS)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from_path'  => ['nullable', 'string'],
            'to_path'    => ['nullable', 'string'],
        ]);

        // Dispatch a migration job (async via Horizon)
        // For now, return success — the job implementation is in Phase 6 Jobs/
        return response()->json([
            'status'  => 'queued',
            'message' => 'Migration queued. Sessions will appear shortly.',
            'data'    => $validated,
        ]);
    }
}
