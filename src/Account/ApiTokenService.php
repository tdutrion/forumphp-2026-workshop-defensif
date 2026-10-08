<?php

declare(strict_types=1);

namespace App\Account;

use App\Account\Entity\ApiToken;
use App\Account\Entity\User;
use App\Account\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Personal API access tokens. Only their SHA-256 hash is stored.
 */
class ApiTokenService
{
    private const string PREFIX = 'mm_';
    private const string LIFETIME = '+90 days';

    public function __construct(
        private EntityManagerInterface $em,
        private ApiTokenRepository $apiTokenRepository,
    ) {
    }

    /**
     * @return string the plaintext token, to be shown to the user only once
     */
    public function create(User $user, string $name): string
    {
        $plain = self::PREFIX.bin2hex(random_bytes(32));

        $token = (new ApiToken())
            ->setUser($user)
            ->setName($name)
            ->setTokenHash(hash('sha256', $plain))
            ->setExpiresAt(new \DateTimeImmutable(self::LIFETIME));
        $this->em->persist($token);
        $this->em->flush();

        return $plain;
    }

    public function findUserByToken(#[\SensitiveParameter] string $token): ?User
    {
        // The lookup is done on the hash: the plaintext token is never compared nor stored.
        $apiToken = $this->apiTokenRepository->findOneBy(['tokenHash' => hash('sha256', $token)]);
        if (null === $apiToken || null !== $apiToken->getRevokedAt() || $apiToken->getExpiresAt() <= new \DateTimeImmutable()) {
            return null;
        }

        return $apiToken->getUser();
    }

    /**
     * @return bool false if the token does not exist or does not belong to the user
     */
    public function revoke(User $user, string $tokenId): bool
    {
        if (!Uuid::isValid($tokenId)) {
            return false;
        }

        $token = $this->em->find(ApiToken::class, $tokenId);
        if (null === $token || !$token->getUser()->getId()->equals($user->getId())) {
            return false;
        }

        $token->setRevokedAt(new \DateTimeImmutable());
        $this->em->flush();

        return true;
    }

    /**
     * @return list<array{id: string, name: string, createdAt: string, expiresAt: string}> active tokens (neither revoked nor expired), dates in 'Y-m-d H:i' format
     */
    public function listForUser(User $user): array
    {
        // UUID v7 values sort by creation date.
        $tokens = $this->apiTokenRepository->findBy(['user' => $user, 'revokedAt' => null], ['id' => 'ASC']);
        // Same rule as findUserByToken(): an expired token no longer opens the account.
        $now = new \DateTimeImmutable();
        $tokens = array_values(array_filter($tokens, static fn (ApiToken $token) => $token->getExpiresAt() > $now));

        return array_map(static fn (ApiToken $token) => [
            'id' => $token->getId()->toRfc4122(),
            'name' => $token->getName(),
            'createdAt' => $token->getCreatedAt()->format('Y-m-d H:i'),
            'expiresAt' => $token->getExpiresAt()->format('Y-m-d H:i'),
        ], $tokens);
    }
}
