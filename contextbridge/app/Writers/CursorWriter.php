<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\PlatformPaths;
use Illuminate\Support\Collection;
use PDO;

class CursorWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'cursor';
    }

    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void
    {
        $storagePath = $this->getStoragePath();

        // Group sessions by resolved project path for workspace registration
        $sessionsByProject = [];

        foreach ($sessions as $session) {
            // Route to the correct project directory.
            // If the session has a project name that differs from the projectPath basename,
            // resolve it as a sibling directory (same parent).
            $sessionProjectPath = $projectPath;
            if ($session->project && strcasecmp(basename($projectPath), $session->project) !== 0) {
                $sessionProjectPath = dirname($projectPath) . DIRECTORY_SEPARATOR . $session->project;
            }

            $encoded    = $this->encodeProjectPath($sessionProjectPath);
            $sessionDir = $storagePath . DIRECTORY_SEPARATOR . $encoded
                . DIRECTORY_SEPARATOR . 'agent-transcripts'
                . DIRECTORY_SEPARATOR . $session->id;

            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0755, true);
            }

            // ── Layer 1: JSONL transcript file ────────────────────────────────
            $lines = [];
            foreach ($session->turns as $turn) {
                if ($turn->role === 'user') {
                    $userText = str_contains($turn->content, '<user_query>')
                        ? $turn->content
                        : "<user_query>\n{$turn->content}\n</user_query>";
                    $lines[] = json_encode([
                        'role'    => 'user',
                        'message' => [
                            'content' => [[
                                'type' => 'text',
                                'text' => $userText,
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    $lines[] = json_encode([
                        'role'    => 'assistant',
                        'message' => [
                            'content' => [[
                                'type' => 'text',
                                'text' => $turn->content,
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE);
                }
            }

            $filePath = $sessionDir . DIRECTORY_SEPARATOR . $session->id . '.jsonl';
            file_put_contents($filePath, implode("\n", $lines) . "\n");

            // ── Layer 2: cursorDiskKV in global state.vscdb ───────────────────
            $this->writeToGlobalDb($session);

            // Collect for Layer 3
            $sessionsByProject[$sessionProjectPath][] = $session;
        }

        // ── Layer 3: workspace DB registration ────────────────────────────
        $this->writeToWorkspaceDbs($sessionsByProject);
    }

    /**
     * Write composerData and bubbleId entries to Cursor's global state.vscdb
     * so the session appears and is openable in the Cursor sidebar.
     */
    protected function writeToGlobalDb(SessionDTO $session): void
    {
        $dbPath = $this->getGlobalDbPath();
        if (!$dbPath || !file_exists($dbPath)) {
            return; // Global DB not found — skip silently (JSONL still written)
        }

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Remove stale entries for this session (idempotent re-import)
        $db->prepare("DELETE FROM cursorDiskKV WHERE key = ?")->execute(["composerData:{$session->id}"]);
        $db->prepare("DELETE FROM cursorDiskKV WHERE key LIKE ?")->execute(["bubbleId:{$session->id}:%"]);

        $createdAt   = $session->createdAt->getTimestampMs();
        $lastUpdated = $session->lastActiveAt->getTimestampMs();
        $tsMs        = $createdAt;

        $headers = [];
        $bubbles = [];

        foreach ($session->turns as $turn) {
            $bubbleId = $this->uuid4();
            $type     = $turn->role === 'user' ? 1 : 2;
            $text     = $turn->role === 'user'
                ? $this->stripUserQuery($turn->content)
                : $turn->content;

            $headers[] = ['bubbleId' => $bubbleId, 'type' => $type];
            $bubbles[$bubbleId] = $this->makeBubble($bubbleId, $type, $text, $tsMs);
            $tsMs += 1000;
        }

        $composerData = $this->makeComposerData($session->id, $session->title, $headers, $createdAt, $lastUpdated);

        $stmt = $db->prepare("INSERT INTO cursorDiskKV (key, value) VALUES (?, ?)");
        $stmt->execute(["composerData:{$session->id}", json_encode($composerData, JSON_UNESCAPED_UNICODE)]);

        foreach ($bubbles as $bubbleId => $bubble) {
            $stmt->execute(["bubbleId:{$session->id}:{$bubbleId}", json_encode($bubble, JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function makeComposerData(
        string $composerId,
        string $name,
        array  $headers,
        int    $createdAt,
        int    $lastUpdated,
    ): array {
        $lastHeader = end($headers) ?: null;

        return [
            '_v'                                    => 14,
            'composerId'                            => $composerId,
            'name'                                  => $name,
            'richText'                              => null,
            'hasLoaded'                             => true,
            'text'                                  => '',
            'fullConversationHeadersOnly'           => $headers,
            'conversationMap'                       => (object)[],
            'status'                                => 'completed',
            'generatingBubbleIds'                   => [],
            'isReadingLongFile'                     => null,
            'codeBlockData'                         => (object)[],
            'originalFileStates'                    => null,
            'newlyCreatedFiles'                     => null,
            'newlyCreatedFolders'                   => null,
            'createdAt'                             => $createdAt,
            'lastUpdatedAt'                         => $lastUpdated,
            'hasChangedContext'                     => false,
            'activeTabsShouldBeReactive'            => null,
            'capabilities'                          => [],
            'isFileListExpanded'                    => false,
            'browserChipManuallyDisabled'           => false,
            'browserChipManuallyEnabled'            => false,
            'unifiedMode'                           => 'chat',
            'forceMode'                             => null,
            'usageData'                             => null,
            'allAttachedFileCodeChunksUris'         => null,
            'modelConfig'                           => ['modelName' => 'default', 'maxMode' => false],
            'subComposerIds'                        => null,
            'subagentComposerIds'                   => null,
            'capabilityContexts'                    => [],
            'todos'                                 => null,
            'isQueueExpanded'                       => false,
            'hasUnreadMessages'                     => false,
            'gitHubPromptDismissed'                 => false,
            'totalLinesAdded'                       => null,
            'totalLinesRemoved'                     => null,
            'addedFiles'                            => null,
            'removedFiles'                          => null,
            'isDraft'                               => false,
            'isCreatingWorktree'                    => false,
            'isApplyingWorktree'                    => false,
            'isUndoingWorktree'                     => false,
            'applied'                               => null,
            'pendingCreateWorktree'                 => null,
            'worktreeStartedReadOnly'               => null,
            'isBestOfNSubcomposer'                  => false,
            'isBestOfNParent'                       => false,
            'bestOfNJudgeWinner'                    => null,
            'isSpec'                                => false,
            'isProject'                             => false,
            'isSpecSubagentDone'                    => null,
            'isContinuationInProgress'              => false,
            'stopHookLoopCount'                     => null,
            'branches'                              => null,
            'speculativeSummarizationEncryptionKey' => null,
            'isNAL'                                 => null,
            'planModeSuggestionUsed'                => null,
            'debugModeSuggestionUsed'               => null,
            'conversationState'                     => '~',
            'queueItems'                            => null,
            'blobEncryptionKey'                     => null,
            'isAgentic'                             => false,
            'agentBackend'                          => null,
            'latestChatGenerationUUID'              => $lastHeader['bubbleId'] ?? null,
            'subtitle'                              => null,
            'filesChangedCount'                     => 0,
            'context'                               => [
                'composers' => [], 'selectedCommits' => [], 'selectedPullRequests' => [],
                'selectedImages' => [], 'folderSelections' => [], 'fileSelections' => [],
                'selections' => [], 'terminalSelections' => [], 'selectedDocs' => [],
                'externalLinks' => [], 'cursorRules' => [], 'cursorCommands' => [],
                'gitPRDiffSelections' => [], 'subagentSelections' => [], 'browserSelections' => [],
                'mentions' => [
                    'composers' => [], 'selectedCommits' => [], 'selectedPullRequests' => [],
                    'gitDiff' => [], 'gitDiffFromBranchToMain' => [], 'selectedImages' => [],
                    'folderSelections' => [], 'fileSelections' => [], 'terminalFiles' => [],
                    'selections' => [], 'terminalSelections' => [], 'selectedDocs' => [],
                    'externalLinks' => [], 'diffHistory' => [], 'cursorRules' => [],
                    'cursorCommands' => [], 'uiElementSelections' => [], 'consoleLogs' => [],
                    'ideEditorsState' => [], 'gitPRDiffSelections' => [], 'subagentSelections' => [],
                    'browserSelections' => [],
                ],
            ],
        ];
    }

    private function makeBubble(string $bubbleId, int $type, string $text, int $tsMs): array
    {
        $base = [
            '_v'                                 => 3,
            'type'                               => $type,
            'approximateLintErrors'              => [],
            'lints'                              => [],
            'codebaseContextChunks'              => [],
            'commits'                            => [],
            'pullRequests'                       => [],
            'attachedCodeChunks'                 => [],
            'assistantSuggestedDiffs'            => [],
            'gitDiffs'                           => [],
            'interpreterResults'                 => [],
            'images'                             => [],
            'attachedFolders'                    => [],
            'attachedFoldersNew'                 => [],
            'bubbleId'                           => $bubbleId,
            'userResponsesToSuggestedCodeBlocks' => [],
            'suggestedCodeBlocks'                => [],
            'diffsForCompressingFiles'           => [],
            'relevantFiles'                      => [],
            'toolResults'                        => [],
            'notepads'                           => [],
            'capabilities'                       => [],
            'multiFileLinterErrors'              => [],
            'diffHistories'                      => [],
            'recentLocationsHistory'             => [],
            'recentlyViewedFiles'                => [],
            'isAgentic'                          => false,
            'fileDiffTrajectories'               => [],
            'existedSubsequentTerminalCommand'   => false,
            'existedPreviousTerminalCommand'     => false,
            'docsReferences'                     => [],
            'webReferences'                      => [],
            'aiWebSearchResults'                 => [],
            'requestId'                          => '',
            'attachedFoldersListDirResults'      => [],
            'humanChanges'                       => [],
            'attachedHumanChanges'               => false,
            'summarizedComposers'                => [],
            'cursorRules'                        => [],
            'cursorCommands'                     => [],
            'cursorCommandsExplicitlySet'        => false,
            'pastChats'                          => [],
            'pastChatsExplicitlySet'             => false,
            'contextPieces'                      => [],
            'editTrailContexts'                  => [],
            'allThinkingBlocks'                  => [],
            'diffsSinceLastApply'                => [],
            'deletedFiles'                       => [],
            'supportedTools'                     => [],
            'tokenCount'                         => ['inputTokens' => 0, 'outputTokens' => 0],
            'attachedFileCodeChunksMetadataOnly' => [],
            'consoleLogs'                        => [],
            'uiElementPicked'                    => [],
            'isRefunded'                         => false,
            'knowledgeItems'                     => [],
            'documentationSelections'            => [],
            'externalLinks'                      => [],
            'projectLayouts'                     => [],
            'unifiedMode'                        => 2, // numeric enum: 2 = agent
            'capabilityContexts'                 => [],
            'todos'                              => [],
            'createdAt'                          => date('c', (int) ($tsMs / 1000)),
            'mcpDescriptors'                     => [],
            'workspaceUris'                      => [],
            'conversationState'                  => '~',
            'text'                               => $text,
            'isPlanExecution'                    => null,
        ];

        if ($type === 1) {
            $base['richText']     = null;
            $base['context']      = null;
            $base['modelInfo']    = null;
            $base['checkpointId'] = null;
        }
        // type=2 (assistant) text-response bubbles have NO capabilityType/thinkingStyle/thinking keys

        return $base;
    }

    /**
     * Layer 3: Register sessions in per-workspace state.vscdb so they appear
     * in the Cursor sidebar when the project is opened.
     *
     * @param array<string, SessionDTO[]> $sessionsByProject project path => sessions
     */
    protected function writeToWorkspaceDbs(array $sessionsByProject): void
    {
        $wsBase = $this->getWorkspaceStoragePath();
        if (!$wsBase || !is_dir($wsBase)) {
            return;
        }

        // Build URI => hash map by scanning workspaceStorage directories
        $wsMap = []; // URI or basename key => ['hash' => ..., 'uri' => ...]
        foreach (glob($wsBase . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'workspace.json') as $wjson) {
            $data   = json_decode(file_get_contents($wjson), true);
            $folder = $data['folder'] ?? '';
            $hash   = basename(dirname($wjson));

            // Index by raw URI (as stored in workspace.json, percent-encoded)
            $wsMap[$folder] = ['hash' => $hash, 'uri' => $folder];

            // Also index by basename for fallback matching
            $decoded  = rawurldecode($folder);
            $parts    = explode('/', rtrim($decoded, '/'));
            $baseName = strtolower(end($parts));
            $wsMap['basename:' . $baseName] = ['hash' => $hash, 'uri' => $folder];
        }

        foreach ($sessionsByProject as $projectPath => $sessions) {
            // Try to find matching workspace
            $projectUri = $this->pathToUri($projectPath);
            $wsInfo = $wsMap[$projectUri]
                ?? $wsMap['basename:' . strtolower(basename($projectPath))]
                ?? null;

            if (!$wsInfo) {
                // No workspace found — session is still in global DB + JSONL,
                // it will be registered when user opens the project and re-imports
                // or runs the registration separately.
                continue;
            }

            $wdbPath = $wsBase . DIRECTORY_SEPARATOR . $wsInfo['hash'] . DIRECTORY_SEPARATOR . 'state.vscdb';
            if (!file_exists($wdbPath)) {
                continue;
            }

            $wdb = new PDO('sqlite:' . $wdbPath);
            $wdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Read or create workspace composerData
            $stmt   = $wdb->prepare("SELECT value FROM ItemTable WHERE key = 'composer.composerData'");
            $stmt->execute();
            $wcdRaw = $stmt->fetchColumn();

            if ($wcdRaw) {
                $wcd = json_decode($wcdRaw, true);
            } else {
                $wcd = ['allComposers' => [], 'currentComposerId' => null];
                $wdb->prepare("INSERT INTO ItemTable (key, value) VALUES ('composer.composerData', ?)")
                    ->execute([json_encode($wcd)]);
            }

            $existingIds = array_column($wcd['allComposers'] ?? [], 'composerId');

            foreach ($sessions as $session) {
                if (in_array($session->id, $existingIds, true)) {
                    continue;
                }

                $entry = [
                    'type'                      => 'head',
                    'composerId'                => $session->id,
                    'createdAt'                 => $session->createdAt->getTimestampMs(),
                    'unifiedMode'               => 'chat',
                    'forceMode'                 => null,
                    'hasUnreadMessages'         => false,
                    'totalLinesAdded'           => 0,
                    'totalLinesRemoved'         => 0,
                    'isArchived'                => false,
                    'isDraft'                   => false,
                    'isWorktree'                => false,
                    'worktreeStartedReadOnly'   => false,
                    'isSpec'                    => false,
                    'isProject'                 => false,
                    'isBestOfNSubcomposer'      => false,
                    'numSubComposers'           => 0,
                    'referencedPlans'           => [],
                    'branches'                  => [],
                    'name'                      => $session->title,
                    'lastUpdatedAt'             => $session->lastActiveAt->getTimestampMs(),
                    'contextUsagePercent'       => null,
                    'hasBlockingPendingActions' => false,
                    'filesChangedCount'         => 0,
                    'subtitle'                  => null,
                ];

                array_unshift($wcd['allComposers'], $entry);
            }

            $wdb->prepare("UPDATE ItemTable SET value = ? WHERE key = 'composer.composerData'")
                ->execute([json_encode($wcd, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * Convert a local filesystem path to a file:/// URI matching Cursor's format.
     * e.g. C:\Work\My Project → file:///c%3A/Work/My%20Project
     */
    private function pathToUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        // Encode drive letter colon: C: → c%3A
        if (preg_match('/^([A-Za-z]):/', $path, $m)) {
            $path = strtolower($m[1]) . '%3A' . substr($path, 2);
        }

        // Encode spaces and other special chars in path segments
        $segments = explode('/', $path);
        $segments = array_map(fn($s) => rawurlencode($s), $segments);
        // rawurlencode also encodes %3A back — fix that
        $encoded = implode('/', $segments);
        $encoded = str_replace('%253A', '%3A', $encoded);

        return 'file:///' . $encoded;
    }

    protected function getStoragePath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.{$this->toolName()}.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    private function getGlobalDbPath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.{$this->toolName()}.global_db.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    private function getWorkspaceStoragePath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.{$this->toolName()}.workspace_storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    /**
     * Encode a project path the way Cursor does:
     *   C:\Work\Personal\Flutter\Composer2 → c-Work-Personal-Flutter-Composer2
     * (lowercase drive letter, single dash separators)
     */
    private function encodeProjectPath(string $path): string
    {
        // Normalise slashes
        $path = str_replace('/', '\\', $path);
        // lowercase drive + replace :\ with -
        $path = preg_replace_callback('/^([A-Za-z]):\\\\/', fn($m) => strtolower($m[1]) . '-', $path) ?? $path;
        // replace remaining backslashes and spaces with dashes
        $path = str_replace(['\\', ' '], '-', $path);
        return $path;
    }

    /**
     * Strip <user_query>...</user_query> wrapper from user message text.
     * Cursor's own exports wrap messages in these tags; we unwrap for the
     * cursorDiskKV bubble text field (display text only).
     */
    private function stripUserQuery(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, '<user_query>') && str_ends_with($text, '</user_query>')) {
            $text = substr($text, strlen('<user_query>'), -strlen('</user_query>'));
            $text = trim($text);
        }
        return $text;
    }

    private function uuid4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
