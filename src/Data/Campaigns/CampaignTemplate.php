<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * An approved WhatsApp Business template a campaign sends (wa_cloud channels
 * only), with its placeholders filled per recipient.
 *
 * Launch checks it against every channel's last template sync — approved,
 * fillable, as many params as placeholders — so sync the channels first
 * (`Linqelio::channels()->syncTemplates()`) if a template was just approved.
 */
final readonly class CampaignTemplate
{
    /**
     * @param  array<int, CampaignTemplateParam>  $params  in send order: header first, then body
     * @param  array<string, string>  $variables  DEPRECATED (issue #140) — kept by the
     *                                            platform for compatibility, never used
     *                                            for sending; read only. Use `$params`.
     */
    public function __construct(
        public string $name,
        public ?string $language = null,
        public array $params = [],
        /** @deprecated read only and never used for sending — fill placeholders with $params */
        public array $variables = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Read::string($data, 'name'),
            language: Read::stringOrNull($data, 'language'),
            params: array_map(CampaignTemplateParam::fromArray(...), Read::objects($data, 'params')),
            variables: Read::stringMap($data, 'variables'),
        );
    }

    /**
     * `variables` is never sent: the platform does not send with it, and a
     * caller setting it would be planning around something that does nothing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'name' => $this->name,
            'language' => $this->language,
            'params' => $this->params === []
                ? null
                : array_map(static fn (CampaignTemplateParam $p): array => $p->toArray(), array_values($this->params)),
        ]);
    }
}
