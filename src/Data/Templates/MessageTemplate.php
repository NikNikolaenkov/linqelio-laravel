<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Templates;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Policy\SendTemplate;
use Linqelio\Laravel\Data\Read;

/**
 * A WhatsApp Business message template, as of the channel's last sync with Meta.
 *
 * Meta-owned values — status, category, formats — stay strings on purpose: Meta
 * adds values, and an unknown one has to be shown, not fail to parse.
 */
final readonly class MessageTemplate
{
    /**
     * @param  bool  $sendable  approved AND of a shape the platform can send
     * @param  string|null  $unsupportedReason  why an approved template is still not sendable
     * @param  array<int, array{type: string, text: string}>  $buttons  for display
     * @param  array<int, TemplateParameter>  $parameters  the placeholders a send fills, in order
     */
    public function __construct(
        public string $id,
        public string $channelId,
        public string $name,
        public string $language,
        public string $category = '',
        public string $status = '',
        public string $parameterFormat = '',
        public bool $sendable = false,
        public ?string $unsupportedReason = null,
        public ?string $headerFormat = null,
        public ?string $header = null,
        public string $body = '',
        public ?string $footer = null,
        public array $buttons = [],
        public array $parameters = [],
        public ?DateTimeImmutable $syncedAt = null,
        public ?DateTimeImmutable $changedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $buttons = [];
        foreach (Read::objects($data, 'buttons') as $button) {
            $buttons[] = ['type' => Read::string($button, 'type'), 'text' => Read::string($button, 'text')];
        }

        return new self(
            id: Read::string($data, 'id'),
            channelId: Read::string($data, 'channelId'),
            name: Read::string($data, 'name'),
            language: Read::string($data, 'language'),
            category: Read::string($data, 'category'),
            status: Read::string($data, 'status'),
            parameterFormat: Read::string($data, 'parameterFormat'),
            sendable: Read::bool($data, 'sendable'),
            unsupportedReason: Read::stringOrNull($data, 'unsupportedReason'),
            headerFormat: Read::stringOrNull($data, 'headerFormat'),
            header: Read::stringOrNull($data, 'header'),
            body: Read::string($data, 'body'),
            footer: Read::stringOrNull($data, 'footer'),
            buttons: $buttons,
            parameters: array_map(TemplateParameter::fromArray(...), Read::objects($data, 'parameters')),
            syncedAt: Read::date($data, 'syncedAt'),
            changedAt: Read::date($data, 'changedAt'),
        );
    }

    /**
     * The template of a send, filled with one value per placeholder.
     *
     * @param  array<int, string>  $params  in the order of {@see self::$parameters}
     */
    public function toSend(array $params = []): SendTemplate
    {
        return new SendTemplate($this->name, $this->language, array_values($params));
    }
}
