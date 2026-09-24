<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Templates;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * A channel's templates and the state of its last sync with Meta.
 *
 * The list is only as fresh as `lastSyncedAt`: a template approved since then is
 * not in it, and a send naming it fails with `template.not_found` until the
 * channel is synced again.
 */
final readonly class ChannelTemplates
{
    /**
     * @param  array<int, MessageTemplate>  $templates
     * @param  string|null  $lastErrorCode  the ErrorCode value of the last failed sync, if it failed
     */
    public function __construct(
        public array $templates,
        public int $templateCount = 0,
        public ?DateTimeImmutable $lastAttemptAt = null,
        public ?DateTimeImmutable $lastSyncedAt = null,
        public ?string $lastErrorCode = null,
        public ?string $lastError = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $sync = Read::map($data, 'sync');

        return new self(
            templates: array_map(MessageTemplate::fromArray(...), Read::objects($data, 'items')),
            templateCount: Read::int($sync, 'templateCount'),
            lastAttemptAt: Read::date($sync, 'lastAttemptAt'),
            lastSyncedAt: Read::date($sync, 'lastSyncedAt'),
            lastErrorCode: Read::stringOrNull($sync, 'lastErrorCode'),
            lastError: Read::stringOrNull($sync, 'lastError'),
        );
    }

    /** The template of that name and language, if the last sync saw it. */
    public function find(string $name, string $language): ?MessageTemplate
    {
        foreach ($this->templates as $template) {
            if ($template->name === $name && $template->language === $language) {
                return $template;
            }
        }

        return null;
    }
}
