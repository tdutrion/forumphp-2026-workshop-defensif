<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Account\SeenFilmService;
use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use App\Catalog\WorkLinker;
use App\Catalog\WorkLinkStatus;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\Builder\WikidataApiBuilder;
use App\Tests\Builder\WorkBuilder;
use App\Tests\Fake\FakeWikidataApi;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkLinkerTest extends KernelTestCase
{
    use StoresEntities;

    private const string NOW = '2030-01-10 12:00:00';

    private function linker(WikidataApiBuilder $api): WorkLinker
    {
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve($api);

        return self::getContainer()->get(WorkLinker::class);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
    }

    private function reload(Work $work): Work
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->find(Work::class, $work->getId());
    }

    public function testASingleMatchingFilmLinksTheWork(): void
    {
        // Arrange
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q182153', 'Cars', 2006, ['John Lasseter'], 'tt0317219', '920')
            ->withFilm('Q1', 'Cars', 2006, ['Someone Else'])
            ->withFilm('Q2', 'Cars', 2006, ['John Lasseter'], type: 'Q5398426'));
        $work = WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->directedBy('John Lasseter')->build();
        $this->store($work);

        // Act
        $linked = $linker->link($work, $this->now());

        // Assert: Q1 has another director, Q2 is a TV series.
        $work = $this->reload($work);
        self::assertTrue($linked);
        self::assertSame(['Q182153', 'tt0317219', '920', WorkLinkStatus::Wikidata], [$work->getWikidataId(), $work->getImdbId(), $work->getTmdbId(), $work->getLinkStatus()]);
    }

    public function testAnAmbiguousOrEmptySearchLinksNothing(): void
    {
        // Arrange
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q10', 'Pressure', 2026, ['Anthony Maras'])
            ->withFilm('Q11', 'Pressure Point', 2026, ['Anthony Maras']));
        $ambiguous = WorkBuilder::aWork()->titled('Pressure')->releasedIn(2026)->directedBy('Anthony Maras')->build();
        $unknown = WorkBuilder::aWork()->titled('Primetime')->releasedIn(2026)->build();
        $this->store($ambiguous, $unknown);

        // Act
        $linker->link($ambiguous, $this->now());
        $linker->link($unknown, $this->now());

        // Assert
        self::assertNull($this->reload($ambiguous)->getWikidataId());
        self::assertSame(WorkLinkStatus::Unlinked, $this->reload($unknown)->getLinkStatus());
        self::assertEquals($this->now(), $this->reload($unknown)->getLinkAttemptedAt());
    }

    public function testAReReleaseIsLinkedThroughItsDirector(): void
    {
        // Arrange: Pathé dates the 2026 re-release of a 1987 film.
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()->withFilm('Q649589', 'Hellraiser', 1987, ['Clive Barker']));
        $work = WorkBuilder::aWork()->titled('Hellraiser')->releasedIn(2026)->directedBy('Clive Barker')->build();
        $this->store($work);

        // Act and assert
        self::assertTrue($linker->link($work, $this->now()));
    }

    public function testAnUnlinkedWorkIsRetriedAfterADayNotBefore(): void
    {
        // Arrange: the film is not in Wikidata yet (the kernel is booted once, before storing).
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi());
        $work = WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->directedBy('John Lasseter')->build();
        $this->store($work);
        $linker->linkDue([$work], $this->now());
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()->withFilm('Q182153', 'Cars', 2006, ['John Lasseter']));

        // Act (the answers of Wikidata are cached for a day too: the next day, the cache is empty)
        $sameDay = $linker->linkDue([$work], $this->now()->modify('+2 hours'));
        self::getContainer()->get('cache.wikidata')->clear();
        $nextDay = $linker->linkDue([$work], $this->now()->modify('+25 hours'));

        // Assert
        self::assertSame([0, 1], [$sameDay, $nextDay]);
    }

    public function testAManualLinkWinsAndIsNeverReplaced(): void
    {
        // Arrange
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q182153', 'Cars', 2006, ['John Lasseter'])
            ->withFilm('Q5', 'Cars: the series', 2006, [], type: 'Q5398426'));
        $work = WorkBuilder::aWork()->titled('Some French Title')->releasedIn(2006)->build();
        $this->store($work);

        // Act
        $series = $linker->linkManually($work, 'Q5');
        $film = $linker->linkManually($work, 'Q182153');
        $retried = $linker->linkDue([$work], $this->now()->modify('+30 days'));

        // Assert
        self::assertFalse($series, 'not a film');
        self::assertTrue($film);
        self::assertSame(0, $retried);
        self::assertSame(WorkLinkStatus::Manual, $this->reload($work)->getLinkStatus());
    }

    public function testNoMatchStopsTheAttempts(): void
    {
        // Arrange
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()->withFilm('Q182153', 'Cars', 2006, ['John Lasseter']));
        $work = WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->directedBy('John Lasseter')->build();
        $this->store($work);

        // Act
        $linker->markNoMatch($work);

        // Assert
        self::assertSame(0, $linker->linkDue([$work], $this->now()));
    }

    public function testTheSameFilmAtTwoChainsBecomesOneWorkWithItsMarks(): void
    {
        // Arrange: Pathé and another chain each have their film, each its work; a user saw both.
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi());
        $user = UserBuilder::aUser()->build();
        $pathe = FilmBuilder::aFilm()->withSlug('digger-51293')->titled('Digger')->build();
        $other = FilmBuilder::aFilm()->withSlug('other-digger')->titled('Digger')->build();
        $other->setChain('other');
        $this->store($user, $pathe, $other);
        $seen = self::getContainer()->get(SeenFilmService::class);
        $seen->markSeen($user->getUserIdentifier(), 'digger-51293');
        $seen->markSeen($user->getUserIdentifier(), 'other-digger');

        // Act: their film pages give the same original title, year and director.
        $linker->describe($pathe, 'Digger', 2026, ['Alejandro González Iñárritu']);
        $work = $linker->describe($other, 'Digger', 2026, ['Alejandro Gonzalez Inarritu']);

        // Assert
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertEquals($em->find(Film::class, 'digger-51293')->getWork()->getId(), $em->find(Film::class, 'other-digger')->getWork()->getId());
        self::assertSame(WorkLinkStatus::Fingerprint, $work->getLinkStatus());
        self::assertSame(1, $seen->pageOfSeenFilms($user->getUserIdentifier(), 1, 20)->total, 'one mark left');
    }

    public function testAWikidataIdAlreadyKnownMergesTheWorks(): void
    {
        // Arrange: another chain's film already linked to Q182153.
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()->withFilm('Q182153', 'Cars', 2006, ['John Lasseter']));
        $linked = WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->linkedTo('Q182153')->build();
        $other = FilmBuilder::aFilm()->withSlug('other-cars')->ofWork($linked)->build();
        $other->setChain('other');
        $pathe = FilmBuilder::aFilm()->withSlug('cars')->ofWork(WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->directedBy('John Lasseter')->build())->build();
        $this->store($other, $pathe);

        // Act
        $linker->link($pathe->getWork(), $this->now());

        // Assert
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame('Q182153', $em->find(Film::class, 'cars')->getWork()->getWikidataId());
        self::assertEquals($em->find(Film::class, 'cars')->getWork()->getId(), $em->find(Film::class, 'other-cars')->getWork()->getId());
    }

    public function testAnUnavailableWikidataLeavesTheWorkAsItIs(): void
    {
        // Arrange
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()->failing());
        $work = WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->directedBy('John Lasseter')->build();
        $this->store($work);

        // Act and assert
        self::assertFalse($linker->link($work, $this->now()));
        self::assertSame(WorkLinkStatus::Unlinked, $this->reload($work)->getLinkStatus());
    }

    public function testAFingerprintMergeKeepsTheLinkOfTheAbsorbedWork(): void
    {
        // Arrange: chain X's film (older work, directors unknown yet) and chain Y's film, linked by hand.
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi());
        $older = FilmBuilder::aFilm()->withSlug('digger-51293')->titled('Digger')->build();
        $linked = FilmBuilder::aFilm()->withSlug('other-digger')->ofWork(
            WorkBuilder::aWork()->titled('Digger')->releasedIn(2026)->directedBy('Alejandro González Iñárritu')->linkedByHandTo('Q129677718')->build(),
        )->build();
        $linked->setChain('other');
        $this->store($older, $linked);

        // Act: chain X's film page now gives the same title, year and director.
        $work = $linker->describe($older, 'Digger', 2026, ['Alejandro González Iñárritu']);

        // Assert
        self::assertSame(['Q129677718', WorkLinkStatus::Manual], [$work->getWikidataId(), $work->getLinkStatus()]);
    }

    public function testAnAutomaticLinkNeverDowngradesAManualOne(): void
    {
        // Arrange: a work linked by hand, and a new film of the same chain whose work Wikidata finds.
        $linker = $this->linker(WikidataApiBuilder::aWikidataApi()->withFilm('Q182153', 'Cars', 2006, ['John Lasseter']));
        $manual = FilmBuilder::aFilm()->withSlug('cars')->ofWork(WorkBuilder::aWork()->titled('Cars')->linkedByHandTo('Q182153')->build())->build();
        $preview = FilmBuilder::aFilm()->withSlug('cars-preview')->ofWork(WorkBuilder::aWork()->titled('Cars')->releasedIn(2006)->directedBy('John Lasseter')->build())->build();
        $this->store($manual, $preview);

        // Act
        $linker->link($preview->getWork(), $this->now());

        // Assert
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame(WorkLinkStatus::Manual, $em->find(Film::class, 'cars-preview')->getWork()->getLinkStatus());
    }
}
