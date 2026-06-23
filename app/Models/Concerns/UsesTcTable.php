<?php

namespace App\Models\Concerns;

trait UsesTcTable
{
    public function getTraccarAttributesJson(): ?string
    {
        $raw = $this->getAttributes()['attributes'] ?? null;

        return is_string($raw) ? $raw : null;
    }

    public function setTraccarAttributesJson(?string $json): void
    {
        $this->attributes['attributes'] = $json;
    }

    public function traccarAppAttributes(): array
    {
        return \App\Support\Traccar\TraccarAttributes::decode($this->getTraccarAttributesJson());
    }

    public function patchTraccarAppAttributes(array $patch): void
    {
        $this->setTraccarAttributesJson(
            \App\Support\Traccar\TraccarAppFields::mergeInto($this->getTraccarAttributesJson(), $patch)
        );
    }
}
