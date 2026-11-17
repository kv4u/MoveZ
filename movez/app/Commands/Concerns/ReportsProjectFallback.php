<?php
declare(strict_types=1);

namespace App\Commands\Concerns;

trait ReportsProjectFallback
{
    /** @param array{written: array<string, int>, fallback: int} $result from ProjectTargets::write() */
    private function reportFallback(array $result): void
    {
        if ($result['fallback'] > 0) {
            $this->line("{$result['fallback']} session(s) had no project folder on this machine and went under "
                . getcwd() . '. Use --project, or --from-path/--to-path to point them at the right folder.');
        }
    }
}
