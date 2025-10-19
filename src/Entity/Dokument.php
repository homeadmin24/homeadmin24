<?php

namespace App\Entity;

use App\Repository\DokumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DokumentRepository::class)]
class Dokument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $dateiname = null;

    #[ORM\Column(length: 500)]
    private ?string $dateipfad = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dateityp = null;

    #[ORM\Column(nullable: true)]
    private ?int $dategroesse = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $uploadDatum = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $kategorie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $beschreibung = null;

    #[ORM\ManyToOne(inversedBy: 'dokumente')]
    private ?Rechnung $rechnung = null;

    #[ORM\ManyToOne(inversedBy: 'dokumente')]
    private ?Dienstleister $dienstleister = null;

    #[ORM\Column(nullable: true)]
    private ?int $abrechnungsJahr = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $einheitNummer = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $format = null;

    #[ORM\ManyToOne]
    private ?Weg $weg = null;

    public function __construct()
    {
        $this->uploadDatum = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateiname(): ?string
    {
        return $this->dateiname;
    }

    public function setDateiname(string $dateiname): static
    {
        $this->dateiname = $dateiname;

        return $this;
    }

    public function getDateipfad(): ?string
    {
        return $this->dateipfad;
    }

    public function setDateipfad(string $dateipfad): static
    {
        $this->dateipfad = $dateipfad;

        return $this;
    }

    public function getDateityp(): ?string
    {
        return $this->dateityp;
    }

    public function setDateityp(?string $dateityp): static
    {
        $this->dateityp = $dateityp;

        return $this;
    }

    public function getDategroesse(): ?int
    {
        return $this->dategroesse;
    }

    public function setDategroesse(?int $dategroesse): static
    {
        $this->dategroesse = $dategroesse;

        return $this;
    }

    public function getUploadDatum(): ?\DateTimeInterface
    {
        return $this->uploadDatum;
    }

    public function setUploadDatum(\DateTimeInterface $uploadDatum): static
    {
        $this->uploadDatum = $uploadDatum;

        return $this;
    }

    public function getKategorie(): ?string
    {
        return $this->kategorie;
    }

    public function setKategorie(?string $kategorie): static
    {
        $this->kategorie = $kategorie;

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

    public function getRechnung(): ?Rechnung
    {
        return $this->rechnung;
    }

    public function setRechnung(?Rechnung $rechnung): static
    {
        $this->rechnung = $rechnung;

        return $this;
    }

    public function getDienstleister(): ?Dienstleister
    {
        return $this->dienstleister;
    }

    public function setDienstleister(?Dienstleister $dienstleister): static
    {
        $this->dienstleister = $dienstleister;

        return $this;
    }

    public function getDateigroesseFormatiert(): string
    {
        if (!$this->dategroesse) {
            return '0 B';
        }

        $bytes = $this->dategroesse;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < \count($units) - 1; ++$i) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDateierweiterung(): string
    {
        return pathinfo($this->dateiname, \PATHINFO_EXTENSION);
    }

    public function getAbsoluterPfad(?string $projectDir = null): string
    {
        $projectDir = $projectDir ?: getcwd();

        return $projectDir . '/data/dokumente/' . $this->dateipfad;
    }

    public function getAbrechnungsJahr(): ?int
    {
        return $this->abrechnungsJahr;
    }

    public function setAbrechnungsJahr(?int $abrechnungsJahr): static
    {
        $this->abrechnungsJahr = $abrechnungsJahr;

        return $this;
    }

    public function getEinheitNummer(): ?string
    {
        return $this->einheitNummer;
    }

    public function setEinheitNummer(?string $einheitNummer): static
    {
        $this->einheitNummer = $einheitNummer;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getWeg(): ?Weg
    {
        return $this->weg;
    }

    public function setWeg(?Weg $weg): static
    {
        $this->weg = $weg;

        return $this;
    }

    public function isHausgeldabrechnung(): bool
    {
        return 'hausgeldabrechnung' === $this->kategorie;
    }
}
