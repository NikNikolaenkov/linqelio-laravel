<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\MediaContent;
use Linqelio\Laravel\Data\Read;

/**
 * The one message every recipient of a campaign gets: a send body as for a
 * single send — text, media, or a WhatsApp template.
 */
final readonly class CampaignContent
{
    /**
     * @param  array<string, mixed>  $content  keyed by type, as for a single send
     */
    public function __construct(
        public MessageType $type,
        public array $content = [],
        public ?CampaignTemplate $template = null,
    ) {}

    public static function text(string $text): self
    {
        return new self(MessageType::Text, ['text' => $text]);
    }

    /** An uploaded attachment; see `Linqelio::media()->upload()`. */
    public static function media(MessageType $type, MediaContent $media, ?string $caption = null): self
    {
        return new self($type, $media->toContent($caption));
    }

    public static function template(CampaignTemplate $template): self
    {
        return new self(MessageType::Template, template: $template);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $template = Read::object($data, 'template');

        return new self(
            type: MessageType::tryFrom(Read::string($data, 'type')) ?? MessageType::Text,
            content: Read::map($data, 'content'),
            template: $template === null ? null : CampaignTemplate::fromArray($template),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'type' => $this->type->value,
            'content' => $this->content === [] ? null : $this->content,
            'template' => $this->template?->toArray(),
        ]);
    }
}
