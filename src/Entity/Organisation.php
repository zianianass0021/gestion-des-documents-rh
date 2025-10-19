<?php

namespace App\Entity;

use App\Repository\OrganisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganisationRepository::class)]
#[ORM\Table(name: 'p_organisation')]
class Organisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $divisionActivitesStrategiques = null;

    #[ORM\Column(length: 10)]
    private ?string $das = null;

    #[ORM\Column(length: 10)]
    private ?string $groupement = null;

    #[ORM\Column(length: 10)]
    private ?string $dossier = null;

    #[ORM\Column(length: 255)]
    private ?string $dossierDesignation = null;

    #[ORM\OneToMany(targetEntity: OrganisationEmployeeContrat::class, mappedBy: 'organisation', cascade: ['persist', 'remove'])]
    private Collection $organisationEmployeeContrats;

    public function __construct()
    {
        $this->organisationEmployeeContrats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDivisionActivitesStrategiques(): ?string
    {
        return $this->divisionActivitesStrategiques;
    }

    public function setDivisionActivitesStrategiques(string $divisionActivitesStrategiques): static
    {
        $this->divisionActivitesStrategiques = $divisionActivitesStrategiques;

        return $this;
    }

    public function getDas(): ?string
    {
        return $this->das;
    }

    public function setDas(string $das): static
    {
        $this->das = $das;

        return $this;
    }

    public function getGroupement(): ?string
    {
        return $this->groupement;
    }

    public function setGroupement(string $groupement): static
    {
        $this->groupement = $groupement;

        return $this;
    }

    public function getDossier(): ?string
    {
        return $this->dossier;
    }

    public function setDossier(string $dossier): static
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getDossierDesignation(): ?string
    {
        return $this->dossierDesignation;
    }

    public function setDossierDesignation(string $dossierDesignation): static
    {
        $this->dossierDesignation = $dossierDesignation;

        return $this;
    }

    /**
     * @return Collection<int, OrganisationEmployeeContrat>
     */
    public function getOrganisationEmployeeContrats(): Collection
    {
        return $this->organisationEmployeeContrats;
    }

    public function addOrganisationEmployeeContrat(OrganisationEmployeeContrat $organisationEmployeeContrat): static
    {
        if (!$this->organisationEmployeeContrats->contains($organisationEmployeeContrat)) {
            $this->organisationEmployeeContrats->add($organisationEmployeeContrat);
            $organisationEmployeeContrat->setOrganisation($this);
        }

        return $this;
    }

    public function removeOrganisationEmployeeContrat(OrganisationEmployeeContrat $organisationEmployeeContrat): static
    {
        if ($this->organisationEmployeeContrats->removeElement($organisationEmployeeContrat)) {
            // set the owning side to null (unless already changed)
            if ($organisationEmployeeContrat->getOrganisation() === $this) {
                $organisationEmployeeContrat->setOrganisation(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->dossierDesignation ?? '';
    }
}
