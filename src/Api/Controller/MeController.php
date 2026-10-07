<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Api\Response\ProfileResource;
use App\Api\Response\Responder;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Profile')]
class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Profile of the token user', content: new Model(type: ProfileResource::class))]
    public function show(#[CurrentUser] User $user, Responder $responder): JsonResponse
    {
        return $responder->json(ProfileResource::fromUser($user));
    }
}
