<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\KategorisierungCorrectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks user corrections to payment categorizations.
 * Used for learning and improving AI/pattern matching accuracy over time.
 */
#[ORM\Entity(repositoryClass: KategorisierungCorrectionRepository::class)]
#[ORM\Table(name: 'kategorisierung_correction')]
#[ORM\Index(name: 'IDX_KAT_CORR_ZAHLUNG', columns: ['zahlung_id'])]
#[ORM\Index(name: 'IDX_KAT_CORR_CREATED_AT', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_KAT_CORR_PARTNER', columns: ['zahlung_partner'])]
class KategorisierungCorrection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Zahlung::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Zahlung $zahlung;

    #[ORM\ManyToOne(targetEntity: Kostenkonto::class)]
    private ?Kostenkonto $suggestedKostenkonto = null;

    #[ORM\ManyToOne(targetEntity: Zahlungskategorie::class)]
    private ?Zahlungskategorie $suggestedKategorie = null;

    #[ORM\ManyToOne(targetEntity: Kostenkonto::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Kostenkonto $actualKostenkonto;

    #[ORM\ManyToOne(targetEntity: Zahlungskategorie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Zahlungskategorie $actualKategorie;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, nullable: true)]
    private ?string $suggestedConfidence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $suggestedReasoning = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $correctionType; // pattern_failed, ai_failed, user_override

    #[ORM\Column(type: 'string', length: 50)]
    private string $correctionSource = 'manual_edit'; // manual_edit, bulk_review, import

    #[ORM\Column(type: 'string', length: 255)]
    private string $zahlungBezeichnung;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $zahlungPartner = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $zahlungBetrag;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $zahlungDatum;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZahlung(): Zahlung
    {
        return $this->zahlung;
    }

    public function setZahlung(Zahlung $zahlung): self
    {
        $this->zahlung = $zahlung;

        return $this;
    }

    public function getSuggestedKostenkonto(): ?Kostenkonto
    {
        return $this->suggestedKostenkonto;
    }

    public function setSuggestedKostenkonto(?Kostenkonto $suggestedKostenkonto): self
    {
        $this->suggestedKostenkonto = $suggestedKostenkonto;

        return $this;
    }

    public function getSuggestedKategorie(): ?Zahlungskategorie
    {
        return $this->suggestedKategorie;
    }

    public function setSuggestedKategorie(?Zahlungskategorie $suggestedKategorie): self
    {
        $this->suggestedKategorie = $suggestedKategorie;

        return $this;
    }

    public function getActualKostenkonto(): Kostenkonto
    {
        return $this->actualKostenkonto;
    }

    public function setActualKostenkonto(Kostenkonto $actualKostenkonto): self
    {
        $this->actualKostenkonto = $actualKostenkonto;

        return $this;
    }

    public function getActualKategorie(): Zahlungskategorie
    {
        return $this->actualKategorie;
    }

    public function setActualKategorie(Zahlungskategorie $actualKategorie): self
    {
        $this->actualKategorie = $actualKategorie;

        return $this;
    }

    public function getSuggestedConfidence(): ?string
    {
        return $this->suggestedConfidence;
    }

    public function setSuggestedConfidence(?string $suggestedConfidence): self
    {
        $this->suggestedConfidence = $suggestedConfidence;

        return $this;
    }

    public function getSuggestedReasoning(): ?string
    {
        return $this->suggestedReasoning;
    }

    public function setSuggestedReasoning(?string $suggestedReasoning): self
    {
        $this->suggestedReasoning = $suggestedReasoning;

        return $this;
    }

    public function getCorrectionType(): string
    {
        return $this->correctionType;
    }

    public function setCorrectionType(string $correctionType): self
    {
        $this->correctionType = $correctionType;

        return $this;
    }

    public function getCorrectionSource(): string
    {
        return $this->correctionSource;
    }

    public function setCorrectionSource(string $correctionSource): self
    {
        $this->correctionSource = $correctionSource;

        return $this;
    }

    public function getZahlungBezeichnung(): string
    {
        return $this->zahlungBezeichnung;
    }

    public function setZahlungBezeichnung(string $zahlungBezeichnung): self
    {
        $this->zahlungBezeichnung = $zahlungBezeichnung;

        return $this;
    }

    public function getZahlungPartner(): ?string
    {
        return $this->zahlungPartner;
    }

    public function setZahlungPartner(?string $zahlungPartner): self
    {
        $this->zahlungPartner = $zahlungPartner;

        return $this;
    }

    public function getZahlungBetrag(): string
    {
        return $this->zahlungBetrag;
    }

    public function setZahlungBetrag(string $zahlungBetrag): self
    {
        $this->zahlungBetrag = $zahlungBetrag;

        return $this;
    }

    public function getZahlungDatum(): \DateTimeInterface
    {
        return $this->zahlungDatum;
    }

    public function setZahlungDatum(\DateTimeInterface $zahlungDatum): self
    {
        $this->zahlungDatum = $zahlungDatum;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
