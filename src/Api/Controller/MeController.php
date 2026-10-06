<?php

namespace App\Api\Controller;

use App\Account\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Profile')]
class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Profile of the token user')]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        $providers = [];
        foreach ($user->getLinkedAccounts() as $linkedAccount) {
            $providers[] = $linkedAccount->getProvider();
        }

        return new JsonResponse([
            'id' => $user->getUserIdentifier(),
            'displayName' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'providers' => $providers,
        ]);
    }
}
