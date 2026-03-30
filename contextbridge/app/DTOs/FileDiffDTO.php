<?php
declare(strict_types=1);

namespace App\DTOs;

final readonly class FileDiffDTO
{
    public function __construct(
        public string $file,
        public string $diff,   // unified diff format
    ) {}
}
