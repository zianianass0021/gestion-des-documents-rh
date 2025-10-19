<?php

namespace App\Entity;

use App\Repository\EmployeRepository;
use App\Repository\NatureContratTypeDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: EmployeRepository::class)]
#[ORM\Table(name: 't_employe')]
class Employe implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;


    #[ORM\OneToMany(targetEntity: EmployeeContrat::class, mappedBy: 'employe', cascade: ['persist', 'remove'])]
    private Collection $employeeContrats;

    #[ORM\OneToOne(targetEntity: Dossier::class, mappedBy: 'employe', cascade: ['persist', 'remove'])]
    private ?Dossier $dossier = null;

    #[ORM\OneToMany(targetEntity: Demande::class, mappedBy: 'employe', cascade: ['persist', 'remove'])]
    private Collection $demandes;

    public function __construct()
    {
        $this->employeeContrats = new ArrayCollection();
        $this->demandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

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


    /**
     * @return Collection<int, EmployeeContrat>
     */
    public function getEmployeeContrats(): Collection
    {
        return $this->employeeContrats;
    }

    public function addEmployeeContrat(EmployeeContrat $employeeContrat): static
    {
        // Empêcher l'ajout de contrats aux administrateurs RH et responsables RH
        if (in_array('ROLE_ADMINISTRATEUR_RH', $this->roles) || in_array('ROLE_RESPONSABLE_RH', $this->roles)) {
            throw new \InvalidArgumentException('Les administrateurs RH et responsables RH ne peuvent pas avoir de contrats.');
        }
        
        if (!$this->employeeContrats->contains($employeeContrat)) {
            $this->employeeContrats->add($employeeContrat);
            $employeeContrat->setEmploye($this);
        }

        return $this;
    }

    public function removeEmployeeContrat(EmployeeContrat $employeeContrat): static
    {
        if ($this->employeeContrats->removeElement($employeeContrat)) {
            // set the owning side to null (unless already changed)
            if ($employeeContrat->getEmploye() === $this) {
                $employeeContrat->setEmploye(null);
            }
        }

        return $this;
    }

    public function getDossier(): ?Dossier
    {
        return $this->dossier;
    }

    public function setDossier(?Dossier $dossier): static
    {
        $this->dossier = $dossier;
        return $this;
    }

    /**
     * @return Collection<int, Demande>
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): static
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes->add($demande);
            $demande->setEmploye($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): static
    {
        if ($this->demandes->removeElement($demande)) {
            // set the owning side to null (unless already changed)
            if ($demande->getEmploye() === $this) {
                $demande->setEmploye(null);
            }
        }

        return $this;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Get all required documents across all active contracts
     * This creates a union of all document requirements from all contracts
     */
    public function getAllRequiredDocuments(NatureContratTypeDocumentRepository $repository): array
    {
        $allRequiredDocs = [];
        
        foreach ($this->employeeContrats as $contrat) {
            if ($contrat->isActive()) {
                $contractDocs = $contrat->getRequiredDocuments($repository);
                foreach ($contractDocs as $doc) {
                    $abbreviation = $doc->getDocumentAbbreviation();
                    // If document already exists, keep the most restrictive requirement (required = true wins)
                    if (!isset($allRequiredDocs[$abbreviation]) || $doc->isRequired()) {
                        $allRequiredDocs[$abbreviation] = [
                            'abbreviation' => $abbreviation,
                            'required' => $doc->isRequired(),
                            'contractType' => $doc->getContractType()
                        ];
                    }
                }
            }
        }
        
        return array_values($allRequiredDocs);
    }

    /**
     * Get active contracts only
     */
    public function getActiveContracts(): Collection
    {
        return $this->employeeContrats->filter(function($contrat) {
            return $contrat->isActive();
        });
    }
}
