<?php

declare(strict_types=1);

/**
 * Refreshes tests/Fixtures/openapi.yaml from the platform's contract.
 *
 * The fixture is what ContractParityTest reconciles against, so it has to be a
 * copy rather than a reference: this repository is public and standalone, and a
 * test that silently passes because it could not find the contract would be
 * worse than no test.
 *
 * Point LINQELIO_CONTRACT at the spec, or keep the platform repository beside
 * this one:
 *
 *   LINQELIO_CONTRACT=/path/to/openapi.yaml composer contract:sync
 */
$source = getenv('LINQELIO_CONTRACT') ?: __DIR__.'/../../Linqelio/api/openapi/openapi.yaml';
$target = __DIR__.'/../tests/Fixtures/openapi.yaml';

if (! is_file($source)) {
    fwrite(STDERR, sprintf(
        "Contract not found at %s\n".
        "Set LINQELIO_CONTRACT to the openapi.yaml you want to sync from.\n",
        $source,
    ));
    exit(1);
}

if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true)) {
    fwrite(STDERR, "Could not create tests/Fixtures\n");
    exit(1);
}

$before = is_file($target) ? (string) file_get_contents($target) : '';
$after = (string) file_get_contents($source);

if ($before === $after) {
    echo "Contract already up to date.\n";
    exit(0);
}

if (! copy($source, $target)) {
    fwrite(STDERR, "Copy failed.\n");
    exit(1);
}

printf(
    "Synced %s -> tests/Fixtures/openapi.yaml (%d bytes)\nRun the tests: the parity gate will say what changed.\n",
    $source,
    strlen($after),
);
