<?php

namespace App\Service;

class ManifestationSearchQuery
{
    public ?string $q = null;
    public ?string $identifier = null;
    public ?string $externalId1 = null;
    public ?string $type1 = null;
    public ?string $type2 = null;
    public string $mode = 'simple'; // 'simple', 'multi', 'advanced'

    public function hasSearchCriteria(): bool
    {
        return $this->q || $this->identifier || $this->externalId1
            || $this->type1 || $this->type2;
    }

    public function isMultiLine(): bool
    {
        return $this->q !== null && str_contains($this->q, "\n");
    }

    public static function fromRequest(array $params): self
    {
        $query = new self();
        $query->q = $params['q'] ?? null;
        $query->identifier = $params['identifier'] ?? null;
        $query->externalId1 = $params['external_identifier1'] ?? null;
        $query->type1 = $params['type1'] ?? null;
        $query->type2 = $params['type2'] ?? null;
        $query->mode = $params['mode'] ?? 'simple';

        return $query;
    }
}
