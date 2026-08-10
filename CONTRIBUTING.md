# Contributing

Thanks for helping. This package is small on purpose — the shortest path to a
merged change is a focused one.

## Getting set up

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint
```

Tests need the `pdo_sqlite` and `sqlite3` extensions. On a PHP build where they
are compiled but not enabled, you can load them for a single run:

```bash
php -d extension=sqlite3 -d extension=pdo_sqlite vendor/bin/pest
```

## The one gate that will surprise you

`tests/Contract/ContractParityTest.php` reconciles this package against the
OpenAPI contract in `tests/Fixtures/openapi.yaml`. Every operation must be either
wrapped or listed in `Contract::EXCLUDED` **with a reason**, and every error code
in the contract must exist in `ErrorCode`.

It exists because a hand-written client drifts silently: the server grows an
operation, nobody notices, and the gap surfaces months later in somebody's
production. Failing loudly here is the point — if it fails, either implement the
operation or write down why you are not going to.

Refresh the fixture when the contract moves:

```bash
composer contract:sync
```

## Writing the code

- **Types over arrays.** A resource returns a DTO, not `array`. Callers should
  get autocompletion and PHPStan should catch a typo.
- **Say why, not what.** `// increment the counter` above `$i++` is noise. The
  comments worth writing are the ones explaining a decision that looks wrong
  until you know the reason — why the projection is keyed on message id, why the
  signature is checked over the raw body.
- **PHPStan level 8, no new baseline entries.** If the analyser is unhappy, it
  usually has a point.
- **Pint before pushing.** CI checks it.

## Tests

Pest, with Testbench for anything that needs the framework. `Http::fake()` covers
the client — no test should reach the network.

A bug fix should come with the test that fails without it. Two of the three bugs
found while writing this package were caught that way, and the third was a test
whose premise was wrong.

## Pull requests

One change per PR. Update the README if you changed behaviour somebody relies on,
and add a `CHANGELOG.md` entry under "Unreleased".

## Security

Do not open an issue for a vulnerability — see [SECURITY.md](SECURITY.md).
