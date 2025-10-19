<?php

namespace App\Entity;

use App\Repository\NatureContratRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NatureContratRepository::class)]
#[ORM\Table(name: 'p_nature_contrat')]
class NatureContrat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $designation = null;

    #[ORM\OneToMany(targetEntity: EmployeeContrat::class, mappedBy: 'natureContrat')]
    private Collection $employeeContrats;

    public function __construct()
    {
        $this->employeeContrats = new ArrayCollection();
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

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;

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
        if (!$this->employeeContrats->contains($employeeContrat)) {
            $this->employeeContrats->add($employeeContrat);
            $employeeContrat->setNatureContrat($this);
        }

        return $this;
    }

    public function removeEmployeeContrat(EmployeeContrat $employeeContrat): static
    {
        if ($this->employeeContrats->removeElement($employeeContrat)) {
            // set the owning side to null (unless already changed)
            if ($employeeContrat->getNatureContrat() === $this) {
                $employeeContrat->setNatureContrat(null);
            }
        }

        return $this;
    }


    public function __toString(): string
    {
        return $this->designation ?? '';
    }
}