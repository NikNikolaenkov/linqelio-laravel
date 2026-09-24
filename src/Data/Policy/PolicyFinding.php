<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Policy;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\PolicyVerdict;
use Linqelio\Laravel\Data\Read;

/**
 * One send-policy checker's answer about a send.
 *
 * The set of checkers grows — rate, consent, frequency, duplicates, business
 * hours, and whatever comes next — so render a finding GENERICALLY: localise by
 * `code` with `params` interpolated, and fall back to `detail`, then to the raw
 * `code`, for one this package does not know. {@see self::message()} does the
 * fallback half.
 */
final readonly class PolicyFinding
{
    /**
     * @param  string  $key  what acknowledges this finding when it is a warning:
     *                       pass it in `acknowledgedWarnings` on the send
     * @param  string|null  $code  usually an ErrorCode value, but a newer checker
     *                             may send one this package has never heard of —
     *                             which is why it stays a string
     * @param  array<string, string>  $params  values to interpolate into the
     *                                         localised message
     * @param  DateTimeImmutable|null  $until  for `defer`: when the send may be retried
     */
    public function __construct(
        public string $check,
        public PolicyVerdict $verdict,
        public string $key,
        public ?string $code = null,
        public array $params = [],
        public ?DateTimeImmutable $until = null,
        public ?string $detail = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            check: Read::string($data, 'check'),
            verdict: PolicyVerdict::parse(Read::stringOrNull($data, 'verdict')),
            key: Read::string($data, 'key'),
            code: Read::stringOrNull($data, 'code'),
            params: Read::stringMap($data, 'params'),
            until: Read::date($data, 'until'),
            detail: Read::stringOrNull($data, 'detail'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, self>
     */
    public static function listFrom(array $data, string $key): array
    {
        return array_map(self::fromArray(...), Read::objects($data, $key));
    }

    public function isWarning(): bool
    {
        return $this->verdict === PolicyVerdict::Warn;
    }

    /**
     * Operator-facing text when there is no translation for `code`: the
     * platform's English `detail`, then the code itself, then the checker.
     */
    public function message(): string
    {
        return $this->detail ?? $this->code ?? $this->check;
    }
}
