<?php
declare(strict_types=1);

namespace App\Writers;

class WindsurfWriter extends CursorWriter
{
    public function toolName(): string
    {
        return 'windsurf';
    }
}
