<?php

namespace App\Entity;

use App\Repository\HeizWasserkostenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeizWasserkostenRepository::class)]
class HeizWasserkosten
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Weg::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Weg $weg = null;

    #[ORM\ManyToOne(targetEntity: WegEinheit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?WegEinheit $wegEinheit = null;

    #[ORM\Column(type: 'integer')]
    private ?int $jahr = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isWegGesamt = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $heizkosten = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $wasserKosten = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $sonstigeKosten = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeg(): ?Weg
    {
        return $this->weg;
    }

    public function setWeg(?Weg $weg): self
    {
        $this->weg = $weg;

        return $this;
    }

    public function getWegEinheit(): ?WegEinheit
    {
        return $this->wegEinheit;
    }

    public function setWegEinheit(?WegEinheit $wegEinheit): self
    {
        $this->wegEinheit = $wegEinheit;

        return $this;
    }

    public function getIsWegGesamt(): bool
    {
        return $this->isWegGesamt;
    }

    public function setIsWegGesamt(bool $isWegGesamt): self
    {
        $this->isWegGesamt = $isWegGesamt;

        return $this;
    }

    public function getJahr(): ?int
    {
        return $this->jahr;
    }

    public function setJahr(int $jahr): self
    {
        $this->jahr = $jahr;

        return $this;
    }

    public function getHeizkosten(): ?string
    {
        return $this->heizkosten;
    }

    public function setHeizkosten(string $heizkosten): self
    {
        $this->heizkosten = $heizkosten;

        return $this;
    }

    public function getWasserKosten(): ?string
    {
        return $this->wasserKosten;
    }

    public function setWasserKosten(string $wasserKosten): self
    {
        $this->wasserKosten = $wasserKosten;

        return $this;
    }

    public function getSonstigeKosten(): ?string
    {
        return $this->sonstigeKosten;
    }

    public function setSonstigeKosten(?string $sonstigeKosten): self
    {
        $this->sonstigeKosten = $sonstigeKosten;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
