<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['identifier'], message: 'この識別子は既に使用されています')]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(length: 255)]
    private ?string $full_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $full_name_yomi = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $group1 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $group2 = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $communication_address1 = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $communication_address2 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiry_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    public function setFullName(string $full_name): static
    {
        $this->full_name = $full_name;
        return $this;
    }

    public function getFullNameYomi(): ?string
    {
        return $this->full_name_yomi;
    }

    public function setFullNameYomi(?string $full_name_yomi): static
    {
        $this->full_name_yomi = $full_name_yomi;
        return $this;
    }

    public function getGroup1(): ?string
    {
        return $this->group1;
    }

    public function setGroup1(?string $group1): static
    {
        $this->group1 = $group1;
        return $this;
    }

    public function getGroup2(): ?string
    {
        return $this->group2;
    }

    public function setGroup2(?string $group2): static
    {
        $this->group2 = $group2;
        return $this;
    }

    public function getCommunicationAddress1(): ?string
    {
        return $this->communication_address1;
    }

    public function setCommunicationAddress1(?string $communication_address1): static
    {
        $this->communication_address1 = $communication_address1;
        return $this;
    }

    public function getCommunicationAddress2(): ?string
    {
        return $this->communication_address2;
    }

    public function setCommunicationAddress2(?string $communication_address2): static
    {
        $this->communication_address2 = $communication_address2;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiry_date;
    }

    public function setExpiryDate(?\DateTimeInterface $expiry_date): static
    {
        $this->expiry_date = $expiry_date;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }
}
