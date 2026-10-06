<?php

namespace App\Tests\Integration\Account;

use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\Builder\WorkBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MarksOnWorksTest extends KernelTestCase
{
    use StoresEntities;

    public function testAFilmSeenAtOneChainIsSeenAtEveryChain(): void
    {
        // Arrange: the same work at Pathé and at another chain.
        self::bootKernel();
        $user = UserBuilder::aUser()->build();
        $work = WorkBuilder::aWork()->titled('Digger')->build();
        $this->store($user,
            FilmBuilder::aFilm()->withSlug('digger-51293')->ofWork($work)->build(),
            FilmBuilder::aFilm()->withSlug('other-digger')->ofWork($work)->build(),
        );
        $seen = self::getContainer()->get(SeenFilmService::class);
        $unwanted = self::getContainer()->get(UnwantedFilmService::class);

        // Act
        $seen->markSeen($user->getUserIdentifier(), 'digger-51293');
        $unwanted->markUnwanted($user->getUserIdentifier(), 'other-digger');

        // Assert
        $seenSlugs = $seen->getSeenFilmSlugs($user->getUserIdentifier());
        sort($seenSlugs);
        self::assertSame(['digger-51293', 'other-digger'], $seenSlugs);
        self::assertCount(1, $seen->pageOfSeenFilms($user->getUserIdentifier(), 1, 20)->films, 'one row per work');
        self::assertContains('digger-51293', $unwanted->getUnwantedFilmSlugs($user->getUserIdentifier()));
    }

    public function testUnmarkingFromAnyChainUnmarksTheWork(): void
    {
        // Arrange
        self::bootKernel();
        $user = UserBuilder::aUser()->build();
        $work = WorkBuilder::aWork()->build();
        $this->store($user,
            FilmBuilder::aFilm()->withSlug('digger-51293')->ofWork($work)->build(),
            FilmBuilder::aFilm()->withSlug('other-digger')->ofWork($work)->build(),
        );
        $seen = self::getContainer()->get(SeenFilmService::class);
        $seen->markSeen($user->getUserIdentifier(), 'digger-51293');

        // Act
        $seen->unmarkSeen($user->getUserIdentifier(), 'other-digger');

        // Assert
        self::assertSame([], $seen->getSeenFilmSlugs($user->getUserIdentifier()));
    }
}
