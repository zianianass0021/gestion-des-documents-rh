<?php

namespace App\Entity;

use App\Repository\ModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\PreUpdate;

#[ORM\Entity(repositoryClass: ModuleRepository::class)]
#[ORM\Table(name: 'modules')]
#[ORM\UniqueConstraint(name: 'unique_module_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class Module
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $routePrefix = null;

    #[ORM\Column(name: 'sort_order', type: Types::SMALLINT, options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: UserModule::class, mappedBy: 'module', cascade: ['remove'])]
    private Collection $userModules;

    public function __construct()
    {
        $this->userModules = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getRoutePrefix(): ?string
    {
        return $this->routePrefix;
    }

    public function setRoutePrefix(?string $routePrefix): static
    {
        $this->routePrefix = $routePrefix;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, UserModule>
     */
    public function getUserModules(): Collection
    {
        return $this->userModules;
    }

    public function addUserModule(UserModule $userModule): static
    {
        if (!$this->userModules->contains($userModule)) {
            $this->userModules->add($userModule);
            $userModule->setModule($this);
        }

        return $this;
    }

    public function removeUserModule(UserModule $userModule): static
    {
        if ($this->userModules->removeElement($userModule)) {
            // set the owning side to null (unless already changed)
            if ($userModule->getModule() === $this) {
                $userModule->setModule(null);
            }
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

