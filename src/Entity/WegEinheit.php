<?php

namespace App\Entity;

use App\Repository\WegEinheitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WegEinheitRepository::class)]
class WegEinheit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nummer = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $bezeichnung = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $hauptwohneinheit = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $miteigentuemer = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $miteigentumsanteile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stimme = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $hebeanlage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $telefon = null;

    #[ORM\ManyToOne(targetEntity: Weg::class, inversedBy: 'einheiten')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Weg $weg = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNummer(): ?string
    {
        return $this->nummer;
    }

    public function setNummer(string $nummer): self
    {
        $this->nummer = $nummer;

        return $this;
    }

    public function getBezeichnung(): ?string
    {
        return $this->bezeichnung;
    }

    public function setBezeichnung(string $bezeichnung): self
    {
        $this->bezeichnung = $bezeichnung;

        return $this;
    }

    public function getHauptwohneinheit(): ?bool
    {
        return $this->hauptwohneinheit;
    }

    public function setHauptwohneinheit(bool $hauptwohneinheit): self
    {
        $this->hauptwohneinheit = $hauptwohneinheit;

        return $this;
    }

    public function getMiteigentuemer(): ?string
    {
        return $this->miteigentuemer;
    }

    public function setMiteigentuemer(string $miteigentuemer): self
    {
        $this->miteigentuemer = $miteigentuemer;

        return $this;
    }

    public function getMiteigentumsanteile(): ?string
    {
        return $this->miteigentumsanteile;
    }

    public function setMiteigentumsanteile(string $miteigentumsanteile): self
    {
        $this->miteigentumsanteile = $miteigentumsanteile;

        return $this;
    }

    public function getStimme(): ?string
    {
        return $this->stimme;
    }

    public function setStimme(?string $stimme): self
    {
        $this->stimme = $stimme;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelefon(): ?string
    {
        return $this->telefon;
    }

    public function setTelefon(?string $telefon): self
    {
        $this->telefon = $telefon;

        return $this;
    }

    public function getHebeanlage(): ?string
    {
        return $this->hebeanlage;
    }

    public function setHebeanlage(?string $hebeanlage): self
    {
        $this->hebeanlage = $hebeanlage;

        return $this;
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
}
