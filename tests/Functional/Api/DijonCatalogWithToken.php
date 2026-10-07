<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Account\ApiTokenService;
use App\Account\Entity\User;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Pathé Dijon with four films of 100 minutes on January 10, 2030 (local times), a user and their API token.
 */
trait DijonCatalogWithToken
{
    use StoresEntities;

    private KernelBrowser $client;
    private User $user;
    private string $token;

    private function arrangeDijonCatalogWithToken(): void
    {
        $this->client = self::createClient();
        $dijon = CityBuilder::aCity()->build();
        $cinema = CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->named('Pathé Dijon')->in($dijon)->at(47.318031, 5.029935)->build();
        $entities = [$dijon, $cinema];
        foreach (['14:00' => 'f1', '16:30' => 'f2', '16:40' => 'f3', '19:00' => 'f4'] as $time => $slug) {
            $film = FilmBuilder::aFilm()->withSlug($slug)->titled('Film '.$slug)->lasting(100)->build();
            $entities[] = $film;
            $entities[] = ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt('2030-01-10 '.$time.':00')->build();
        }
        $this->user = UserBuilder::aUser()->build();
        $this->store($this->user, ...$entities);
        $this->token = self::getContainer()->get(ApiTokenService::class)->create($this->user, 'Tests');
    }

    private function api(string $method, string $uri, array $query = []): array
    {
        $this->client->request($method, $uri, $query, [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'HTTP_ACCEPT' => 'application/json']);
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
