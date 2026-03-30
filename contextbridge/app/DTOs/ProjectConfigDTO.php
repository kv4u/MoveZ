<?php
declare(strict_types=1);

namespace App\DTOs;

final readonly class ProjectConfigDTO
{
    public function __construct(
        public ?string $cursorRules = null,
        public ?string $claudeMd    = null,
        public ?string $mcpJson     = null,
        public ?string $agentsMd    = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cursorRules: $data['cursor_rules'] ?? null,
            claudeMd:    $data['claude_md'] ?? null,
            mcpJson:     $data['mcp_json'] ?? null,
            agentsMd:    $data['agents_md'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'cursor_rules' => $this->cursorRules,
            'claude_md'    => $this->claudeMd,
            'mcp_json'     => $this->mcpJson,
            'agents_md'    => $this->agentsMd,
        ];
    }
}
