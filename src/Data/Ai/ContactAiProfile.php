<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Ai;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\AiRunStatus;
use Linqelio\Laravel\Data\Enums\FieldValueSource;
use Linqelio\Laravel\Data\Fields\FieldOrigin;
use Linqelio\Laravel\Data\Read;

/**
 * A contact's AI questionnaire: which typed fields the AI is asked to fill, which
 * are filled and by whom, the AI summary, and the last run.
 *
 * The AI never overwrites what a person, your system or the platform wrote — a
 * field filled by anyone else stays theirs (ADR-0061).
 *
 * @phpstan-type Run array{id: string, status: ?AiRunStatus, errorCode: ?string, createdAt: ?DateTimeImmutable, finishedAt: ?DateTimeImmutable, applied: array<int, string>, kept: array<int, string>}
 */
final readonly class ContactAiProfile
{
    /**
     * @param  bool  $enabled  the cabinet has consented to AI processing
     * @param  array<int, array{key: string, filled: bool, source: ?FieldValueSource}>  $fields
     * @param  Run|null  $lastRun
     */
    public function __construct(
        public bool $enabled,
        public array $fields = [],
        public ?string $summaryField = null,
        public ?string $summary = null,
        public ?FieldOrigin $summaryOrigin = null,
        public ?array $lastRun = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $fields = [];
        foreach (Read::objects($data, 'fields') as $field) {
            $fields[] = [
                'key' => Read::string($field, 'key'),
                'filled' => Read::bool($field, 'filled'),
                'source' => FieldValueSource::tryFrom(Read::string($field, 'source')),
            ];
        }

        $origin = Read::object($data, 'summaryOrigin');
        $run = Read::object($data, 'lastRun');

        return new self(
            enabled: Read::bool($data, 'enabled'),
            fields: $fields,
            summaryField: Read::stringOrNull($data, 'summaryField'),
            summary: Read::stringOrNull($data, 'summary'),
            summaryOrigin: $origin === null ? null : FieldOrigin::fromArray($origin),
            lastRun: $run === null ? null : [
                'id' => Read::string($run, 'id'),
                'status' => AiRunStatus::tryFrom(Read::string($run, 'status')),
                'errorCode' => Read::stringOrNull($run, 'errorCode'),
                'createdAt' => Read::date($run, 'createdAt'),
                'finishedAt' => Read::date($run, 'finishedAt'),
                'applied' => Read::strings($run, 'applied'),
                'kept' => Read::strings($run, 'kept'),
            ],
        );
    }

    /**
     * The questionnaire fields nobody has filled yet.
     *
     * @return array<int, string>
     */
    public function unfilled(): array
    {
        $out = [];
        foreach ($this->fields as $field) {
            if (! $field['filled']) {
                $out[] = $field['key'];
            }
        }

        return $out;
    }
}
