<?php

namespace App\Entity;

use App\Repository\UmlageschluesselRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UmlageschluesselRepository::class)]
class Umlageschluessel implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $schluessel = null;

    #[ORM\Column(length: 255)]
    private ?string $bezeichnung = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $beschreibung = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $gesamtumlage = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $umlageTyp = null;

    /**
     * @var Collection<int, Kostenkonto>
     */
    #[ORM\OneToMany(mappedBy: 'umlageschluessel', targetEntity: Kostenkonto::class)]
    private Collection $kostenkonten;

    public function __construct()
    {
        $this->kostenkonten = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->schluessel . ' - ' . $this->bezeichnung;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchluessel(): ?string
    {
        return $this->schluessel;
    }

    public function setSchluessel(string $schluessel): static
    {
        $this->schluessel = $schluessel;

        return $this;
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

    public function getBeschreibung(): ?string
    {
        return $this->beschreibung;
    }

    public function setBeschreibung(?string $beschreibung): static
    {
        $this->beschreibung = $beschreibung;

        return $this;
    }

    public function getGesamtumlage(): ?string
    {
        return $this->gesamtumlage;
    }

    public function setGesamtumlage(?string $gesamtumlage): static
    {
        $this->gesamtumlage = $gesamtumlage;

        return $this;
    }

    public function getUmlageTyp(): ?string
    {
        return $this->umlageTyp;
    }

    public function setUmlageTyp(?string $umlageTyp): static
    {
        $this->umlageTyp = $umlageTyp;

        return $this;
    }

    /**
     * @return Collection<int, Kostenkonto>
     */
    public function getKostenkonten(): Collection
    {
        return $this->kostenkonten;
    }

    public function addKostenkonto(Kostenkonto $kostenkonto): static
    {
        if (!$this->kostenkonten->contains($kostenkonto)) {
            $this->kostenkonten->add($kostenkonto);
            $kostenkonto->setUmlageschluessel($this);
        }

        return $this;
    }

    public function removeKostenkonto(Kostenkonto $kostenkonto): static
    {
        if ($this->kostenkonten->removeElement($kostenkonto)) {
            if ($kostenkonto->getUmlageschluessel() === $this) {
                $kostenkonto->setUmlageschluessel(null);
            }
        }

        return $this;
    }
}
