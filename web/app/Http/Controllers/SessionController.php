<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiSession;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(AiSession $session): Response
    {
        $session->load('project');

        return Inertia::render('Sessions/Show', [
            'session'     => $session,
            'sessionData' => $session->getDecodedSessionData(),
        ]);
    }
}
