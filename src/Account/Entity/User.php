<?php

namespace App\Account\Entity;

use App\Account\LastConnection;
use App\Account\Repository\UserRepository;
use App\Account\Theme;
use App\Security\UserInfo;
use App\Security\VerifiedEmail;
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

    private function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->linkedAccounts = new ArrayCollection();
    }

    /**
     * An account is born with its first connection: its email is the one the provider verified, if any.
     */
    public static function fromProviderIdentity(string $provider, UserInfo $info): self
    {
        $user = new self();
        $user->email = $info->email instanceof VerifiedEmail ? $info->email->value : null;
        $user->displayName = $info->name ?? $info->email?->value;
        $user->connect($provider, $info);

        return $user;
    }

    /**
     * Another way to sign in to the same account.
     */
    public function connect(string $provider, UserInfo $info): void
    {
        $this->linkedAccounts->add(new LinkedAccount($this, $provider, $info));
    }

    /**
     * @throws LastConnection
     */
    public function disconnect(LinkedAccount $linkedAccount): void
    {
        if (!$this->linkedAccounts->contains($linkedAccount)) {
            return;
        }
        if ($this->linkedAccounts->count() <= 1) {
            throw new LastConnection();
        }
        $this->linkedAccounts->removeElement($linkedAccount);
    }

    /**
     * The email of the account, when it has none yet: only one a provider verified.
     */
    public function adoptEmail(VerifiedEmail $email): void
    {
        $this->email ??= $email->value;
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

    public function getDisplayName(): ?string
    {
        return $this->displayName;
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
