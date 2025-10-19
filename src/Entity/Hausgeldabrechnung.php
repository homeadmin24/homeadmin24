<?php

namespace App\Entity;

use App\Repository\HausgeldabrechnungRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HausgeldabrechnungRepository::class)]
class Hausgeldabrechnung
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Weg::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Weg $weg = null;

    #[ORM\Column(type: 'integer')]
    private int $jahr;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pdfPfad = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $erstellungsdatum;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $gesamtkosten;

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

    public function getJahr(): int
    {
        return $this->jahr;
    }

    public function setJahr(int $jahr): self
    {
        $this->jahr = $jahr;

        return $this;
    }

    public function getPdfPfad(): ?string
    {
        return $this->pdfPfad;
    }

    public function setPdfPfad(?string $pdfPfad): self
    {
        $this->pdfPfad = $pdfPfad;

        return $this;
    }

    public function getErstellungsdatum(): \DateTimeInterface
    {
        return $this->erstellungsdatum;
    }

    public function setErstellungsdatum(\DateTimeInterface $erstellungsdatum): self
    {
        $this->erstellungsdatum = $erstellungsdatum;

        return $this;
    }

    public function getGesamtkosten(): string
    {
        return $this->gesamtkosten;
    }

    public function setGesamtkosten(string $gesamtkosten): self
    {
        $this->gesamtkosten = $gesamtkosten;

        return $this;
    }
}
