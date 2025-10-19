<?php

namespace App\Entity;

use App\Repository\DienstleisterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DienstleisterRepository::class)]
class Dienstleister
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $bezeichnung = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $artDienstleister = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $vertrag = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $datumInkrafttreten = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $vertragsende = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $preisProJahr = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $datumUnterzeichnung = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $kuendigungsfrist = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $vertragsreferenz = null;

    /**
     * @var Collection<int, Dokument>
     */
    #[ORM\OneToMany(mappedBy: 'dienstleister', targetEntity: Dokument::class, orphanRemoval: true)]
    private Collection $dokumente;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $parserConfig = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $parserClass = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiParsingPrompt = null;

    #[ORM\Column(name: 'parser_enabled', type: 'boolean')]
    private bool $parserEnabled = false;

    public function __construct()
    {
        $this->dokumente = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getArtDienstleister(): ?string
    {
        return $this->artDienstleister;
    }

    public function setArtDienstleister(?string $artDienstleister): self
    {
        $this->artDienstleister = $artDienstleister;

        return $this;
    }

    public function getVertrag(): ?string
    {
        return $this->vertrag;
    }

    public function setVertrag(?string $vertrag): self
    {
        $this->vertrag = $vertrag;

        return $this;
    }

    public function getDatumInkrafttreten(): ?\DateTimeInterface
    {
        return $this->datumInkrafttreten;
    }

    public function setDatumInkrafttreten(?\DateTimeInterface $datumInkrafttreten): self
    {
        $this->datumInkrafttreten = $datumInkrafttreten;

        return $this;
    }

    public function getVertragsende(): ?int
    {
        return $this->vertragsende;
    }

    public function setVertragsende(?int $vertragsende): self
    {
        $this->vertragsende = $vertragsende;

        return $this;
    }

    // Update getter and setter for preisProJahr to use string type
    public function getPreisProJahr(): ?string
    {
        return $this->preisProJahr;
    }

    public function setPreisProJahr(?string $preisProJahr): self
    {
        $this->preisProJahr = $preisProJahr;

        return $this;
    }

    public function getDatumUnterzeichnung(): ?\DateTimeInterface
    {
        return $this->datumUnterzeichnung;
    }

    public function setDatumUnterzeichnung(?\DateTimeInterface $datumUnterzeichnung): self
    {
        $this->datumUnterzeichnung = $datumUnterzeichnung;

        return $this;
    }

    public function getKuendigungsfrist(): ?int
    {
        return $this->kuendigungsfrist;
    }

    public function setKuendigungsfrist(?int $kuendigungsfrist): self
    {
        $this->kuendigungsfrist = $kuendigungsfrist;

        return $this;
    }

    public function getVertragsreferenz(): ?string
    {
        return $this->vertragsreferenz;
    }

    public function setVertragsreferenz(?string $vertragsreferenz): self
    {
        $this->vertragsreferenz = $vertragsreferenz;

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
            $dokument->setDienstleister($this);
        }

        return $this;
    }

    public function removeDokument(Dokument $dokument): static
    {
        if ($this->dokumente->removeElement($dokument)) {
            if ($dokument->getDienstleister() === $this) {
                $dokument->setDienstleister(null);
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getParserConfig(): ?array
    {
        return $this->parserConfig;
    }

    /**
     * @param array<string, mixed>|null $parserConfig
     */
    public function setParserConfig(?array $parserConfig): self
    {
        $this->parserConfig = $parserConfig;

        return $this;
    }

    public function getParserClass(): ?string
    {
        return $this->parserClass;
    }

    public function setParserClass(?string $parserClass): self
    {
        $this->parserClass = $parserClass;

        return $this;
    }

    public function getAiParsingPrompt(): ?string
    {
        return $this->aiParsingPrompt;
    }

    public function setAiParsingPrompt(?string $aiParsingPrompt): self
    {
        $this->aiParsingPrompt = $aiParsingPrompt;

        return $this;
    }

    public function isParserEnabled(): bool
    {
        return $this->parserEnabled;
    }

    public function setParserEnabled(bool $parserEnabled): self
    {
        $this->parserEnabled = $parserEnabled;

        return $this;
    }
}
