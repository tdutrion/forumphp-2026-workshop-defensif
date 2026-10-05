<?php

namespace App\Account;

use App\Account\Entity\LinkedAccount;
use App\Account\Entity\User;
use App\Account\Repository\LinkedAccountRepository;
use App\Account\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * User accounts and linked connections (Google, GitHub, local provider...).
 */
class AccountService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private LinkedAccountRepository $linkedAccountRepository,
    ) {
    }

    /**
     * Finds or creates the account matching an identity returned by a provider.
     *
     * @param array $userInfo ['id' => identifier at the provider, 'email' => ?string, 'emailVerified' => bool, 'name' => ?string]
     */
    public function loginWithProvider(string $provider, array $userInfo): User
    {
        $linked = $this->linkedAccountRepository->findOneByProviderIdentity($provider, $userInfo['id']);
        if (null !== $linked) {
            return $linked->getUser();
        }

        $user = null;
        // Automatic linking only if the provider guarantees the email is verified.
        if ($userInfo['emailVerified'] && null !== $userInfo['email']) {
            $user = $this->userRepository->findOneBy(['email' => $userInfo['email']]);
        }

        if (null === $user) {
            $user = new User();
            $user->setEmail($userInfo['emailVerified'] ? $userInfo['email'] : null);
            $user->setDisplayName($userInfo['name'] ?? $userInfo['email']);
            $this->em->persist($user);
        }

        $user->addLinkedAccount($this->newLinkedAccount($provider, $userInfo));
        $this->em->flush();

        return $user;
    }

    /**
     * Links an additional identity to the signed-in account.
     *
     * @return bool false if this identity already belongs to another account
     */
    public function linkProvider(User $user, string $provider, array $userInfo): bool
    {
        $existing = $this->linkedAccountRepository->findOneByProviderIdentity($provider, $userInfo['id']);
        if (null !== $existing) {
            return $existing->getUser()->getId()->equals($user->getId());
        }

        $user->addLinkedAccount($this->newLinkedAccount($provider, $userInfo));
        if (null === $user->getEmail() && $userInfo['emailVerified'] && null !== $userInfo['email']
            && null === $this->userRepository->findOneBy(['email' => $userInfo['email']])) {
            $user->setEmail($userInfo['email']);
        }
        $this->em->flush();

        return true;
    }

    /**
     * @return bool false if the connection does not belong to the user or if it is their last one
     */
    public function removeLinkedAccount(User $user, string $linkedAccountId): bool
    {
        if ($user->getLinkedAccounts()->count() <= 1) {
            return false;
        }

        foreach ($user->getLinkedAccounts() as $linkedAccount) {
            if ($linkedAccount->getId()->toRfc4122() === $linkedAccountId) {
                $user->removeLinkedAccount($linkedAccount);
                $this->em->flush();

                return true;
            }
        }

        return false;
    }

    public function changeTheme(User $user, Theme $theme): void
    {
        $user->setTheme($theme);
        $this->em->flush();
    }

    private function newLinkedAccount(string $provider, array $userInfo): LinkedAccount
    {
        return (new LinkedAccount())
            ->setProvider($provider)
            ->setProviderUserId($userInfo['id'])
            ->setEmail($userInfo['email'])
            ->setEmailVerified($userInfo['emailVerified']);
    }
}
