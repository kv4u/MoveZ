<?php
declare(strict_types=1);

namespace App\Parsers;

class WindsurfParser extends CursorParser
{
    public function toolName(): string
    {
        return 'windsurf';
    }
}
