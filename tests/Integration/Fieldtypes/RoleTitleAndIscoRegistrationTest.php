<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Statamic\Fields\FieldtypeRepository;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;
use UAMSWeb\Aether\People\Fieldtypes\Isco_08;
use UAMSWeb\Aether\People\Fieldtypes\RoleTitle;
use UAMSWeb\Aether\People\Rules\OnetSocOccupationCode;
use UAMSWeb\Aether\People\Support\RoleTitleScopeUnit;

it('resolves the role_title and isco_08 fieldtypes from the add-on', function (): void {
    expect(app(FieldtypeRepository::class)->find('role_title'))->toBeInstanceOf(RoleTitle::class)
        ->and(app(FieldtypeRepository::class)->find('isco_08'))->toBeInstanceOf(Isco_08::class);
});

it('registers the Control Panel components under the fieldtype handles', function (): void {
    $js = file_get_contents(__DIR__.'/../../../resources/js/cp.js');

    expect($js)->toContain("register('role_title-fieldtype'")
        ->and($js)->toContain("register('isco_08-fieldtype'");
});

it('merges the role_title config and offers it for publishing', function (): void {
    expect(config('role_title'))->toBeArray()->not->toBeEmpty();

    $paths = ServiceProvider::pathsToPublish(UAMSWeb\Aether\People\ServiceProvider::class, 'aether-people-role-title-config');

    expect(array_keys($paths))->toHaveCount(1)
        ->and(basename((string) array_key_first($paths)))->toBe('role_title.php');
});

it('reads the ISCO-08 codes from aether-enums', function (): void {
    expect(enum_exists(Isco08::class))->toBeTrue()
        ->and(Isco08::cases())->not->toBeEmpty();
});

it('depends on no host code in the moved classes', function (): void {
    $offenders = [];

    foreach (['Rules', 'Support', 'Fieldtypes', 'Contracts'] as $dir) {
        foreach (glob(__DIR__."/../../../src/{$dir}/*.php") ?: [] as $file) {
            if (preg_match('/^\s*use App\\\\|\\bApp\\\\[A-Z]/m', (string) file_get_contents($file)) === 1) {
                $offenders[] = basename($file);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('uses the host-bound display name only when a unit has no formal name', function (): void {
    expect(RoleTitleScopeUnit::displayName(null))->toBeNull();
    expect(app()->bound(OrganizationDisplayName::class))->toBeFalse();
});

it('validates O*Net-SOC codes by format, skipping empty values and refusing arrays', function (): void {
    $rule = new OnetSocOccupationCode;
    $check = static function (mixed $value) use ($rule): bool {
        $failed = false;
        $rule->validate('code', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    };

    expect($check('11-1011.00'))->toBeTrue()
        ->and($check('13-2011.01'))->toBeTrue()
        ->and($check('11-1011'))->toBeFalse()
        ->and($check("11-1011.00\n"))->toBeFalse()
        ->and($check(''))->toBeTrue()
        ->and($check(null))->toBeTrue()
        ->and($check(['11-1011.00']))->toBeFalse()
        ->and($rule->isValid('11-1011.00'))->toBeTrue()
        ->and($rule->isValid('bad'))->toBeFalse();
});
