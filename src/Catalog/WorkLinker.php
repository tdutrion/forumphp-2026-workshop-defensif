<?php

namespace App\Catalog;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use App\Catalog\Repository\WorkRepository;
use App\Sdk\Wikidata\WikidataClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Gives works their identity: description from the chain, same fingerprint at another chain,
 * Wikidata (CC0), or a link by hand. Never guesses: no link when zero or several films match.
 */
class WorkLinker
{
    /** Film and its kinds (feature, animated, documentary, short, silent, 3D, animated feature). */
    private const FILM_TYPES = ['Q11424', 'Q24869', 'Q202866', 'Q93204', 'Q24862', 'Q226730', 'Q229390', 'Q29168811'];

    public function __construct(
        private EntityManagerInterface $em,
        private WorkRepository $workRepository,
        private WorkMerger $workMerger,
        private WikidataClient $wikidata,
        private LoggerInterface $logger,
    ) {
    }

    public function describe(Film $film, string $originalTitle, ?int $year, ?array $directors): Work
    {
        $work = $film->getWork()->describe($originalTitle, $year, $directors);
        $this->em->flush();
        $fingerprint = $work->getFingerprint();
        $same = null === $fingerprint ? null : $this->workRepository->findOtherChainWorkByFingerprint($fingerprint, $work);
        if (null === $same) {
            return $work;
        }

        $merged = $this->workMerger->merge($same, $work);
        if (WorkLinkStatus::Unlinked === $merged->getLinkStatus()) {
            $merged->setLinkStatus(WorkLinkStatus::Fingerprint);
            $this->em->flush();
        }

        return $merged;
    }

    public function linkDue(array $works, \DateTimeImmutable $now): int
    {
        $linked = 0;
        foreach ($works as $work) {
            $attempted = $work->getLinkAttemptedAt();
            $due = in_array($work->getLinkStatus(), [WorkLinkStatus::Unlinked, WorkLinkStatus::Fingerprint], true)
                && (null === $attempted || $attempted <= $now->modify('-1 day'));
            if ($due && $this->link($work, $now)) {
                ++$linked;
            }
        }

        return $linked;
    }

    public function link(Work $work, \DateTimeImmutable $now): bool
    {
        $work->setLinkAttemptedAt($now);
        $this->em->flush();

        $ids = [];
        foreach (array_unique([$work->getOriginalTitle(), ...$this->workRepository->findFilmTitles($work)]) as $title) {
            foreach (['fr', 'en'] as $language) {
                $found = $this->wikidata->searchFilms($title, $language);
                if (false === $found) {
                    $this->logger->warning('Wikidata unavailable, work left as it is', ['work' => (string) $work->getId()]);

                    return false;
                }
                $ids = array_unique([...$ids, ...$found]);
            }
        }
        $films = $this->wikidata->getFilms($ids);
        if (false === $films) {
            return false;
        }

        $matches = array_filter($films, fn (array $film): bool => $this->matches($work, $film));
        if (1 !== count($matches)) {
            return false;
        }

        $this->attach($work, (string) array_key_first($matches), reset($matches), WorkLinkStatus::Wikidata);

        return true;
    }

    public function linkManually(Work $work, string $wikidataId): bool
    {
        $films = $this->wikidata->getFilms([$wikidataId]);
        if (false === $films || !isset($films[$wikidataId]) || [] === array_intersect($films[$wikidataId]['types'], self::FILM_TYPES)) {
            return false;
        }
        $this->attach($work, $wikidataId, $films[$wikidataId], WorkLinkStatus::Manual);

        return true;
    }

    public function markNoMatch(Work $work): void
    {
        $work->setLinkStatus(WorkLinkStatus::NoMatch);
        $this->em->flush();
    }

    /**
     * A film, released within a year of the chain's date or by the same director; when the chain
     * gives a director, one of the candidate's directors has the same surname.
     */
    private function matches(Work $work, array $film): bool
    {
        if ([] === array_intersect($film['types'], self::FILM_TYPES)) {
            return false;
        }
        $surnames = array_map([$this, 'surname'], $work->getDirectors() ?? []);
        $directorMatches = [] !== array_intersect($surnames, array_map([$this, 'surname'], $film['directors']));
        if ([] !== $surnames) {
            return $directorMatches;
        }
        foreach ($film['years'] as $year) {
            if (null !== $work->getYear() && abs($year - $work->getYear()) <= 1) {
                return true;
            }
        }

        return false;
    }

    private function surname(string $name): string
    {
        $words = preg_split('/\s+/', trim(strtolower((string) transliterator_transliterate('Any-Latin; Latin-ASCII', $name))));

        return (string) end($words);
    }

    private function attach(Work $work, string $wikidataId, array $film, WorkLinkStatus $status): void
    {
        $owner = $this->workRepository->findOneByWikidataId($wikidataId);
        if (null !== $owner && !$owner->getId()->equals($work->getId())) {
            $work = $this->workMerger->merge($owner, $work);
        }
        $work->setExternalIds($wikidataId, $film['imdbId'], $film['tmdbId'], $status);
        $this->em->flush();
    }
}
