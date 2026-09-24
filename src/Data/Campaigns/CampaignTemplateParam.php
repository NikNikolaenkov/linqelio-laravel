<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Enums\CampaignParamSource;
use Linqelio\Laravel\Data\Read;

/**
 * The value of one template placeholder, worked out per recipient.
 *
 * A recipient whose name or field is empty gets `default`; with no default
 * either, that recipient is skipped with `template.params_invalid` — the dry run
 * counts them before anyone is skipped for real.
 */
final readonly class CampaignTemplateParam
{
    public function __construct(
        public CampaignParamSource $source,
        public ?string $value = null,
        public ?string $field = null,
        public ?string $default = null,
    ) {}

    /** The same text for everyone. */
    public static function literal(string $value): self
    {
        return new self(CampaignParamSource::Literal, value: $value);
    }

    /** The recipient's display name. */
    public static function contactName(?string $default = null): self
    {
        return new self(CampaignParamSource::ContactName, default: $default);
    }

    /** One of the recipient's typed contact fields. */
    public static function contactField(string $field, ?string $default = null): self
    {
        return new self(CampaignParamSource::ContactField, field: $field, default: $default);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: CampaignParamSource::tryFrom(Read::string($data, 'source')) ?? CampaignParamSource::Literal,
            value: Read::stringOrNull($data, 'value'),
            field: Read::stringOrNull($data, 'field'),
            default: Read::stringOrNull($data, 'default'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $out = ['source' => $this->source->value];

        foreach (['value' => $this->value, 'field' => $this->field, 'default' => $this->default] as $key => $value) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
