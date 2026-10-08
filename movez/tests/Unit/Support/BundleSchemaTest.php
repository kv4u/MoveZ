<?php
declare(strict_types=1);

use App\Support\BundleSchema;

it('validates a correct bundle array', function (): void {
    $valid = [
        'version'     => '1.0',
        'sessions'    => [],
        'source_tool' => 'cursor',
        'exported_at' => '2026-01-15T10:00:00+00:00',
    ];

    expect(BundleSchema::validate($valid))->toBeTrue();
});

it('throws InvalidArgumentException when version is missing', function (): void {
    expect(fn() => BundleSchema::validate([
        'sessions'    => [],
        'source_tool' => 'cursor',
        'exported_at' => '2026-01-15T10:00:00+00:00',
    ]))->toThrow(InvalidArgumentException::class, 'version');
});

it('throws InvalidArgumentException when sessions is missing', function (): void {
    expect(fn() => BundleSchema::validate([
        'version'     => '1.0',
        'source_tool' => 'cursor',
        'exported_at' => '2026-01-15T10:00:00+00:00',
    ]))->toThrow(InvalidArgumentException::class, 'sessions');
});

it('throws InvalidArgumentException when source_tool is missing', function (): void {
    expect(fn() => BundleSchema::validate([
        'version'     => '1.0',
        'sessions'    => [],
        'exported_at' => '2026-01-15T10:00:00+00:00',
    ]))->toThrow(InvalidArgumentException::class, 'source_tool');
});

it('throws InvalidArgumentException when exported_at is missing', function (): void {
    expect(fn() => BundleSchema::validate([
        'version'     => '1.0',
        'sessions'    => [],
        'source_tool' => 'cursor',
    ]))->toThrow(InvalidArgumentException::class, 'exported_at');
});

it('makeManifest includes the spec fields', function (): void {
    $manifest = BundleSchema::makeManifest('claude-code', 'deadbeef01234567', 3);

    expect($manifest)->toHaveKeys(['version', 'source_tool', 'machine_sha', 'exported_at', 'session_count', 'encrypted'])
        ->and($manifest['source_tool'])->toBe('claude-code')
        ->and($manifest['machine_sha'])->toBe('deadbeef01234567')
        ->and($manifest['session_count'])->toBe(3)
        ->and($manifest['encrypted'])->toBeFalse();
});
