<?php

namespace App\Entity;

use App\Repository\ManifestationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManifestationRepository::class)]
class Manifestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $identifier = null;

    #[ORM\Column(length: 255)]
    private ?string $external_identifier1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier3 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $purchase_date = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getExternalIdentifier1(): ?string
    {
        return $this->external_identifier1;
    }

    public function setExternalIdentifier1(string $external_identifier1): static
    {
        $this->external_identifier1 = $external_identifier1;

        return $this;
    }

    public function getExternalIdentifier2(): ?string
    {
        return $this->external_identifier2;
    }

    public function setExternalIdentifier2(?string $external_identifier2): static
    {
        $this->external_identifier2 = $external_identifier2;

        return $this;
    }

    public function getExternalIdentifier3(): ?string
    {
        return $this->external_identifier3;
    }

    public function setExternalIdentifier3(?string $external_identifier3): static
    {
        $this->external_identifier3 = $external_identifier3;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchase_date;
    }

    public function setPurchaseDate(?\DateTimeInterface $purchase_date): static
    {
        $this->purchase_date = $purchase_date;

        return $this;
    }
}
