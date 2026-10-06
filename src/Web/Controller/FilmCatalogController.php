<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Catalog\FilmCatalog;
use App\Catalog\FilmCatalogQuery;
use App\Catalog\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The films that can still be booked, with their selection and order in the URL.
 */
class FilmCatalogController extends AbstractController
{
    public function __construct(
        private FilmCatalog $filmCatalog,
        private CityRepository $cityRepository,
    ) {
    }

    #[Route('/films', name: 'app_films', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?FilmCatalogQuery $query = null): Response
    {
        $query ??= new FilmCatalogQuery();

        return $this->render('film/catalog.html.twig', $this->filmCatalog->browse($query, $user->getUserIdentifier(), $user->getPageSize()) + [
            'query' => $query,
            'cities' => $this->cityRepository->findAllForSelect(),
            'sorts' => FilmCatalogQuery::SORTS,
        ]);
    }
}
