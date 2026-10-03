<?php

namespace App\Entity;

use App\Repository\WegEinheitVorauszahlungRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WegEinheitVorauszahlungRepository::class)]
#[ORM\Table(name: 'weg_einheit_vorauszahlung')]
#[ORM\UniqueConstraint(name: 'unique_weg_einheit_year', columns: ['weg_einheit_id', 'year'])]
class WegEinheitVorauszahlung
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WegEinheit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?WegEinheit $wegEinheit = null;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $monthlyAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $yearlyAdvancePayment = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWegEinheit(): ?WegEinheit
    {
        return $this->wegEinheit;
    }

    public function setWegEinheit(?WegEinheit $wegEinheit): static
    {
        $this->wegEinheit = $wegEinheit;

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

    public function getMonthlyAmount(): ?string
    {
        return $this->monthlyAmount;
    }

    public function setMonthlyAmount(string $monthlyAmount): static
    {
        $this->monthlyAmount = $monthlyAmount;

        return $this;
    }

    public function getYearlyAdvancePayment(): ?string
    {
        return $this->yearlyAdvancePayment;
    }

    public function setYearlyAdvancePayment(?string $yearlyAdvancePayment): static
    {
        $this->yearlyAdvancePayment = $yearlyAdvancePayment;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get monthly amount as float for calculations.
     */
    public function getMonthlyAmountAsFloat(): float
    {
        return (float) $this->monthlyAmount;
    }

    /**
     * Get yearly advance payment as float for calculations.
     */
    public function getYearlyAdvancePaymentAsFloat(): ?float
    {
        return $this->yearlyAdvancePayment ? (float) $this->yearlyAdvancePayment : null;
    }
}
