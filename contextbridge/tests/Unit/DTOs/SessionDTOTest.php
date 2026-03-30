<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use Carbon\Carbon;

function sampleSessionArray(): array
{
    return [
        'id'                => 'abc123',
        'title'             => 'My Session',
        'source_tool'       => 'cursor',
        'source_machine_id' => 'sha16hex1234abcd',
        'created_at'        => '2026-01-15T08:00:00+00:00',
        'last_active_at'    => '2026-01-15T09:00:00+00:00',
        'turns'             => [
            ['role' => 'user',      'content' => 'Hi',    'timestamp' => '2026-01-15T08:01:00+00:00'],
            ['role' => 'assistant', 'content' => 'Hello', 'timestamp' => '2026-01-15T08:02:00+00:00'],
        ],
    ];
}

it('round-trips through fromArray and toArray', function (): void {
    $dto  = SessionDTO::fromArray(sampleSessionArray());
    $arr  = $dto->toArray();

    expect($arr['id'])->toBe('abc123')
        ->and($arr['title'])->toBe('My Session')
        ->and($arr['source_tool'])->toBe('cursor')
        ->and($arr['source_machine_id'])->toBe('sha16hex1234abcd')
        ->and($arr['turns'])->toHaveCount(2);
});

it('defaults title to Untitled when missing', function (): void {
    $data          = sampleSessionArray();
    $data['title'] = null;
    $dto           = SessionDTO::fromArray($data);

    expect($dto->title)->toBe('Untitled');
});

it('turns collection contains TurnDTO instances', function (): void {
    $dto = SessionDTO::fromArray(sampleSessionArray());

    expect($dto->turns)->toHaveCount(2)
        ->and($dto->turns->first())->toBeInstanceOf(TurnDTO::class);
});

it('createdAt and lastActiveAt are Carbon instances', function (): void {
    $dto = SessionDTO::fromArray(sampleSessionArray());

    expect($dto->createdAt)->toBeInstanceOf(Carbon::class)
        ->and($dto->lastActiveAt)->toBeInstanceOf(Carbon::class);
});

it('is a readonly class', function (): void {
    $rc = new ReflectionClass(SessionDTO::class);
    expect($rc->isReadOnly())->toBeTrue();
});
