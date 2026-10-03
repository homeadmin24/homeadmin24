<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'umlageschluessel_einheit')]
#[ORM\Index(columns: ['umlageschluessel_id'], name: 'idx_umlageschluessel')]
#[ORM\Index(columns: ['weg_einheit_id'], name: 'idx_weg_einheit')]
class UmlageschluesselEinheit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Umlageschluessel $umlageschluessel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?WegEinheit $wegEinheit = null;

    #[ORM\Column(length: 50)]
    private ?string $anteil = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUmlageschluessel(): ?Umlageschluessel
    {
        return $this->umlageschluessel;
    }

    public function setUmlageschluessel(?Umlageschluessel $umlageschluessel): static
    {
        $this->umlageschluessel = $umlageschluessel;

        return $this;
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

    public function getAnteil(): ?string
    {
        return $this->anteil;
    }

    public function setAnteil(string $anteil): static
    {
        $this->anteil = $anteil;

        return $this;
    }
}
