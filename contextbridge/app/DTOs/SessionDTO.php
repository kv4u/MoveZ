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
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:               $data['id'],
            title:            $data['title'] ?? 'Untitled',
            sourceTool:       $data['source_tool'],
            sourceMachineSha: $data['source_machine_id'],
            createdAt:        Carbon::parse($data['created_at']),
            lastActiveAt:     Carbon::parse($data['last_active_at']),
            turns:            collect($data['turns'])->map(fn($t) => TurnDTO::fromArray($t)),
            project:          $data['project'] ?? '',
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
            'turn_count'        => $this->turns->count(),
            'turns'             => $this->turns->map(fn($t) => $t->toArray())->all(),
        ];
    }
}
