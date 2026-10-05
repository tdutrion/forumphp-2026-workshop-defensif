<?php

namespace App\Tests\Functional\Web;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FilmHistoryTest extends WebTestCase
{
    use StoresEntities;

    private User $user;

    /**
     * A signed-in user who has seen $count films, f01 first (so f01 is the oldest of the history).
     */
    private function signedInHavingSeen(int $count): KernelBrowser
    {
        $client = self::createClient();
        $this->user = UserBuilder::aUser()->build();
        $films = [];
        for ($i = 1; $i <= $count; ++$i) {
            $films[] = FilmBuilder::aFilm()->withSlug(sprintf('f%02d', $i))->titled(sprintf('Film %02d', $i))->withSynopsis('Synopsis of film '.$i)->build();
        }
        $this->store($this->user, ...$films);
        foreach ($films as $film) {
            $this->seenFilms()->markSeen($this->user->getUserIdentifier(), $film->getSlug());
        }
        $client->loginUser($this->user);

        return $client;
    }

    private function seenFilms(): SeenFilmService
    {
        return self::getContainer()->get(SeenFilmService::class);
    }

    public function testTheHistoryIsPagedWithTheLatestFilmsFirst(): void
    {
        // Arrange
        $client = $this->signedInHavingSeen(25);

        // Act
        $firstPage = $client->request('GET', '/history')->filter('#film-history tbody tr');
        $secondPage = $client->request('GET', '/history?page=2')->filter('#film-history tbody tr');

        // Assert
        self::assertCount(20, $firstPage);
        self::assertStringContainsString('Film 25', $firstPage->first()->text());
        self::assertStringContainsString('Synopsis of film 25', $firstPage->first()->text());
        self::assertCount(5, $secondPage);
        self::assertStringContainsString('Film 01', $secondPage->last()->text());
    }

    public function testRemovingAFilmFromTheHistoryComesBackToTheSamePage(): void
    {
        // Arrange
        $client = $this->signedInHavingSeen(25);
        $crawler = $client->request('GET', '/history?page=2');

        // Act
        $client->submit($crawler->filter('#film-history [data-seen-film="f01"] form')->form());

        // Assert
        self::assertResponseRedirects('/history?page=2', 303);
        self::assertNotContains('f01', $this->seenFilms()->getSeenFilmSlugs($this->user->getUserIdentifier()));
    }

    public function testTheActionsOfARowAreShownWithALinkToTheFilm(): void
    {
        // Arrange
        $client = $this->signedInHavingSeen(1);

        // Act
        $row = $client->request('GET', '/history')->filter('#film-history tbody tr');

        // Assert
        self::assertCount(0, $row->filter('details'), 'no actions menu to open');
        self::assertCount(1, $row->filter('a[href="/films/f01"][aria-label] svg'), 'an icon links to the film page');
        self::assertCount(1, $row->filter('[data-seen-film="f01"] button'));
        self::assertCount(1, $row->filter('[data-unwanted-film="f01"] button'));
    }

    public function testAPageAfterTheLastOneShowsTheLastOne(): void
    {
        // Arrange
        $client = $this->signedInHavingSeen(3);

        // Act
        $client->request('GET', '/history?page=9');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(3, $client->getCrawler()->filter('#film-history tbody tr'));
    }

    public function testAPageThatIsNotANumberIsNotFound(): void
    {
        // Arrange
        $client = $this->signedInHavingSeen(1);

        // Act
        $client->request('GET', '/history?page=abc');

        // Assert
        self::assertResponseStatusCodeSame(404);
    }
}
