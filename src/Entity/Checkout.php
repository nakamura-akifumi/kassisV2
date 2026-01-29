<?php

namespace App\Entity;

use App\Repository\CheckoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CheckoutRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Checkout
{
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_RETURNED = 'returned';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Manifestation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Manifestation $manifestation = null;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Member $member = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $checked_out_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $due_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checked_in_at = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_CHECKED_OUT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManifestation(): ?Manifestation
    {
        return $this->manifestation;
    }

    public function setManifestation(Manifestation $manifestation): static
    {
        $this->manifestation = $manifestation;
        return $this;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(Member $member): static
    {
        $this->member = $member;
        return $this;
    }

    public function getCheckedOutAt(): ?\DateTimeInterface
    {
        return $this->checked_out_at;
    }

    public function setCheckedOutAt(\DateTimeInterface $checked_out_at): static
    {
        $this->checked_out_at = $checked_out_at;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->due_date;
    }

    public function setDueDate(?\DateTimeInterface $due_date): static
    {
        $this->due_date = $due_date;
        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeInterface
    {
        return $this->checked_in_at;
    }

    public function setCheckedInAt(?\DateTimeInterface $checked_in_at): static
    {
        $this->checked_in_at = $checked_in_at;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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
