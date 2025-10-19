<?php

namespace App\Entity;

use App\Repository\WegRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WegRepository::class)]
class Weg
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $bezeichnung = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $adresse = null;

    /**
     * @var Collection<int, WegEinheit>
     */
    #[ORM\OneToMany(mappedBy: 'weg', targetEntity: WegEinheit::class)]
    private Collection $einheiten;

    public function __construct()
    {
        $this->einheiten = new ArrayCollection();
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    /**
     * @return Collection<int, WegEinheit>
     */
    public function getEinheiten(): Collection
    {
        return $this->einheiten;
    }

    public function addEinheit(WegEinheit $einheit): self
    {
        if (!$this->einheiten->contains($einheit)) {
            $this->einheiten[] = $einheit;
            $einheit->setWeg($this);
        }

        return $this;
    }

    public function removeEinheit(WegEinheit $einheit): self
    {
        if ($this->einheiten->removeElement($einheit)) {
            // set the owning side to null (unless already changed)
            if ($einheit->getWeg() === $this) {
                $einheit->setWeg(null);
            }
        }

        return $this;
    }
}
