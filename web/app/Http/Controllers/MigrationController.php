<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MigrationController extends Controller
{
    /** Tools the CLI can read from. */
    private const SOURCE_TOOLS = ['cursor', 'claude-code', 'codex', 'copilot-cli', 'cline', 'continue'];

    /** Tools the CLI can write to. */
    private const TARGET_TOOLS = ['cursor', 'claude-code', 'codex', 'copilot-cli'];

    public function wizard(Request $request): Response
    {
        return Inertia::render('Migration/Wizard', [
            'supportedTools' => self::SOURCE_TOOLS,
            'writableTools'  => self::TARGET_TOOLS,
            'projects'       => $request->user()->projects()->latest()->get(['id', 'name', 'path']),
        ]);
    }

    /**
     * Session files live on the user's machine, not on this server, so the
     * wizard validates the choices and returns the CLI command to run locally.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_tool'  => ['required', 'string', 'in:' . implode(',', self::SOURCE_TOOLS)],
            'to_tool'    => ['required', 'string', 'in:' . implode(',', self::TARGET_TOOLS), 'different:from_tool'],
            'project_id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->where('user_id', $request->user()->id),
            ],
            'from_path'  => ['nullable', 'string', 'max:1024'],
            'to_path'    => ['nullable', 'string', 'max:1024'],
        ]);

        $projectPath = isset($validated['project_id'])
            ? Project::whereKey($validated['project_id'])->value('path')
            : null;

        $args = [
            'movez', 'transfer',
            '--from=' . $validated['from_tool'],
            '--to=' . $validated['to_tool'],
        ];

        if ($projectPath) {
            $args[] = '--project=' . $this->quote($projectPath);
        }

        if (!empty($validated['from_path']) && !empty($validated['to_path'])) {
            $args[] = '--from-path=' . $this->quote($validated['from_path']);
            $args[] = '--to-path=' . $this->quote($validated['to_path']);
        }

        return response()->json([
            'status'  => 'ready',
            'message' => 'Run this command on the machine that has your sessions.',
            'command' => implode(' ', $args),
        ]);
    }

    private function quote(string $value): string
    {
        return preg_match('#^[A-Za-z0-9_/\\\\:.\-]+$#', $value) ? $value : '"' . str_replace('"', '\"', $value) . '"';
    }
}
