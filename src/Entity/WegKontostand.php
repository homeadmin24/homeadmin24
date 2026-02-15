<?php

namespace App\Entity;

use App\Repository\WegKontostandRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WegKontostandRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_WEG_KONTOSTAND_YEAR', columns: ['weg_id', 'year', 'bankkonto_typ'])]
class WegKontostand
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Weg::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Weg $weg = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $year = null;

    #[ORM\Column(length: 20)]
    private string $bankkontoTyp = 'hausgeld';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $stichtagStart = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $stichtagEndPeriode = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $stichtagEnd = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $saldoStart = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $saldoEndPeriode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $saldoEnd = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bemerkung = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        // Set default year for new entities
        if (null === $this->year) {
            $this->year = (int) date('Y');
        }
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getBankkontoTyp(): string
    {
        return $this->bankkontoTyp;
    }

    public function setBankkontoTyp(string $bankkontoTyp): static
    {
        $this->bankkontoTyp = $bankkontoTyp;

        return $this;
    }

    public function getStichtagStart(): ?\DateTimeInterface
    {
        return $this->stichtagStart;
    }

    public function setStichtagStart(\DateTimeInterface $stichtagStart): static
    {
        $this->stichtagStart = $stichtagStart;

        return $this;
    }

    public function getStichtagEnd(): ?\DateTimeInterface
    {
        return $this->stichtagEnd;
    }

    public function setStichtagEnd(\DateTimeInterface $stichtagEnd): static
    {
        $this->stichtagEnd = $stichtagEnd;

        return $this;
    }

    public function getSaldoStart(): ?string
    {
        return $this->saldoStart;
    }

    public function setSaldoStart(string $saldoStart): static
    {
        $this->saldoStart = $saldoStart;

        return $this;
    }

    public function getStichtagEndPeriode(): ?\DateTimeInterface
    {
        return $this->stichtagEndPeriode;
    }

    public function setStichtagEndPeriode(?\DateTimeInterface $stichtagEndPeriode): static
    {
        $this->stichtagEndPeriode = $stichtagEndPeriode;

        return $this;
    }

    public function getSaldoEndPeriode(): ?string
    {
        return $this->saldoEndPeriode;
    }

    public function setSaldoEndPeriode(?string $saldoEndPeriode): static
    {
        $this->saldoEndPeriode = $saldoEndPeriode;

        return $this;
    }

    public function getSaldoEnd(): ?string
    {
        return $this->saldoEnd;
    }

    public function setSaldoEnd(string $saldoEnd): static
    {
        $this->saldoEnd = $saldoEnd;

        return $this;
    }

    public function getBemerkung(): ?string
    {
        return $this->bemerkung;
    }

    public function setBemerkung(?string $bemerkung): static
    {
        $this->bemerkung = $bemerkung;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
