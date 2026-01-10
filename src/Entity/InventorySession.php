<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InventorySession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\Column(length: 255)]
    private ?string $identifier = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $scannedAt = null;

    public function __construct()
    {
        $this->scannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(string $location): self { $this->location = $location; return $this; }
    public function getIdentifier(): ?string { return $this->identifier; }
    public function setIdentifier(string $identifier): self { $this->identifier = $identifier; return $this; }
    public function getScannedAt(): ?\DateTimeImmutable { return $this->scannedAt; }
}
