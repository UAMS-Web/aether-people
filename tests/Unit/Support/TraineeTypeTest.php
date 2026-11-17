<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\TraineeType;

/**
 * The trainee-type field and the carry-over of the yes/no fields it replaced (UAMS-Web/aether-people#58).
 *
 * @command  vendor/bin/pest tests/Unit/Support/TraineeTypeTest.php
 */
it('names the field and its three values', function (): void {
    expect(TraineeType::FIELD)->toBe('trainee_type')
        ->and(TraineeType::NONE)->toBe('none')
        ->and(TraineeType::RESIDENT)->toBe('resident')
        ->and(TraineeType::FELLOW)->toBe('fellow');
});

it('reads only a resident as a resident', function (mixed $value, bool $expected): void {
    expect(TraineeType::isResident($value))->toBe($expected);
})->with([
    'a resident' => ['resident', true],
    'a fellow' => ['fellow', false],
    'not a trainee' => ['none', false],
    'no answer' => [null, false],
    'the old yes' => [true, false],
]);

it('carries the old answers over to one trainee type', function (array $before, array $after): void {
    expect(TraineeType::carryOver($before))->toBe($after);
})->with([
    'a resident' => [
        ['resident_query' => true, 'fellow_query' => false, 'bio' => 'x'],
        ['bio' => 'x', 'trainee_type' => 'resident'],
    ],
    'a fellow' => [
        ['resident_query' => false, 'fellow_query' => true],
        ['trainee_type' => 'fellow'],
    ],
    'a resident answered and the fellow question never shown' => [
        ['resident_query' => true],
        ['trainee_type' => 'resident'],
    ],
    'both answered yes reads as a resident, as the old template did' => [
        ['resident_query' => true, 'fellow_query' => true],
        ['trainee_type' => 'resident'],
    ],
    'both answered no' => [
        ['resident_query' => false, 'fellow_query' => false],
        ['trainee_type' => 'none'],
    ],
    'only the resident question answered no' => [
        ['resident_query' => false],
        ['trainee_type' => 'none'],
    ],
    'only the fellow question answered no' => [
        ['fellow_query' => false],
        ['trainee_type' => 'none'],
    ],
    'the string an import wrote for no' => [
        ['resident_query' => 'false', 'fellow_query' => 'false'],
        ['trainee_type' => 'none'],
    ],
    'the string an import wrote for yes' => [
        ['resident_query' => 'true', 'fellow_query' => 'false'],
        ['trainee_type' => 'resident'],
    ],
    'a one an import wrote for yes' => [
        ['resident_query' => 0, 'fellow_query' => 1],
        ['trainee_type' => 'fellow'],
    ],
    'both questions never answered' => [
        ['resident_query' => null, 'fellow_query' => null, 'bio' => 'x'],
        ['bio' => 'x'],
    ],
]);

it('leaves an entry with neither old field alone', function (array $data): void {
    expect(TraineeType::carryOver($data))->toBeNull();
})->with([
    'no trainee data at all' => [['bio' => 'x']],
    'a trainee type already stored' => [['trainee_type' => 'fellow']],
    'a provenance list that names no trainee field' => [['workday_supplied' => ['gender']]],
    'a provenance list that is not a list' => [['workday_supplied' => 'gender']],
    'a provenance list holding true, which a loose comparison would read as a handle' => [['workday_supplied' => [true]]],
]);

it('keeps a trainee type already stored over the old answers', function (): void {
    expect(TraineeType::carryOver(['resident_query' => true, 'trainee_type' => 'fellow']))->toBe(['trainee_type' => 'fellow']);
});

it('replaces a blank stored trainee type with the old answer', function (): void {
    expect(TraineeType::carryOver(['resident_query' => true, 'trainee_type' => null]))->toBe(['trainee_type' => 'resident']);
});

it('names the new field wherever the Workday provenance list named an old one', function (array $supplied, array $expected): void {
    expect(TraineeType::carryOver(['workday_supplied' => $supplied]))->toBe(['workday_supplied' => $expected]);
})->with([
    'the resident field' => [['resident_query'], ['trainee_type']],
    'the fellow field' => [['fellow_query'], ['trainee_type']],
    'both, once' => [['resident_query', 'fellow_query'], ['trainee_type']],
    'beside another handle, in order' => [['gender', 'fellow_query', 'job_title'], ['gender', 'trainee_type', 'job_title']],
    'the new field already named' => [['trainee_type', 'resident_query'], ['trainee_type']],
    'an entry that is not a handle, kept as it is' => [['resident_query', true], ['trainee_type', true]],
    'an entry that is not a handle, before the renamed one' => [[true, 'fellow_query'], [true, 'trainee_type']],
]);

it('carries the answers and the provenance list together', function (): void {
    expect(TraineeType::carryOver(['resident_query' => true, 'fellow_query' => false, 'workday_supplied' => ['resident_query']]))
        ->toBe(['workday_supplied' => ['trainee_type'], 'trainee_type' => 'resident']);
});

it('changes nothing on a second pass', function (): void {
    $once = TraineeType::carryOver(['resident_query' => false, 'fellow_query' => true, 'workday_supplied' => ['fellow_query']]);

    expect($once)->toBe(['workday_supplied' => ['trainee_type'], 'trainee_type' => 'fellow'])
        ->and(TraineeType::carryOver($once ?? []))->toBeNull();
});
