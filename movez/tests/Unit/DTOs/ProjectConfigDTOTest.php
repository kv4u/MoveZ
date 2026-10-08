<?php
declare(strict_types=1);

use App\DTOs\ProjectConfigDTO;

it('all fields default to null', function (): void {
    $dto = new ProjectConfigDTO();

    expect($dto->cursorRules)->toBeNull()
        ->and($dto->claudeMd)->toBeNull()
        ->and($dto->mcpJson)->toBeNull()
        ->and($dto->agentsMd)->toBeNull();
});

it('round-trips through fromArray and toArray', function (): void {
    $dto = ProjectConfigDTO::fromArray([
        'cursor_rules' => '.cursorrules content',
        'claude_md'    => '# Claude config',
        'mcp_json'     => '{}',
        'agents_md'    => '# Agents',
    ]);

    $arr = $dto->toArray();

    expect($arr['cursor_rules'])->toBe('.cursorrules content')
        ->and($arr['claude_md'])->toBe('# Claude config')
        ->and($arr['mcp_json'])->toBe('{}')
        ->and($arr['agents_md'])->toBe('# Agents');
});

it('is a readonly class', function (): void {
    $rc = new ReflectionClass(ProjectConfigDTO::class);
    expect($rc->isReadOnly())->toBeTrue();
});
