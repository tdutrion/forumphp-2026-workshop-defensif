<?php

namespace App\Account\Entity;

use App\Account\Repository\LinkedAccountRepository;
use App\Security\UserInfo;
use App\Security\VerifiedEmail;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LinkedAccountRepository::class)]
#[ORM\UniqueConstraint(columns: ['provider', 'provider_user_id'])]
class LinkedAccount
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $providerUserId;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column]
    private bool $emailVerified;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'linkedAccounts')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        /** Provider name: 'google', 'github' or 'local'. */
        #[ORM\Column(length: 32)]
        private string $provider,
        UserInfo $info,
    ) {
        $this->id = Uuid::v7();
        $this->providerUserId = $info->id;
        $this->email = $info->email?->value;
        $this->emailVerified = $info->email instanceof VerifiedEmail;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
