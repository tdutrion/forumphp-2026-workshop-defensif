<?php

namespace App\Account\Entity;

use App\Account\Repository\UserRepository;
use App\Account\Theme;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** Email verified by at least one provider, otherwise null; in lower case, compared byte for byte (josé@ is not jose@). */
    #[ORM\Column(length: 180, unique: true, nullable: true, options: ['collation' => 'utf8mb4_bin'])]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 5, enumType: Theme::class, options: ['default' => 'auto'])]
    private Theme $theme = Theme::Auto;

    /** Number of films per page in the film lists: one of FilmPage::PAGE_SIZES. */
    #[ORM\Column(options: ['default' => 20])]
    private int $pageSize = 20;

    /** @var Collection<int, LinkedAccount> */
    #[ORM\OneToMany(targetEntity: LinkedAccount::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
    private Collection $linkedAccounts;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->linkedAccounts = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, LinkedAccount> */
    public function getLinkedAccounts(): Collection
    {
        return $this->linkedAccounts;
    }

    public function addLinkedAccount(LinkedAccount $linkedAccount): static
    {
        if (!$this->linkedAccounts->contains($linkedAccount)) {
            $this->linkedAccounts->add($linkedAccount);
            $linkedAccount->setUser($this);
        }

        return $this;
    }

    public function removeLinkedAccount(LinkedAccount $linkedAccount): static
    {
        $this->linkedAccounts->removeElement($linkedAccount);

        return $this;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function setPageSize(int $pageSize): static
    {
        $this->pageSize = $pageSize;

        return $this;
    }
}
