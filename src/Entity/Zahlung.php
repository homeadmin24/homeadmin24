<?php

namespace App\Entity;

use App\Repository\ZahlungRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ZahlungRepository::class)]
class Zahlung
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $datum = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $bezeichnung = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    private ?string $betrag = null;

    #[ORM\ManyToOne(targetEntity: Zahlungskategorie::class)]
    private ?Zahlungskategorie $hauptkategorie = null;

    #[ORM\ManyToOne(targetEntity: Kostenkonto::class)]
    private ?Kostenkonto $kostenkonto = null;

    #[ORM\ManyToOne(targetEntity: WegEinheit::class)]
    private ?WegEinheit $eigentuemer = null;

    #[ORM\ManyToOne(targetEntity: Rechnung::class)]
    private ?Rechnung $rechnung = null;

    #[ORM\ManyToOne(targetEntity: Dienstleister::class)]
    private ?Dienstleister $dienstleister = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $gesamtMwSt = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $hndAnteil = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $abrechnungsjahrZuordnung = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isSimulation = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatum(): ?\DateTimeInterface
    {
        return $this->datum;
    }

    public function setDatum(\DateTimeInterface $datum): self
    {
        $this->datum = $datum;

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

    public function getBetrag(): ?string
    {
        return $this->betrag;
    }

    public function setBetrag(string $betrag): self
    {
        $this->betrag = $betrag;

        return $this;
    }

    public function getHauptkategorie(): ?Zahlungskategorie
    {
        return $this->hauptkategorie;
    }

    public function setHauptkategorie(?Zahlungskategorie $hauptkategorie): self
    {
        $this->hauptkategorie = $hauptkategorie;

        return $this;
    }

    public function getKostenkonto(): ?Kostenkonto
    {
        return $this->kostenkonto;
    }

    public function setKostenkonto(?Kostenkonto $kostenkonto): self
    {
        $this->kostenkonto = $kostenkonto;

        return $this;
    }

    public function getEigentuemer(): ?WegEinheit
    {
        return $this->eigentuemer;
    }

    public function setEigentuemer(?WegEinheit $eigentuemer): self
    {
        $this->eigentuemer = $eigentuemer;

        return $this;
    }

    public function getRechnung(): ?Rechnung
    {
        return $this->rechnung;
    }

    public function setRechnung(?Rechnung $rechnung): self
    {
        $this->rechnung = $rechnung;

        return $this;
    }

    public function getDienstleister(): ?Dienstleister
    {
        return $this->dienstleister;
    }

    public function setDienstleister(?Dienstleister $dienstleister): self
    {
        $this->dienstleister = $dienstleister;

        return $this;
    }

    public function getGesamtMwSt(): ?string
    {
        return $this->gesamtMwSt;
    }

    public function setGesamtMwSt(?string $gesamtMwSt): self
    {
        $this->gesamtMwSt = $gesamtMwSt;

        return $this;
    }

    public function getHndAnteil(): ?string
    {
        return $this->hndAnteil;
    }

    public function setHndAnteil(?string $hndAnteil): self
    {
        $this->hndAnteil = $hndAnteil;

        return $this;
    }

    public function getAbrechnungsjahrZuordnung(): ?int
    {
        return $this->abrechnungsjahrZuordnung;
    }

    public function setAbrechnungsjahrZuordnung(?int $abrechnungsjahrZuordnung): self
    {
        $this->abrechnungsjahrZuordnung = $abrechnungsjahrZuordnung;

        return $this;
    }

    public function isSimulation(): bool
    {
        return $this->isSimulation;
    }

    public function setIsSimulation(bool $isSimulation): self
    {
        $this->isSimulation = $isSimulation;

        return $this;
    }
}
