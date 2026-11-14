<?php

declare(strict_types=1);

/**
 * Regenerate tests/Fixtures/PersonNameDisplayFormats/display-format-options.json from
 * UAMSWeb\Aether\People\Support\PersonNameDisplayFormats::buildOptions() over corpus.json (UAMS-Web/uams-statamic-archive#3053).
 *
 * Run after changing the corpus or the PHP option builder, and commit the result; the
 * Pest test fails until the committed file matches, and the Vitest spec then checks the
 * JavaScript against it.
 *
 *     php scripts/generate-person-name-display-format-fixtures.php
 */

require __DIR__.'/../vendor/autoload.php';

use UAMSWeb\Aether\People\Tests\Support\PersonNameDisplayFormatFixtures;

$path = PersonNameDisplayFormatFixtures::fixturePath();

if (file_put_contents($path, PersonNameDisplayFormatFixtures::encode()) === false) {
    fwrite(STDERR, 'Cannot write '.$path.PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Wrote '.$path.PHP_EOL);
