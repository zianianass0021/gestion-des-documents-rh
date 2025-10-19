<?php

namespace App\Entity;

use App\Repository\EmployeeContratRepository;
use App\Repository\NatureContratTypeDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeContratRepository::class)]
#[ORM\Table(name: 't_employee_contrat')]
class EmployeeContrat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'employeeContrats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(inversedBy: 'employeeContrats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?NatureContrat $natureContrat = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $salaire = null;

    #[ORM\OneToMany(targetEntity: OrganisationEmployeeContrat::class, mappedBy: 'employeeContrat', cascade: ['persist', 'remove'])]
    private Collection $organisationEmployeeContrats;

    public function __construct()
    {
        $this->organisationEmployeeContrats = new ArrayCollection();
        $this->statut = 'actif';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;

        return $this;
    }

    public function getNatureContrat(): ?NatureContrat
    {
        return $this->natureContrat;
    }

    public function setNatureContrat(?NatureContrat $natureContrat): static
    {
        $this->natureContrat = $natureContrat;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getSalaire(): ?string
    {
        return $this->salaire;
    }

    public function setSalaire(?string $salaire): static
    {
        $this->salaire = $salaire;

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
            $organisationEmployeeContrat->setEmployeeContrat($this);
        }

        return $this;
    }

    public function removeOrganisationEmployeeContrat(OrganisationEmployeeContrat $organisationEmployeeContrat): static
    {
        if ($this->organisationEmployeeContrats->removeElement($organisationEmployeeContrat)) {
            // set the owning side to null (unless already changed)
            if ($organisationEmployeeContrat->getEmployeeContrat() === $this) {
                $organisationEmployeeContrat->setEmployeeContrat(null);
            }
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->statut === 'actif';
    }

    public function isExpired(): bool
    {
        if (!$this->dateFin) {
            return false;
        }
        return $this->dateFin < new \DateTime();
    }

    /**
     * Get required documents for this contract type
     * This method will be used to determine which documents are mandatory/optional
     */
    public function getRequiredDocuments(NatureContratTypeDocumentRepository $repository): array
    {
        if (!$this->natureContrat) {
            return [];
        }

        $contractType = $this->natureContrat->getDesignation();
        return $repository->findBy(['contractType' => $contractType]);
    }

    /**
     * Get all document abbreviations required for this contract
     */
    public function getRequiredDocumentAbbreviations(NatureContratTypeDocumentRepository $repository): array
    {
        $requiredDocs = $this->getRequiredDocuments($repository);
        $abbreviations = [];
        
        foreach ($requiredDocs as $doc) {
            $abbreviations[] = [
                'abbreviation' => $doc->getDocumentAbbreviation(),
                'required' => $doc->isRequired()
            ];
        }
        
        return $abbreviations;
    }
}