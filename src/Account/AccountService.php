<?php

namespace App\Account;

use App\Account\Entity\User;
use App\Account\Repository\LinkedAccountRepository;
use App\Account\Repository\UserRepository;
use App\Security\UserInfo;
use App\Security\VerifiedEmail;
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
        $linked = $this->linkedAccountRepository->findOneByProviderIdentity($provider, $userInfo->id);
        if (null !== $linked) {
            return $linked->getUser();
        }

        // Automatic linking only if the provider guarantees the email is verified.
        $user = $userInfo->email instanceof VerifiedEmail ? $this->userRepository->findOneBy(['email' => $userInfo->email->value]) : null;
        if (null === $user) {
            $user = User::fromProviderIdentity($provider, $userInfo);
            $this->em->persist($user);
        } else {
            $user->connect($provider, $userInfo);
        }
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
        $existing = $this->linkedAccountRepository->findOneByProviderIdentity($provider, $userInfo->id);
        if (null !== $existing) {
            return $existing->getUser()->getId()->equals($user->getId());
        }

        $user->connect($provider, $userInfo);
        if ($userInfo->email instanceof VerifiedEmail && null === $this->userRepository->findOneBy(['email' => $userInfo->email->value])) {
            $user->adoptEmail($userInfo->email);
        }
        $this->em->flush();

        return true;
    }

    /**
     * @return bool false if the connection does not belong to the user or if it is their last one
     */
    public function removeLinkedAccount(User $user, string $linkedAccountId): bool
    {
        foreach ($user->getLinkedAccounts() as $linkedAccount) {
            if ($linkedAccount->getId()->toRfc4122() !== $linkedAccountId) {
                continue;
            }

            try {
                $user->disconnect($linkedAccount);
            } catch (LastConnection) {
                return false;
            }
            $this->em->flush();

            return true;
        }

        return false;
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
}
