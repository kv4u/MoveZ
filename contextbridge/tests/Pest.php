<?php
declare(strict_types=1);

// Use the Laravel Zero TestCase for both Feature and Unit tests so config() is available
uses(Tests\TestCase::class)->in('Feature', 'Unit');

/**
 * Create a temp directory, pass it to a callback, then delete it.
 */
function withTempDir(\Closure $callback): void
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cb_test_' . uniqid('', true);
    mkdir($dir, 0755, true);

    try {
        $callback($dir);
    } finally {
        $it    = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}

/**
 * Create a minimal Cursor-style JSONL agent-transcript fixture.
 * Each session in $sessions has: id, title (unused in JSONL), turns[]{role, text}.
 * The encoded project dir is 'test-project'.
 * Returns the root temp dir (== the storage path to pass to getStoragePath).
 */
function makeCursorFixture(string $dir, array $sessions = []): string
{
    foreach ($sessions as $session) {
        $id         = $session['id'] ?? uniqid('s');
        $turns      = $session['turns'] ?? [];
        $sessionDir = $dir
            . DIRECTORY_SEPARATOR . 'test-project'
            . DIRECTORY_SEPARATOR . 'agent-transcripts'
            . DIRECTORY_SEPARATOR . $id;

        mkdir($sessionDir, 0755, true);

        $lines = [];
        foreach ($turns as $turn) {
            $role = $turn['type'] === 'human' ? 'user' : 'assistant';
            $text = $turn['text'] ?? '';
            if ($role === 'user') {
                $text = "<user_query>\n{$text}\n</user_query>";
            }
            $lines[] = json_encode([
                'role'    => $role,
                'message' => ['content' => [['type' => 'text', 'text' => $text]]],
            ]);
        }

        file_put_contents(
            $sessionDir . DIRECTORY_SEPARATOR . $id . '.jsonl',
            implode("\n", $lines) . "\n"
        );
    }

    return $dir;
}
