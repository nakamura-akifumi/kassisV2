<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    public const STATUS_WAITING = '待機中';
    public const STATUS_AVAILABLE = '引換可能';
    public const STATUS_CANCELLED = 'キャンセル済';
    public const STATUS_COMPLETED = '完了';

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

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $reserved_at = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $expiry_date = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_WAITING;

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

    public function getReservedAt(): ?int
    {
        return $this->reserved_at;
    }

    public function setReservedAt(int $reserved_at): static
    {
        $this->reserved_at = $reserved_at;
        return $this;
    }

    public function getExpiryDate(): ?int
    {
        return $this->expiry_date;
    }

    public function setExpiryDate(?int $expiry_date): static
    {
        $this->expiry_date = $expiry_date;
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
}
