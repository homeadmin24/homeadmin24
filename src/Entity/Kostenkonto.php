<?php

namespace App\Entity;

use App\Repository\KostenkontoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KostenkontoRepository::class)]
class Kostenkonto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $bezeichnung = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nummer = null;

    #[ORM\Column(enumType: KategorisierungsTyp::class, nullable: false)]
    private KategorisierungsTyp $kategorisierungsTyp = KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Umlageschluessel::class, inversedBy: 'kostenkonten')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Umlageschluessel $umlageschluessel = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $taxDeductible = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBezeichnung(): ?string
    {
        return $this->bezeichnung;
    }

    public function setBezeichnung(string $bezeichnung): static
    {
        $this->bezeichnung = $bezeichnung;

        return $this;
    }

    public function getNummer(): ?string
    {
        return $this->nummer;
    }

    public function setNummer(?string $nummer): static
    {
        $this->nummer = $nummer;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getKategorisierungsTyp(): KategorisierungsTyp
    {
        return $this->kategorisierungsTyp;
    }

    public function setKategorisierungsTyp(KategorisierungsTyp $kategorisierungsTyp): static
    {
        $this->kategorisierungsTyp = $kategorisierungsTyp;

        return $this;
    }

    public function getUmlageschluessel(): ?Umlageschluessel
    {
        return $this->umlageschluessel;
    }

    public function setUmlageschluessel(?Umlageschluessel $umlageschluessel): static
    {
        $this->umlageschluessel = $umlageschluessel;

        return $this;
    }

    public function isTaxDeductible(): bool
    {
        return $this->taxDeductible;
    }

    public function setTaxDeductible(bool $taxDeductible): static
    {
        $this->taxDeductible = $taxDeductible;

        return $this;
    }

    /**
     * Helper method for backwards compatibility.
     */
    public function isIstUmlagefaehig(): bool
    {
        return $this->kategorisierungsTyp->isUmlagefaehig();
    }
}
