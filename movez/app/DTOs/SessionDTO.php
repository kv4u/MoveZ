<?php
declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Collection;
use Carbon\Carbon;

final readonly class SessionDTO
{
    public function __construct(
        public string     $id,
        public string     $title,
        public string     $sourceTool,
        public string     $sourceMachineSha,
        public Carbon     $createdAt,
        public Carbon     $lastActiveAt,
        /** @var Collection<int, TurnDTO> */
        public Collection $turns,
        public string     $project = '',
        /** Known turn count when turns are not loaded (metadata-only listings) */
        public ?int       $turnCount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:               (string) $data['id'],
            title:            (string) ($data['title'] ?? 'Untitled'),
            sourceTool:       (string) ($data['source_tool'] ?? 'unknown'),
            sourceMachineSha: (string) ($data['source_machine_id'] ?? $data['machine_sha'] ?? ''),
            createdAt:        Carbon::parse($data['created_at'] ?? 'now'),
            lastActiveAt:     Carbon::parse($data['last_active_at'] ?? $data['created_at'] ?? 'now'),
            turns:            collect($data['turns'] ?? [])->map(fn($t) => TurnDTO::fromArray($t)),
            project:          (string) ($data['project'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'project'           => $this->project,
            'source_tool'       => $this->sourceTool,
            'source_machine_id' => $this->sourceMachineSha,
            'created_at'        => $this->createdAt->toIso8601String(),
            'last_active_at'    => $this->lastActiveAt->toIso8601String(),
            'turn_count'        => $this->turnCount(),
            'turns'             => $this->turns->map(fn($t) => $t->toArray())->all(),
        ];
    }

    public function turnCount(): int
    {
        return $this->turnCount ?? $this->turns->count();
    }
}
