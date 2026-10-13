<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(Request $request, AiSession $session): Response
    {
        $session->load('project');

        // Sessions are owned through their project; orphaned sessions belong to nobody
        abort_unless($session->project?->user_id === $request->user()->id, 404);

        return Inertia::render('Sessions/Show', [
            'session'     => $session,
            'sessionData' => $session->getDecodedSessionData(),
        ]);
    }
}
