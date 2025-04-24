<?php

namespace App\Entity;

use AllowDynamicProperties;
use App\Repository\ManifestationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[AllowDynamicProperties] #[ORM\Entity(repositoryClass: ManifestationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Manifestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $title_transcription = null;

    #[ORM\Column(length: 255)]
    private ?string $identifier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_identifier3 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyer_identifier = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $purchase_date = null;

    #[ORM\Column(length: 255)]
    private ?string $type1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type4 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location2 = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

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

    public function getTitleTranscription(): ?string
    {
        return $this->title_transcription;
    }

    public function setTitleTranscription(?string $title_transcription): static
    {
        $this->title_transcription = $title_transcription;

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


    public function getBuyer(): ?string
    {
        return $this->buyer;
    }

    public function setBuyer(?string $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function getBuyerIdentifier(): ?string
    {
        return $this->buyer_identifier;
    }

    public function setBuyerIdentifier(?string $buyer_identifier): static
    {
        $this->buyer_identifier = $buyer_identifier;

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

    public function getType1(): ?string
    {
        return $this->type1;
    }

    public function setType1(?string $type1): static
    {
        $this->type1 = $type1;

        return $this;
    }

    public function getType2(): ?string
    {
        return $this->type2;
    }

    public function setType2(?string $type2): static
    {
        $this->type2 = $type2;

        return $this;
    }

    public function getType3(): ?string
    {
        return $this->type3;
    }

    public function setType3(?string $type3): static
    {
        $this->type3 = $type3;

        return $this;
    }

    public function getType4(): ?string
    {
        return $this->type4;
    }

    public function setType4(?string $type4): static
    {
        $this->type4 = $type4;

        return $this;
    }

    public function getLocation1(): ?string
    {
        return $this->location1;
    }

    public function setLocation1(?string $location1): static
    {
        $this->location1 = $location1;

        return $this;
    }

    public function getLocation2(): ?string
    {
        return $this->location2;
    }

    public function setLocation2(?string $location2): static
    {
        $this->location2 = $location2;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updated_at = new \DateTime();
    }

}
