<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Policy;

/**
 * The template of a `type: template` send — WhatsApp Business (wa_cloud) only.
 *
 * `name` and `language` exactly as Meta names them (see
 * `Linqelio::channels()->templates()`), and one value per placeholder in
 * `params`, in the order of the template's parameters: header first, then body.
 * The platform checks it before Meta sees it — approved, sendable, every
 * placeholder filled, no line breaks or tabs in a value.
 */
final readonly class SendTemplate
{
    /**
     * @param  array<int, string>  $params
     */
    public function __construct(
        public string $name,
        public string $language,
        public array $params = [],
    ) {}

    /**
     * @return array{name: string, language: string, params?: array<int, string>}
     */
    public function toArray(): array
    {
        $out = ['name' => $this->name, 'language' => $this->language];

        if ($this->params !== []) {
            $out['params'] = array_values($this->params);
        }

        return $out;
    }
}
