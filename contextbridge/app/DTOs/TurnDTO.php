<?php
declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Collection;
use Carbon\Carbon;

final readonly class TurnDTO
{
    public function __construct(
        public string     $role,              // "user" | "assistant"
        public string     $content,
        public Carbon     $timestamp,
        public array      $filesReferenced = [],
        /** @var Collection<int, FileDiffDTO> */
        public Collection $fileDiffs        = new Collection(),
        public ?string    $reasoningTrace   = null,
        public array      $toolCalls        = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role:            $data['role'],
            content:         $data['content'],
            timestamp:       Carbon::parse($data['timestamp']),
            filesReferenced: $data['files_referenced'] ?? [],
            fileDiffs:       collect($data['file_diffs'] ?? [])
                                ->map(fn($d) => new FileDiffDTO($d['file'], $d['diff'])),
            reasoningTrace:  $data['reasoning_trace'] ?? null,
            toolCalls:       $data['tool_calls'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'role'             => $this->role,
            'content'          => $this->content,
            'timestamp'        => $this->timestamp->toIso8601String(),
            'files_referenced' => $this->filesReferenced,
            'file_diffs'       => $this->fileDiffs
                                       ->map(fn($d) => ['file' => $d->file, 'diff' => $d->diff])
                                       ->all(),
            'reasoning_trace'  => $this->reasoningTrace,
            'tool_calls'       => $this->toolCalls,
        ];
    }
}
