<?php

namespace App\Account;

use App\Account\Entity\LinkedAccount;
use App\Account\Entity\User;
use App\Account\Repository\LinkedAccountRepository;
use App\Account\Repository\UserRepository;
use App\Security\UserInfo;
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
     */
    public function loginWithProvider(string $provider, UserInfo $userInfo): User
    {
        $email = $this->normalizeEmail($userInfo->email);
        $linked = $this->linkedAccountRepository->findOneByProviderIdentity($provider, $userInfo->id);
        if (null !== $linked) {
            return $linked->getUser();
        }

        $user = null;
        // Automatic linking only if the provider guarantees the email is verified.
        if ($userInfo->emailVerified && null !== $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
        }

        if (null === $user) {
            $user = new User();
            $user->setEmail($userInfo->emailVerified ? $email : null);
            $user->setDisplayName($userInfo->name ?? $email);
            $this->em->persist($user);
        }

        $user->addLinkedAccount($this->newLinkedAccount($provider, $userInfo, $email));
        $this->em->flush();

        return $user;
    }

    /**
     * Links an additional identity to the signed-in account.
     *
     * @return bool false if this identity already belongs to another account
     */
    public function linkProvider(User $user, string $provider, UserInfo $userInfo): bool
    {
        $email = $this->normalizeEmail($userInfo->email);
        $existing = $this->linkedAccountRepository->findOneByProviderIdentity($provider, $userInfo->id);
        if (null !== $existing) {
            return $existing->getUser()->getId()->equals($user->getId());
        }

        $user->addLinkedAccount($this->newLinkedAccount($provider, $userInfo, $email));
        if (null === $user->getEmail() && $userInfo->emailVerified && null !== $email
            && null === $this->userRepository->findOneBy(['email' => $email])) {
            $user->setEmail($email);
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

    /**
     * Emails are stored and compared in lower case (the column is case- and accent-sensitive):
     * "Ada@Example.org" is "ada@example.org", but "josé@" is never "jose@".
     */
    private function normalizeEmail(?string $email): ?string
    {
        return null === $email ? null : mb_strtolower($email);
    }

    public function changeTheme(User $user, Theme $theme): void
    {
        $user->setTheme($theme);
        $this->em->flush();
    }

    /**
     * @return bool false if the number is not one of FilmPage::PAGE_SIZES
     */
    public function changePageSize(User $user, int $pageSize): bool
    {
        if (!in_array($pageSize, FilmPage::PAGE_SIZES, true)) {
            return false;
        }
        $user->setPageSize($pageSize);
        $this->em->flush();

        return true;
    }

    private function newLinkedAccount(string $provider, UserInfo $userInfo, ?string $email): LinkedAccount
    {
        return (new LinkedAccount())
            ->setProvider($provider)
            ->setProviderUserId($userInfo->id)
            ->setEmail($email)
            ->setEmailVerified($userInfo->emailVerified);
    }
}
