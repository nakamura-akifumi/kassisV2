<?php

namespace App\Service;

class ManifestationSearchQuery
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $title = null,
        public readonly ?string $identifier = null,
        public readonly ?string $externalId1 = null,
        public readonly ?string $externalId2 = null,
        public readonly ?string $externalId3 = null,
        public readonly ?string $description = null,
        public readonly ?string $purchaseDateFrom = null,
        public readonly ?string $purchaseDateTo = null,
    ) {
    }

    public function hasSearchCriteria(): bool
    {
        return $this->q || $this->title || $this->identifier || $this->externalId1 
            || $this->externalId2 || $this->externalId3 || $this->description 
            || $this->purchaseDateFrom || $this->purchaseDateTo;
    }

    public static function fromRequest(array $query): self
    {
        return new self(
            q: $query['q'] ?? null,
            title: $query['title'] ?? null,
            identifier: $query['identifier'] ?? null,
            externalId1: $query['external_id1'] ?? null,
            externalId2: $query['external_id2'] ?? null,
            externalId3: $query['external_id3'] ?? null,
            description: $query['description'] ?? null,
            purchaseDateFrom: $query['purchase_date_from'] ?? null,
            purchaseDateTo: $query['purchase_date_to'] ?? null,
        );
    }
}
