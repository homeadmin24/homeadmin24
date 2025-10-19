<?php

namespace App\Entity;

use App\Repository\RechnungRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RechnungRepository::class)]
class Rechnung
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $information = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $ausstehend = null;

    #[ORM\ManyToOne(targetEntity: Dienstleister::class)]
    private ?Dienstleister $dienstleister = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rechnungsnummer = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $betragMitSteuern = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $gesamtMwSt = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $datumLeistung = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $faelligkeitsdatum = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $arbeitsFahrtkosten = null;

    /**
     * @var Collection<int, Dokument>
     */
    #[ORM\OneToMany(mappedBy: 'rechnung', targetEntity: Dokument::class, orphanRemoval: true)]
    private Collection $dokumente;

    public function __construct()
    {
        $this->dokumente = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInformation(): ?string
    {
        return $this->information;
    }

    public function setInformation(string $information): self
    {
        $this->information = $information;

        return $this;
    }

    public function isAusstehend(): ?bool
    {
        return $this->ausstehend;
    }

    public function setAusstehend(bool $ausstehend): self
    {
        $this->ausstehend = $ausstehend;

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

    public function getRechnungsnummer(): ?string
    {
        return $this->rechnungsnummer;
    }

    public function setRechnungsnummer(?string $rechnungsnummer): self
    {
        $this->rechnungsnummer = $rechnungsnummer;

        return $this;
    }

    public function getBetragMitSteuern(): ?string
    {
        return $this->betragMitSteuern;
    }

    public function setBetragMitSteuern(string $betragMitSteuern): self
    {
        $this->betragMitSteuern = $betragMitSteuern;

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

    public function getDatumLeistung(): ?\DateTimeInterface
    {
        return $this->datumLeistung;
    }

    public function setDatumLeistung(?\DateTimeInterface $datumLeistung): self
    {
        $this->datumLeistung = $datumLeistung;

        return $this;
    }

    public function getFaelligkeitsdatum(): ?\DateTimeInterface
    {
        return $this->faelligkeitsdatum;
    }

    public function setFaelligkeitsdatum(?\DateTimeInterface $faelligkeitsdatum): self
    {
        $this->faelligkeitsdatum = $faelligkeitsdatum;

        return $this;
    }

    public function getArbeitsFahrtkosten(): ?string
    {
        return $this->arbeitsFahrtkosten;
    }

    public function setArbeitsFahrtkosten(?string $arbeitsFahrtkosten): self
    {
        $this->arbeitsFahrtkosten = $arbeitsFahrtkosten;

        return $this;
    }

    /**
     * @return Collection<int, Dokument>
     */
    public function getDokumente(): Collection
    {
        return $this->dokumente;
    }

    public function addDokument(Dokument $dokument): static
    {
        if (!$this->dokumente->contains($dokument)) {
            $this->dokumente->add($dokument);
            $dokument->setRechnung($this);
        }

        return $this;
    }

    public function removeDokument(Dokument $dokument): static
    {
        if ($this->dokumente->removeElement($dokument)) {
            if ($dokument->getRechnung() === $this) {
                $dokument->setRechnung(null);
            }
        }

        return $this;
    }
}
