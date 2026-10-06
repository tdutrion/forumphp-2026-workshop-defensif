# Film works and Wikidata Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every film a work, common to every cinema chain, linked to Wikidata when possible, so that seen / not for me and programmes hold across chains.

**Architecture:** A `Work` entity (UUID, original title, year, directors, fingerprint, external ids, link status) owned by every `Film`. Seen and unwanted rows reference the work; the planner keeps one showtime per work. A Wikidata SDK (PSR-18, like the Pathé one) feeds a `WorkLinker` at synchronization time; a `WorkMerger` merges works that turn out to be the same (same Wikidata id, or same fingerprint across chains). A console command links by hand.

**Tech Stack:** Symfony 8.1, PHP 8.5, Doctrine ORM 3 / DBAL, MySQL 8.4, PSR-18/17/6/3, PHPUnit 13 (dama transactions), Twig.

**Spec:** `docs/superpowers/specs/2026-10-05-film-works-wikidata-design.md`

## Global Constraints

- Everything produced is in English (code, docs, commit messages); the UI goes through the Symfony Translator (English and French catalogs, semantic keys).
- No test reaches the network: `FakeWikidataApi` replaces Wikidata like `FakePatheApi` replaces Pathé.
- Tests: arrange-act-assert, builders for the collaborators, functional behaviour only (no implementation details), as few as needed.
- Workshop contract style: repositories and services return arrays and `false|result`, as the existing code does.
- Linking only happens at synchronization time; the application never calls Wikidata while serving a page.
- A Wikidata failure never fails the Pathé synchronization.
- External ids end up in `href` attributes: only `Q\d+`, `tt\d+` and `\d+` (TMDB) are stored.
- Commits signed, author Thomas Dutrion, no Co-Authored-By trailer; push only when asked.
- Commands run through the Makefile (`make test c="--filter X"`, `make phpstan`, `make cs`); migrations through `bin/console make:migration` then `doctrine:migrations:migrate`.

## Review Focus

- A Pathé title with quotes, accents or punctuation (`L'Odyssée`, `Détective Conan: …`) must search and fingerprint without errors (normalization), not break a query.
- Wikidata answering garbage (HTML, empty JSON, missing `entities`) must leave the work unlinked, like an error.
- A film whose work has no directors known (`null`) must not get a fingerprint, so two different films with the same title never merge.
- Merging two works whose users both marked them seen must keep one row per user, not fail on the unique key.
- An unknown or malformed work id on `/api/works/{id}` must be a 404, not a 500.

---

## File structure

| File | Responsibility |
|---|---|
| `src/Catalog/Entity/Work.php` (new) | The work: identity, description, fingerprint, external ids, link status |
| `src/Catalog/WorkLinkStatus.php` (new) | Enum: unlinked, wikidata, fingerprint, manual, no_match |
| `src/Catalog/Repository/WorkRepository.php` (new) | Lookups by Wikidata id and fingerprint, titles of a work's films, due works |
| `src/Catalog/WorkMerger.php` (new) | Merges two works (films, seen, unwanted) |
| `src/Catalog/WorkLinker.php` (new) | Describes works from Pathé details, fingerprint merges, Wikidata linking, manual linking |
| `src/Sdk/Wikidata/WikidataClient.php` (new) | Only access point to the Wikidata action API |
| `src/Sdk/Wikidata/WikidataMapper.php` (new) | Turns raw Wikidata answers into arrays |
| `src/Catalog/Command/WorkLinkCommand.php` (new) | `work:link` |
| `src/Api/Controller/WorkController.php` (new) | `GET /api/works/{id}` |
| `src/Catalog/Entity/Film.php` | Gains `work` |
| `src/Account/Entity/SeenFilm.php`, `UnwantedFilm.php` | Reference the work |
| `src/Account/Repository/SeenFilmRepository.php`, `UnwantedFilmRepository.php` | Queries by work |
| `src/Account/SeenFilmService.php`, `UnwantedFilmService.php` | Mark and list by work |
| `src/Catalog/Repository/ShowtimeRepository.php`, `src/Planner/ChainBuilder.php`, `src/Planner/ProgrammeSelector.php` | One showtime per work |
| `src/Catalog/Repository/FilmRepository.php` | Film rows carry the work and its ids |
| `src/Catalog/Sync/CatalogSynchronizer.php` | Describes and links works |
| `src/Sdk/Pathe/PatheMapper.php` | `mapFilmDetails()` |
| `templates/film/show.html.twig`, `src/Api/Controller/FilmController.php` | External links, `work` in the API |
| `tests/Fake/FakeWikidataApi.php`, `tests/Builder/WikidataApiBuilder.php`, `tests/Builder/WorkBuilder.php` (new) | Test doubles and builders |
| `Makefile`, `README.md`, `docs/self-hosting.md` | Dump includes works |

---

### Task 1: Works exist and every film has one

**Files:**
- Create: `src/Catalog/WorkLinkStatus.php`, `src/Catalog/Entity/Work.php`, `src/Catalog/Repository/WorkRepository.php`, `tests/Builder/WorkBuilder.php`, `tests/Unit/Catalog/WorkFingerprintTest.php`, `migrations/Version<generated>.php`
- Modify: `src/Catalog/Entity/Film.php`, `src/Catalog/Sync/CatalogSynchronizer.php:94-112`, `tests/Builder/FilmBuilder.php`, `tests/Integration/Catalog/Sync/CatalogSyncTest.php`

**Interfaces:**
- Produces: `Work::fingerprintOf(?string $title, ?int $year, ?array $directors): ?string`; `Work::describe(string $originalTitle, ?int $year, ?array $directors): static` (sets the fingerprint); getters/setters `getId(): Uuid`, `getOriginalTitle()`, `getYear()`, `getDirectors(): ?array`, `getFingerprint()`, `getWikidataId()`, `getImdbId()`, `getTmdbId()`, `getLinkStatus(): WorkLinkStatus`, `getLinkAttemptedAt()`, `getCreatedAt()`, `setExternalIds(string $wikidataId, ?string $imdbId, ?string $tmdbId, WorkLinkStatus $status): static`, `setLinkStatus()`, `setLinkAttemptedAt()`; `Film::getWork(): Work`, `Film::setWork(Work): static`; `WorkBuilder::aWork()->titled()->releasedIn()->directedBy(string ...)->linkedTo(string $q, ?string $imdb = null, ?string $tmdb = null)->build()`; `FilmBuilder::ofWork(Work)`.

- [ ] **Step 1: Write the failing unit test of the fingerprint**

```php
<?php

namespace App\Tests\Unit\Catalog;

use App\Catalog\Entity\Work;
use PHPUnit\Framework\TestCase;

final class WorkFingerprintTest extends TestCase
{
    public function testTheSameFilmGivesTheSameFingerprintWhateverItsSpelling(): void
    {
        // Arrange
        $pathe = Work::fingerprintOf("L'Odyssée", 2026, ['Christopher Nolan']);

        // Act
        $other = Work::fingerprintOf('l odyssee !', 2026, ['christopher  NOLAN']);

        // Assert
        self::assertSame('l odyssee|2026|nolan', $pathe);
        self::assertSame($pathe, $other);
    }

    public function testNoFingerprintWithoutTitleYearAndDirector(): void
    {
        // Act and assert: a part unknown, no fingerprint (two different films must never merge).
        self::assertNull(Work::fingerprintOf('Cars', null, ['John Lasseter']));
        self::assertNull(Work::fingerprintOf('Cars', 2006, null));
        self::assertNull(Work::fingerprintOf('Cars', 2006, []));
        self::assertNull(Work::fingerprintOf('  ', 2006, ['John Lasseter']));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make test c="--filter WorkFingerprintTest"`
Expected: FAIL, `Class "App\Catalog\Entity\Work" not found`.

- [ ] **Step 3: Write the enum, the entity and its repository**

`src/Catalog/WorkLinkStatus.php`:

```php
<?php

namespace App\Catalog;

/**
 * How a work got its identity: not linked yet, found in Wikidata, merged by fingerprint with the
 * same film of another chain, linked by hand, or declared without match (no more attempts).
 */
enum WorkLinkStatus: string
{
    case Unlinked = 'unlinked';
    case Wikidata = 'wikidata';
    case Fingerprint = 'fingerprint';
    case Manual = 'manual';
    case NoMatch = 'no_match';
}
```

`src/Catalog/Entity/Work.php`:

```php
<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\WorkRepository;
use App\Catalog\WorkLinkStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A film as a work, common to every cinema chain: each chain's Film points to its work.
 * Linked to Wikidata (CC0) when it can be found; its id is the common identifier anyway.
 */
#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Index(columns: ['fingerprint'])]
class Work
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $originalTitle = '';

    /** Original release year; null when unknown. */
    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    /** @var array|null names of the directors; null = not read yet, [] = none given */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $directors = null;

    /** Normalized title, year and surname of the first director (see fingerprintOf()). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fingerprint = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $wikidataId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $imdbId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tmdbId = null;

    #[ORM\Column(length: 16, enumType: WorkLinkStatus::class)]
    private WorkLinkStatus $linkStatus = WorkLinkStatus::Unlinked;

    /** Last automatic attempt to link the work (UTC). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $linkAttemptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Same film, same fingerprint, whatever the chain: lower case, accents removed, every run of
     * characters other than letters and digits becomes one space; then the year and the surname
     * (last word) of the first director. Null when a part is unknown: never merge on a guess.
     */
    public static function fingerprintOf(?string $title, ?int $year, ?array $directors): ?string
    {
        $normalize = static fn (string $text): string => trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(
            (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text),
        )));
        $title = null === $title ? '' : $normalize($title);
        $director = null === $directors || [] === $directors ? '' : $normalize((string) reset($directors));
        if ('' === $title || null === $year || '' === $director) {
            return null;
        }
        $surname = substr($director, (int) strrpos(' '.$director, ' '));

        return $title.'|'.$year.'|'.trim($surname);
    }

    public function describe(string $originalTitle, ?int $year, ?array $directors): static
    {
        $this->originalTitle = $originalTitle;
        $this->year = $year;
        $this->directors = $directors;
        $this->fingerprint = self::fingerprintOf($originalTitle, $year, $directors);

        return $this;
    }

    public function setExternalIds(string $wikidataId, ?string $imdbId, ?string $tmdbId, WorkLinkStatus $status): static
    {
        $this->wikidataId = $wikidataId;
        $this->imdbId = $imdbId;
        $this->tmdbId = $tmdbId;
        $this->linkStatus = $status;

        return $this;
    }

    public function getId(): Uuid { return $this->id; }
    public function getOriginalTitle(): string { return $this->originalTitle; }
    public function getYear(): ?int { return $this->year; }
    public function getDirectors(): ?array { return $this->directors; }
    public function getFingerprint(): ?string { return $this->fingerprint; }
    public function getWikidataId(): ?string { return $this->wikidataId; }
    public function getImdbId(): ?string { return $this->imdbId; }
    public function getTmdbId(): ?string { return $this->tmdbId; }
    public function getLinkStatus(): WorkLinkStatus { return $this->linkStatus; }
    public function getLinkAttemptedAt(): ?\DateTimeImmutable { return $this->linkAttemptedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setLinkStatus(WorkLinkStatus $linkStatus): static
    {
        $this->linkStatus = $linkStatus;

        return $this;
    }

    public function setLinkAttemptedAt(?\DateTimeImmutable $linkAttemptedAt): static
    {
        $this->linkAttemptedAt = $linkAttemptedAt;

        return $this;
    }
}
```

(Format the one-line getters on several lines, as `make cs` will.)

`src/Catalog/Repository/WorkRepository.php`:

```php
<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Work>
 */
class WorkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Work::class);
    }
}
```

- [ ] **Step 4: Run the unit test to verify it passes**

Run: `make test c="--filter WorkFingerprintTest"`
Expected: PASS (2 tests).

- [ ] **Step 5: Write the failing integration test: a synchronized film has a work**

Add to `tests/Integration/Catalog/Sync/CatalogSyncTest.php`:

```php
    public function testEverySynchronizedFilmHasItsOwnWork(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $this->synchronize($this->dijon());

        // Assert
        $this->em()->clear();
        $digger = $this->em()->find(Film::class, 'digger-51293');
        $verity = $this->em()->find(Film::class, 'verity-50815');
        self::assertSame('Digger', $digger->getWork()->getOriginalTitle());
        self::assertNotEquals($digger->getWork()->getId(), $verity->getWork()->getId());
    }
```

- [ ] **Step 6: Run it to verify it fails**

Run: `make test c="--filter testEverySynchronizedFilmHasItsOwnWork"`
Expected: FAIL, `Call to undefined method App\Catalog\Entity\Film::getWork()`.

- [ ] **Step 7: Give every film a work**

In `src/Catalog/Entity/Film.php`, add the relation (cascade persist: a new film brings its new work):

```php
    /** The work this chain's film shows, common to every chain. */
    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Work $work = null;

    public function getWork(): Work
    {
        return $this->work ?? throw new \LogicException('A film always has a work.');
    }

    public function setWork(Work $work): static
    {
        $this->work = $work;

        return $this;
    }
```

In `CatalogSynchronizer::synchronize()`, where a film is created, give a new film a new work described from the listing (directors unknown until the film page is read):

```php
            $film = $this->em->find(Film::class, $data['slug']);
            if (null === $film) {
                $year = null !== $data['releaseDate'] ? (int) substr($data['releaseDate'], 0, 4) : null;
                $film = (new Film())->setSlug($data['slug'])->setWork((new Work())->describe($data['title'], $year, null));
            }
```

In `tests/Builder/FilmBuilder.php`: add `private ?Work $work = null;`, `ofWork(Work $work): self` (clone, set), and in `build()`: `->setWork($this->work ?? WorkBuilder::aWork()->titled($this->title ?? 'Film '.$this->slug)->build())`.

`tests/Builder/WorkBuilder.php`:

```php
<?php

namespace App\Tests\Builder;

use App\Catalog\Entity\Work;
use App\Catalog\WorkLinkStatus;

final class WorkBuilder
{
    private string $title = 'Digger';
    private ?int $year = 2026;
    private ?array $directors = null;
    private ?array $link = null;

    public static function aWork(): self
    {
        return new self();
    }

    public function titled(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function releasedIn(?int $year): self
    {
        $clone = clone $this;
        $clone->year = $year;

        return $clone;
    }

    public function directedBy(string ...$directors): self
    {
        $clone = clone $this;
        $clone->directors = $directors;

        return $clone;
    }

    public function linkedTo(string $wikidataId, ?string $imdbId = null, ?string $tmdbId = null): self
    {
        $clone = clone $this;
        $clone->link = [$wikidataId, $imdbId, $tmdbId];

        return $clone;
    }

    public function build(): Work
    {
        $work = (new Work())->describe($this->title, $this->year, $this->directors);
        if (null !== $this->link) {
            $work->setExternalIds($this->link[0], $this->link[1], $this->link[2], WorkLinkStatus::Wikidata);
        }

        return $work;
    }
}
```

- [ ] **Step 8: Generate the migration, then add the backfill of existing films**

Run: `docker compose exec -T php bin/console make:migration --no-interaction`

Edit the generated file: description `'Works: every film gets one'`. Doctrine generates `CREATE TABLE work …` and `ALTER TABLE film ADD work_id BINARY(16) NOT NULL` plus the foreign key. Change it to add the column nullable first, fill it, then make it mandatory — `up()` becomes, in this order (keep the generated `CREATE TABLE work` statement and the generated names of the index and foreign key):

```php
        $this->addSql('CREATE TABLE work (…generated…)');
        $this->addSql('ALTER TABLE film ADD work_id BINARY(16) DEFAULT NULL');
        // One work per existing film, described from the film (directors unknown: read at the next sync).
        $this->addSql('UPDATE film SET work_id = UUID_TO_BIN(UUID(), 1)');
        $this->addSql("INSERT INTO work (id, original_title, year, directors, fingerprint, wikidata_id, imdb_id, tmdb_id, link_status, link_attempted_at, created_at)
            SELECT work_id, title, YEAR(release_date), NULL, NULL, NULL, NULL, NULL, 'unlinked', NULL, UTC_TIMESTAMP() FROM film");
        $this->addSql('ALTER TABLE film MODIFY work_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE film ADD CONSTRAINT FK_…generated… FOREIGN KEY (work_id) REFERENCES work (id)');
        $this->addSql('CREATE INDEX IDX_…generated… ON film (work_id)');
```

- [ ] **Step 9: Migrate the dev database and check the backfill by hand**

(The spec lists the migration among the tests: migrations run on an empty test database, so the conversion is checked by hand on the dev data, here and in Task 2.)

Run:
```bash
docker compose exec -T php bin/console doctrine:migrations:migrate -n
docker compose exec -T database sh -c 'mysql -N -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT (SELECT COUNT(*) FROM film), (SELECT COUNT(*) FROM work), (SELECT COUNT(*) FROM film WHERE work_id IS NULL)"'
```
Expected: the first two numbers are equal, the third is 0.

- [ ] **Step 10: Run the whole suite, PHPStan, code style**

Run: `make test`, then `make phpstan`, then `make cs`
Expected: all green (every builder-made film now has a work).

- [ ] **Step 11: Commit**

```bash
git add src/Catalog/WorkLinkStatus.php src/Catalog/Entity/Work.php src/Catalog/Entity/Film.php src/Catalog/Repository/WorkRepository.php src/Catalog/Sync/CatalogSynchronizer.php migrations tests
git commit -m "feat(catalog): every film has a work, common to every chain"
```

---

### Task 2: Seen and not for me hold for the work

**Files:**
- Modify: `src/Account/Entity/SeenFilm.php`, `src/Account/Entity/UnwantedFilm.php`, `src/Account/Repository/SeenFilmRepository.php`, `src/Account/Repository/UnwantedFilmRepository.php`, `src/Account/SeenFilmService.php`, `src/Account/UnwantedFilmService.php`
- Create: `migrations/Version<generated>.php`, `tests/Integration/Account/MarksOnWorksTest.php`

**Interfaces:**
- Consumes: `Film::getWork()`, `FilmBuilder::ofWork()`, `WorkBuilder`.
- Produces (signatures unchanged for callers): `SeenFilmService::markSeen(string $userId, string $filmSlug): bool`, `unmarkSeen(string $userId, string $filmSlug): void`, `getSeenFilmSlugs(string $userId): array` (**now: slugs of every film, any chain, whose work is seen**), `pageOfSeenFilms()` (one row per work); same for `UnwantedFilmService`.

- [ ] **Step 1: Write the failing integration test**

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make test c="--filter MarksOnWorksTest"`
Expected: FAIL (only `digger-51293` is seen; the other chain's film is not).

- [ ] **Step 3: Reference the work in the entities**

In `SeenFilm` (and `UnwantedFilm`, same change): the unique constraint becomes `#[ORM\UniqueConstraint(columns: ['user_id', 'work_id'])]`; replace the `$film` relation by:

```php
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Work $work = null;
```

with `getWork(): ?Work` / `setWork(Work $work): static` in place of the film accessors (remove `getFilm`/`setFilm`; nothing else uses them — check with `grep -rn "getFilm\|setFilm" src`).

- [ ] **Step 4: Query by work in the repositories**

`SeenFilmRepository` (the unwanted one is the same with `u`, `unwanted_film` and `created_at`):

```php
    /**
     * @return array slugs of every film, any chain, whose work the user has seen (latest first)
     */
    public function findFilmSlugsByUser(string $userId): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('f.slug')
            ->from(SeenFilm::class, 's')
            ->join(Film::class, 'f', 'WITH', 'f.work = s.work')
            ->where('s.user = :user')
            ->setParameter('user', $userId, 'uuid')
            ->orderBy('s.seenAt', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findOneByUserAndWork(string $userId, Work $work): ?SeenFilm
    {
        return $this->findOneBy(['user' => Uuid::fromString($userId), 'work' => $work]);
    }

    /**
     * @return array rows 'slug', 'title', 'posterUrl', 'synopsis', 'markedAt': one per work (its first film), latest first
     */
    public function findPageByUser(string $userId, int $offset, int $limit): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT slug, title, posterUrl, synopsis, markedAt FROM (
                 SELECT f.slug, f.title, f.poster_url AS posterUrl, f.synopsis, s.seen_at AS markedAt, s.id AS markId,
                        ROW_NUMBER() OVER (PARTITION BY s.id ORDER BY f.slug) AS position
                 FROM seen_film s INNER JOIN film f ON f.work_id = s.work_id
                 WHERE s.user_id = :user
             ) marks
             WHERE position = 1
             ORDER BY markedAt DESC, markId DESC
             LIMIT '.$limit.' OFFSET '.$offset,
            ['user' => Uuid::fromString($userId)->toBinary()],
        );
    }
```

`countByUser()` is unchanged (one row per work). Remove `findOneByUserAndFilm()`.

- [ ] **Step 5: Mark and unmark the work in the services**

`SeenFilmService` (same for `UnwantedFilmService`):

```php
    public function markSeen(string $userId, string $filmSlug): bool
    {
        $film = $this->em->find(Film::class, $filmSlug);
        if (null === $film) {
            return false;
        }

        // INSERT IGNORE: two clicks at the same time must not hit the (user, work) unique key.
        $this->em->getConnection()->executeStatement(
            'INSERT IGNORE INTO seen_film (id, user_id, work_id, seen_at) VALUES (:id, :user, :work, :seenAt)',
            [
                'id' => Uuid::v7()->toBinary(),
                'user' => Uuid::fromString($userId)->toBinary(),
                'work' => $film->getWork()->getId()->toBinary(),
                'seenAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ],
        );

        return true;
    }

    public function unmarkSeen(string $userId, string $filmSlug): void
    {
        $film = $this->em->find(Film::class, $filmSlug);
        $seenFilm = null === $film ? null : $this->seenFilmRepository->findOneByUserAndWork($userId, $film->getWork());
        if (null !== $seenFilm) {
            $this->em->remove($seenFilm);
            $this->em->flush();
        }
    }
```

- [ ] **Step 6: Generate the migration, then move the existing marks to the works**

Run: `docker compose exec -T php bin/console make:migration --no-interaction`

Description: `'Seen and unwanted films reference the work'`. Reorder `up()` so that, for each of `seen_film` (date `seen_at`) and `unwanted_film` (date `created_at`): add `work_id BINARY(16) DEFAULT NULL`; fill it; drop duplicates (keep the oldest); then drop the old foreign key, unique index and `film_slug`, make `work_id` mandatory and add the generated foreign key and unique index:

```php
        $this->addSql('ALTER TABLE seen_film ADD work_id BINARY(16) DEFAULT NULL');
        $this->addSql('UPDATE seen_film s INNER JOIN film f ON f.slug = s.film_slug SET s.work_id = f.work_id');
        // One mark per user and work: the oldest one stays.
        $this->addSql('DELETE a FROM seen_film a INNER JOIN seen_film b ON a.user_id = b.user_id AND a.work_id = b.work_id
            AND (a.seen_at > b.seen_at OR (a.seen_at = b.seen_at AND a.id > b.id))');
        // …generated: DROP FOREIGN KEY on film_slug, DROP INDEX (user_id, film_slug), DROP film_slug…
        $this->addSql('ALTER TABLE seen_film MODIFY work_id BINARY(16) NOT NULL');
        // …generated: ADD CONSTRAINT FK on work_id ON DELETE CASCADE, CREATE UNIQUE INDEX (user_id, work_id), CREATE INDEX (work_id)…
```

- [ ] **Step 7: Migrate the dev database and check the marks by hand**

Before migrating, note `SELECT COUNT(*) FROM seen_film` and `SELECT COUNT(*) FROM unwanted_film`; migrate; run them again.
Expected: same counts (each Pathé film has its own work, so no duplicate is dropped), and `SELECT COUNT(*) FROM seen_film WHERE work_id IS NULL` is 0.

- [ ] **Step 8: Run the test, then the whole suite**

Run: `make test c="--filter MarksOnWorksTest"` then `make test`
Expected: PASS, and the whole suite green (history, unwanted list, film catalog and planner tests keep their behaviour).

- [ ] **Step 9: Commit**

```bash
git add src/Account migrations tests/Integration/Account/MarksOnWorksTest.php
git commit -m "feat(account): already seen and not for me hold for the work, every chain"
```

---

### Task 3: A programme never offers the same work twice

**Files:**
- Modify: `src/Catalog/Repository/ShowtimeRepository.php:38-41`, `src/Planner/ChainBuilder.php:30-80`, `src/Planner/ProgrammeSelector.php:37`, `tests/Builder/ScreeningBuilder.php`, `tests/Builder/ProgrammeBuilder.php`, `tests/Unit/Planner/ChainBuilderTest.php`

**Interfaces:**
- Produces: candidate rows of `ShowtimeRepository::findCandidates()` carry `workId` (hexadecimal string); `ChainBuilder` and `ProgrammeSelector` compare `workId`; `ScreeningBuilder::ofWork(string $workId)` (defaults to the film slug).

- [ ] **Step 1: Write the failing unit test**

Add to `tests/Unit/Planner/ChainBuilderTest.php` (use the existing builder style of the file):

```php
    public function testTwoChainsShowingTheSameWorkNeverFillOneProgramme(): void
    {
        // Arrange: the same work, at Pathé at 14:00 and at another chain at 17:00.
        $showtimes = [
            ScreeningBuilder::aScreening('a')->ofFilm('digger-51293')->ofWork('W1')->inCinema('cinema-pathe-dijon', 47.32, 5.03)->startingAt('14:00')->lasting(100)->build(),
            ScreeningBuilder::aScreening('b')->ofFilm('other-digger')->ofWork('W1')->inCinema('other-dijon', 47.32, 5.03)->startingAt('17:00')->lasting(100)->build(),
        ];

        // Act
        $programmes = (new ChainBuilder())->build($showtimes, 2, false);

        // Assert
        self::assertSame([], $programmes);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make test c="--filter testTwoChainsShowingTheSameWork"`
Expected: FAIL (`Call to undefined method ofWork()`, then one programme found once added).

- [ ] **Step 3: Implement**

`ScreeningBuilder`: add `private ?string $work = null;`, `ofWork(string $workId): self`, and `'workId' => $this->work ?? $this->film,` in `build()`. `ProgrammeBuilder::build()`: each showtime row gets `'workId' => $slug` next to `'filmSlug' => $slug`.

`ShowtimeRepository::findCandidates()`: add `HEX(f.work_id) AS workId` to the select list and to the `@return` doc.

`ChainBuilder`: the docblock lists `workId`; in `explore()`:

```php
        // One showtime per work: the same film shown by two chains is still one film.
        $works = array_column($path, 'workId');
        …
            if (in_array($candidate['workId'], $works, true) || !$this->canChain($last, $candidate, $acceptAds, $travelMode)) {
```

`ProgrammeSelector::select()`: `$films = array_column($programme['showtimes'], 'workId');` (rename the variable `$works`, the key stays a set of works).

- [ ] **Step 4: Run the planner tests, then the whole suite**

Run: `make test c="--filter 'ChainBuilderTest|ProgrammeSelectorTest|PlannerServiceTest|PlannerPageTest|PlanApiTest'"` then `make test`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Catalog/Repository/ShowtimeRepository.php src/Planner tests/Builder tests/Unit/Planner
git commit -m "feat(planner): a programme never offers the same work twice"
```

---

### Task 4: Wikidata SDK and its fake

**Files:**
- Create: `src/Sdk/Wikidata/WikidataClient.php`, `src/Sdk/Wikidata/WikidataMapper.php`, `tests/Fake/FakeWikidataApi.php`, `tests/Builder/WikidataApiBuilder.php`, `tests/Integration/Sdk/WikidataClientTest.php`
- Modify: `config/packages/framework.yaml`, `config/services.yaml`

**Interfaces:**
- Produces:
  - `WikidataClient::searchFilms(string $title, string $language): array|false` — item ids (`Q…`), at most 10.
  - `WikidataClient::getFilms(array $ids): array|false` — `[id => ['types' => list of Q ids, 'years' => list of int, 'directors' => list of labels, 'imdbId' => ?string, 'tmdbId' => ?string]]` (directors resolved to their English labels, falling back to French).
  - `WikidataApiBuilder::aWikidataApi()->withFilm(string $id, string $label, ?int $year, array $directors = [], ?string $imdbId = null, ?string $tmdbId = null, string $type = 'Q11424')->failing()->answeringGarbage()`.
  - `FakeWikidataApi::serve(WikidataApiBuilder $api): void`.

- [ ] **Step 1: Write the failing integration test**

```php
<?php

namespace App\Tests\Integration\Sdk;

use App\Sdk\Wikidata\WikidataClient;
use App\Tests\Builder\WikidataApiBuilder;
use App\Tests\Fake\FakeWikidataApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WikidataClientTest extends KernelTestCase
{
    private function client(WikidataApiBuilder $api): WikidataClient
    {
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve($api);

        return self::getContainer()->get(WikidataClient::class);
    }

    public function testFindsAFilmAndReadsItsFacts(): void
    {
        // Arrange
        $client = $this->client(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q182153', 'Cars', 2006, ['John Lasseter'], 'tt0317219', '920'));

        // Act
        $ids = $client->searchFilms('cars', 'fr');
        $films = $client->getFilms($ids);

        // Assert
        self::assertSame(['Q182153'], $ids);
        self::assertSame(['types' => ['Q11424'], 'years' => [2006], 'directors' => ['John Lasseter'], 'imdbId' => 'tt0317219', 'tmdbId' => '920'], $films['Q182153']);
    }

    public function testAnUnavailableWikidataGivesFalse(): void
    {
        // Arrange
        $client = $this->client(WikidataApiBuilder::aWikidataApi()->failing());

        // Act and assert
        self::assertFalse($client->searchFilms('Cars', 'fr'));
        self::assertFalse($client->getFilms(['Q182153']));
    }

    public function testAGarbledAnswerIsLikeAnError(): void
    {
        // Arrange
        $client = $this->client(WikidataApiBuilder::aWikidataApi()->answeringGarbage());

        // Act and assert
        self::assertFalse($client->searchFilms('Cars', 'fr'));
    }

    public function testMalformedIdsAreLeftOut(): void
    {
        // Arrange: ids end up in links; anything else than the expected formats is dropped.
        $client = $this->client(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q182153', 'Cars', 2006, [], 'javascript:alert(1)', '9 20'));

        // Act
        $films = $client->getFilms(['Q182153']);

        // Assert
        self::assertNull($films['Q182153']['imdbId']);
        self::assertNull($films['Q182153']['tmdbId']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make test c="--filter WikidataClientTest"`
Expected: FAIL, class `App\Sdk\Wikidata\WikidataClient` not found.

- [ ] **Step 3: Write the fake API and its builder**

`tests/Builder/WikidataApiBuilder.php`:

```php
<?php

namespace App\Tests\Builder;

/**
 * Answers of the Wikidata action API for FakeWikidataApi: films and their directors.
 */
final class WikidataApiBuilder
{
    private array $films = [];
    private array $people = [];
    private bool $failing = false;
    private bool $garbage = false;

    public static function aWikidataApi(): self
    {
        return new self();
    }

    public function withFilm(string $id, string $label, ?int $year, array $directors = [], ?string $imdbId = null, ?string $tmdbId = null, string $type = 'Q11424'): self
    {
        $clone = clone $this;
        $directorIds = [];
        foreach ($directors as $name) {
            $personId = 'Q9'.abs(crc32($name));
            $clone->people[$personId] = $name;
            $directorIds[] = $personId;
        }
        $clone->films[$id] = compact('label', 'year', 'directorIds', 'imdbId', 'tmdbId', 'type');

        return $clone;
    }

    public function failing(): self
    {
        $clone = clone $this;
        $clone->failing = true;

        return $clone;
    }

    public function answeringGarbage(): self
    {
        $clone = clone $this;
        $clone->garbage = true;

        return $clone;
    }

    /**
     * @return array{0: string, 1: int} JSON body and HTTP status for the query parameters of a request
     */
    public function answer(array $query): array
    {
        if ($this->failing) {
            return ['{"error":"unavailable"}', 503];
        }
        if ($this->garbage) {
            return ['<html>Something went wrong</html>', 200];
        }

        if ('wbsearchentities' === ($query['action'] ?? null)) {
            $search = mb_strtolower((string) ($query['search'] ?? ''));
            $hits = [];
            foreach ($this->films as $id => $film) {
                if (str_contains(mb_strtolower($film['label']), $search)) {
                    $hits[] = ['id' => $id, 'label' => $film['label']];
                }
            }

            return [json_encode(['search' => $hits], \JSON_THROW_ON_ERROR), 200];
        }

        $entities = [];
        foreach (explode('|', (string) ($query['ids'] ?? '')) as $id) {
            if (isset($this->films[$id])) {
                $film = $this->films[$id];
                $claim = static fn (string $type, mixed $value): array => ['mainsnak' => ['datavalue' => ['type' => $type, 'value' => $value]]];
                $claims = ['P31' => [$claim('wikibase-entityid', ['id' => $film['type']])]];
                if (null !== $film['year']) {
                    $claims['P577'] = [$claim('time', ['time' => '+'.$film['year'].'-01-01T00:00:00Z'])];
                }
                $claims['P57'] = array_map(static fn (string $person) => $claim('wikibase-entityid', ['id' => $person]), $film['directorIds']);
                if (null !== $film['imdbId']) {
                    $claims['P345'] = [$claim('string', $film['imdbId'])];
                }
                if (null !== $film['tmdbId']) {
                    $claims['P4947'] = [$claim('string', $film['tmdbId'])];
                }
                $entities[$id] = ['id' => $id, 'claims' => $claims];
            } elseif (isset($this->people[$id])) {
                $entities[$id] = ['id' => $id, 'labels' => ['en' => ['language' => 'en', 'value' => $this->people[$id]]]];
            } else {
                $entities[$id] = ['id' => $id, 'missing' => ''];
            }
        }

        return [json_encode(['entities' => $entities], \JSON_THROW_ON_ERROR), 200];
    }
}
```

`tests/Fake/FakeWikidataApi.php`:

```php
<?php

namespace App\Tests\Fake;

use App\Tests\Builder\WikidataApiBuilder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that replaces the Wikidata API in the test environment. Without a served
 * builder, every search finds nothing.
 */
final class FakeWikidataApi implements ClientInterface
{
    private WikidataApiBuilder $api;

    public function __construct()
    {
        $this->api = WikidataApiBuilder::aWikidataApi();
    }

    public function serve(WikidataApiBuilder $api): void
    {
        $this->api = $api;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        parse_str($request->getUri()->getQuery(), $query);
        [$body, $status] = $this->api->answer($query);

        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
}
```

- [ ] **Step 4: Write the mapper and the client**

`src/Sdk/Wikidata/WikidataMapper.php`:

```php
<?php

namespace App\Sdk\Wikidata;

/**
 * Turns raw answers of the Wikidata action API into arrays. External ids end up in links:
 * anything else than the expected formats is dropped.
 */
class WikidataMapper
{
    /**
     * @return array item ids of a wbsearchentities answer
     */
    public function searchIds(array $raw): array
    {
        $ids = [];
        foreach ($raw['search'] ?? [] as $hit) {
            $id = $hit['id'] ?? null;
            if (is_string($id) && 1 === preg_match('/^Q\d+$/', $id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array ['types' => Q ids, 'years' => ints, 'directorIds' => Q ids, 'imdbId' => ?string, 'tmdbId' => ?string]
     */
    public function film(array $entity): array
    {
        $claims = $entity['claims'] ?? [];
        $years = [];
        foreach ($this->values($claims, 'P577') as $time) {
            if (is_array($time) && 1 === preg_match('/^[+-](\d{4})-/', (string) ($time['time'] ?? ''), $matches)) {
                $years[] = (int) $matches[1];
            }
        }

        return [
            'types' => $this->entityIds($claims, 'P31'),
            'years' => array_values(array_unique($years)),
            'directorIds' => $this->entityIds($claims, 'P57'),
            'imdbId' => $this->firstMatching($this->values($claims, 'P345'), '/^tt\d+$/'),
            'tmdbId' => $this->firstMatching($this->values($claims, 'P4947'), '/^\d+$/'),
        ];
    }

    /**
     * @return array [id => label] of a wbgetentities answer with labels (English, else French)
     */
    public function labels(array $raw): array
    {
        $labels = [];
        foreach ($raw['entities'] ?? [] as $id => $entity) {
            $label = $entity['labels']['en']['value'] ?? $entity['labels']['fr']['value'] ?? null;
            if (is_string($label)) {
                $labels[$id] = $label;
            }
        }

        return $labels;
    }

    private function values(array $claims, string $property): array
    {
        $values = [];
        foreach ($claims[$property] ?? [] as $claim) {
            if (array_key_exists('value', $claim['mainsnak']['datavalue'] ?? [])) {
                $values[] = $claim['mainsnak']['datavalue']['value'];
            }
        }

        return $values;
    }

    private function entityIds(array $claims, string $property): array
    {
        $ids = [];
        foreach ($this->values($claims, $property) as $value) {
            $id = is_array($value) ? ($value['id'] ?? null) : null;
            if (is_string($id) && 1 === preg_match('/^Q\d+$/', $id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function firstMatching(array $values, string $pattern): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && 1 === preg_match($pattern, $value)) {
                return $value;
            }
        }

        return null;
    }
}
```

`src/Sdk/Wikidata/WikidataClient.php` (same construction as `PatheClient`: optional PSR collaborators discovered when not given):

```php
<?php

namespace App\Sdk\Wikidata;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

/**
 * Only access point to the Wikidata action API (CC0 data). Wikidata asks for a User-Agent naming
 * the application and a way to contact it, and for a gentle pace: one request every $delayMs.
 */
class WikidataClient
{
    public const BASE_URL = 'https://www.wikidata.org/w/api.php';

    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private ?CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        private string $userAgent,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?CacheItemPoolInterface $cache = null,
        ?LoggerInterface $logger = null,
        private WikidataMapper $mapper = new WikidataMapper(),
        private int $delayMs = 500,
        private int $cacheTtl = 86400,
    ) {
        $httpClient ??= Discover::httpClient();
        if (!$httpClient instanceof ClientInterface) {
            throw new \RuntimeException('No PSR-18 HTTP client found: pass one to WikidataClient or install one.');
        }
        $requestFactory ??= Discover::httpRequestFactory();
        if (!$requestFactory instanceof RequestFactoryInterface) {
            throw new \RuntimeException('No PSR-17 request factory found: pass one to WikidataClient or install one.');
        }
        $cache ??= Discover::cache();
        $logger ??= Discover::log();
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->cache = $cache instanceof CacheItemPoolInterface ? $cache : null;
        $this->logger = $logger instanceof LoggerInterface ? $logger : new NullLogger();
    }

    /**
     * @return array|false item ids found for a title (at most 10), or false when Wikidata fails
     */
    public function searchFilms(string $title, string $language): array|false
    {
        $raw = $this->get(['action' => 'wbsearchentities', 'search' => $title, 'language' => $language, 'type' => 'item', 'limit' => 10]);

        return false === $raw ? false : $this->mapper->searchIds($raw);
    }

    /**
     * @param array $ids item ids (Q…)
     *
     * @return array|false [id => ['types', 'years', 'directors' (labels), 'imdbId', 'tmdbId']], or false when Wikidata fails
     */
    public function getFilms(array $ids): array|false
    {
        if ([] === $ids) {
            return [];
        }
        $raw = $this->get(['action' => 'wbgetentities', 'ids' => implode('|', array_slice($ids, 0, 50)), 'props' => 'claims']);
        if (false === $raw) {
            return false;
        }

        $films = [];
        $people = [];
        foreach ($raw['entities'] ?? [] as $id => $entity) {
            if (isset($entity['claims']) && is_array($entity['claims'])) {
                $films[$id] = $this->mapper->film($entity);
                $people = array_merge($people, $films[$id]['directorIds']);
            }
        }

        $labels = [];
        if ([] !== $people) {
            $rawPeople = $this->get(['action' => 'wbgetentities', 'ids' => implode('|', array_slice(array_unique($people), 0, 50)), 'props' => 'labels', 'languages' => 'en|fr']);
            if (false === $rawPeople) {
                return false;
            }
            $labels = $this->mapper->labels($rawPeople);
        }

        foreach ($films as $id => $film) {
            $films[$id] = [
                'types' => $film['types'],
                'years' => $film['years'],
                'directors' => array_values(array_filter(array_map(static fn (string $person) => $labels[$person] ?? null, $film['directorIds']))),
                'imdbId' => $film['imdbId'],
                'tmdbId' => $film['tmdbId'],
            ];
        }

        return $films;
    }

    private function get(array $query): array|false
    {
        $query += ['format' => 'json'];
        $url = self::BASE_URL.'?'.http_build_query($query);
        $item = $this->cacheTtl > 0 ? $this->cache?->getItem('wikidata.'.sha1($url)) : null;
        if (null !== $item && $item->isHit()) {
            return $item->get();
        }

        usleep($this->delayMs * 1000);
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('User-Agent', $this->userAgent)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('Wikidata call failed', ['action' => $query['action'], 'error' => $e->getMessage()]);

            return false;
        }
        if (200 !== $response->getStatusCode()) {
            $this->logger->warning('Unexpected response from Wikidata', ['action' => $query['action'], 'status' => $response->getStatusCode()]);

            return false;
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger->warning('Wikidata answered something else than JSON', ['action' => $query['action']]);

            return false;
        }
        if (!is_array($data) || isset($data['error'])) {
            $this->logger->warning('Wikidata answered an error', ['action' => $query['action']]);

            return false;
        }

        if (null !== $item) {
            $this->cache?->save($item->set($data)->expiresAfter($this->cacheTtl));
        }

        return $data;
    }
}
```

- [ ] **Step 5: Wire the services**

`config/packages/framework.yaml`: under `http_client.scoped_clients` add

```yaml
            wikidata.http_client:
                scope: '^https://www\.wikidata\.org/'
                timeout: 10
```

and under `cache.pools`: `cache.wikidata: { adapter: cache.app }` (comment: "Wikidata answers, kept a day"); under `when@test` pools: `cache.wikidata: { adapter: cache.adapter.array }`.

`config/services.yaml`:

```yaml
    app.wikidata_http_client:
        class: Symfony\Component\HttpClient\Psr18Client
        arguments: ['@wikidata.http_client']

    App\Sdk\Wikidata\WikidataClient:
        arguments:
            # Wikidata asks every client to name itself and give a contact.
            $userAgent: 'ScreenRoute/1.0 (%env(SOURCE_CODE_URL)%)'
            $httpClient: '@app.wikidata_http_client'
            $cache: '@cache.wikidata'
```

and under `when@test.services`:

```yaml
        App\Sdk\Wikidata\WikidataClient:
            public: true
            arguments:
                $userAgent: 'ScreenRoute/test'
                $httpClient: '@App\Tests\Fake\FakeWikidataApi'
                $cache: '@cache.wikidata'
                $delayMs: 0
        App\Tests\Fake\FakeWikidataApi:
            public: true
```

- [ ] **Step 6: Run the test, then the whole suite and PHPStan**

Run: `make test c="--filter WikidataClientTest"`, `make test`, `make phpstan`
Expected: PASS (4 tests), all green.

- [ ] **Step 7: Commit**

```bash
git add src/Sdk/Wikidata config tests/Fake/FakeWikidataApi.php tests/Builder/WikidataApiBuilder.php tests/Integration/Sdk/WikidataClientTest.php
git commit -m "feat(sdk): a Wikidata client to search films and read their facts"
```

---

### Task 5: Link works to Wikidata and merge works

**Files:**
- Create: `src/Catalog/WorkMerger.php`, `src/Catalog/WorkLinker.php`, `tests/Integration/Catalog/WorkLinkerTest.php`
- Modify: `src/Catalog/Repository/WorkRepository.php`, `config/services.yaml` (`when@test`: `App\Catalog\WorkLinker: { autowire: true, public: true }`)

**Interfaces:**
- Consumes: `WikidataClient::searchFilms()`, `getFilms()`; `Work` API (Task 1); `FilmBuilder::ofWork()`.
- Produces:
  - `WorkMerger::merge(Work $a, Work $b): Work` — keeps the oldest (`createdAt`, then id), moves films, seen and unwanted rows (duplicates per user dropped), deletes the other one, flushes, returns the kept work.
  - `WorkLinker::describe(Film $film, string $originalTitle, ?int $year, ?array $directors): Work` — describes the film's work; when another chain's work has the same fingerprint, merges them (status `fingerprint` if it was `unlinked`); returns the film's work.
  - `WorkLinker::linkDue(array $works, \DateTimeImmutable $now): int` — tries every work in status `unlinked` or `fingerprint` not attempted for a day; returns the number linked.
  - `WorkLinker::link(Work $work, \DateTimeImmutable $now): bool` — one automatic attempt (sets `linkAttemptedAt`).
  - `WorkLinker::linkManually(Work $work, string $wikidataId): bool` — false when the item is not a film or Wikidata fails.
  - `WorkLinker::markNoMatch(Work $work): void`.
  - `WorkRepository::findOneByWikidataId(string $id): ?Work`, `findOtherChainWorkByFingerprint(string $fingerprint, Work $except): ?Work`, `findFilmTitles(Work $work): array`, `findWorksOfFilms(array $slugs): array`.

- [ ] **Step 1: Write the failing integration tests**

```php
<?php

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

    private const NOW = '2030-01-10 12:00:00';

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

        // Act
        $sameDay = $linker->linkDue([$work], $this->now()->modify('+2 hours'));
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
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `make test c="--filter WorkLinkerTest"`
Expected: FAIL, class `App\Catalog\WorkLinker` not found.

- [ ] **Step 3: Add the repository queries**

```php
    public function findOneByWikidataId(string $wikidataId): ?Work
    {
        return $this->findOneBy(['wikidataId' => $wikidataId]);
    }

    /**
     * A work with this fingerprint whose films come from another chain than those of $except.
     */
    public function findOtherChainWorkByFingerprint(string $fingerprint, Work $except): ?Work
    {
        return $this->createQueryBuilder('w')
            ->join(Film::class, 'f', 'WITH', 'f.work = w')
            ->where('w.fingerprint = :fingerprint')
            ->andWhere('w.id != :except')
            ->andWhere('f.chain NOT IN (SELECT DISTINCT f2.chain FROM '.Film::class.' f2 WHERE f2.work = :except)')
            ->setParameter('fingerprint', $fingerprint)
            ->setParameter('except', $except->getId(), 'uuid')
            ->orderBy('w.createdAt')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array titles of the films of a work (one per chain), to search Wikidata
     */
    public function findFilmTitles(Work $work): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT f.title')
            ->from(Film::class, 'f')
            ->where('f.work = :work')
            ->setParameter('work', $work->getId(), 'uuid')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return array works of the given films (by slug), each once
     */
    public function findWorksOfFilms(array $slugs): array
    {
        if ([] === $slugs) {
            return [];
        }

        return $this->createQueryBuilder('w')
            ->join(Film::class, 'f', 'WITH', 'f.work = w')
            ->where('f.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->distinct()
            ->getQuery()
            ->getResult();
    }
```

- [ ] **Step 4: Write the merger**

```php
<?php

namespace App\Catalog;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Two works that turn out to be the same film become one: the oldest stays and receives the films,
 * the seen and the not for me marks of the other one (one mark per user and work, the oldest kept).
 */
class WorkMerger
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function merge(Work $a, Work $b): Work
    {
        [$kept, $absorbed] = [$a->getCreatedAt(), (string) $a->getId()] <= [$b->getCreatedAt(), (string) $b->getId()] ? [$a, $b] : [$b, $a];
        $this->em->flush();
        $connection = $this->em->getConnection();
        $params = ['kept' => $kept->getId()->toBinary(), 'absorbed' => $absorbed->getId()->toBinary()];

        $connection->transactional(function () use ($connection, $params): void {
            $connection->executeStatement('UPDATE film SET work_id = :kept WHERE work_id = :absorbed', $params);
            foreach (['seen_film' => 'seen_at', 'unwanted_film' => 'created_at'] as $table => $date) {
                // A user who marked both works keeps the oldest mark.
                $connection->executeStatement(
                    "DELETE a FROM $table a INNER JOIN $table b ON b.user_id = a.user_id
                     WHERE a.work_id = :absorbed AND b.work_id = :kept AND b.$date <= a.$date",
                    $params,
                );
                $connection->executeStatement(
                    "DELETE b FROM $table a INNER JOIN $table b ON b.user_id = a.user_id
                     WHERE a.work_id = :absorbed AND b.work_id = :kept",
                    $params,
                );
                $connection->executeStatement("UPDATE $table SET work_id = :kept WHERE work_id = :absorbed", $params);
            }
            $connection->executeStatement('DELETE FROM work WHERE id = :absorbed', $params);
        });

        // The films already loaded (a synchronization goes on with them) follow the database;
        // the absorbed work leaves the entity manager without clearing the rest.
        foreach ($this->em->getUnitOfWork()->getIdentityMap()[Film::class] ?? [] as $film) {
            if ($film->getWork() === $absorbed) {
                $film->setWork($kept);
            }
        }
        $this->em->detach($absorbed);

        return $kept;
    }
}
```

- [ ] **Step 5: Write the linker**

```php
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
```

- [ ] **Step 6: Run the tests, the whole suite, PHPStan, code style**

Run: `make test c="--filter WorkLinkerTest"`, `make test`, `make phpstan`, `make cs`
Expected: PASS (9 tests), all green.

- [ ] **Step 7: Commit**

```bash
git add src/Catalog/WorkMerger.php src/Catalog/WorkLinker.php src/Catalog/Repository/WorkRepository.php config/services.yaml tests/Integration/Catalog/WorkLinkerTest.php
git commit -m "feat(catalog): link works to Wikidata, merge the works of the same film"
```

---

### Task 6: The synchronization describes and links works

**Files:**
- Modify: `src/Sdk/Pathe/PatheMapper.php`, `src/Catalog/Sync/CatalogSynchronizer.php:176-190`, `src/Catalog/Command/SyncCommand.php` (statistics line), `tests/Builder/PatheApiBuilder.php`, `tests/Integration/Catalog/Sync/CatalogSyncTest.php`

**Interfaces:**
- Consumes: `WorkLinker::describe()`, `linkDue()`; `WorkRepository::findWorksOfFilms()`.
- Produces: `PatheMapper::mapFilmDetails(array $rawShow): array` → `['originalTitle' => string|null, 'year' => int|null, 'directors' => list of string]`; the statistics of `synchronize()` gain `'linked'`; `PatheApiBuilder::withDetails(string $slug, string $originalTitle, int $year, string $directors)`.

- [ ] **Step 1: Write the failing tests**

Add to `CatalogSyncTest`:

```php
    public function testTheWorksOfThePlayingFilmsAreDescribedAndLinked(): void
    {
        // Arrange
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q130000001', 'Digger', 2026, ['Alejandro González Iñárritu'], 'tt30000001'));
        $api = $this->dijon()->withDetails('digger-51293', 'Digger', 2026, 'Alejandro González Iñárritu');

        // Act
        $stats = $this->synchronize($api);

        // Assert
        $this->em()->clear();
        $work = $this->em()->find(Film::class, 'digger-51293')->getWork();
        self::assertSame(['Alejandro González Iñárritu'], $work->getDirectors());
        self::assertSame('tt30000001', $work->getImdbId());
        self::assertSame(1, $stats['linked']);
    }

    public function testAnUnavailableWikidataDoesNotStopTheSynchronization(): void
    {
        // Arrange
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()->failing());

        // Act
        $stats = $this->synchronize($this->dijon());

        // Assert
        self::assertNotFalse($stats);
        self::assertSame(0, $stats['linked']);
        self::assertNotNull($this->em()->find(Showtime::class, 'V3345S85501'));
    }
```

(imports: `App\Tests\Builder\WikidataApiBuilder`, `App\Tests\Fake\FakeWikidataApi`).

- [ ] **Step 2: Run them to verify they fail**

Run: `make test c="--filter 'testTheWorksOfThePlayingFilms|testAnUnavailableWikidata'"`
Expected: FAIL (`withDetails` undefined; then `linked` missing).

- [ ] **Step 3: Implement**

`PatheApiBuilder::withDetails()` sets `originalTitle`, `releaseAt` (`['FR_FR' => $year.'-01-01']`) and `directors` in `$clone->details[$slug]`.

`PatheMapper`:

```php
    /**
     * What identifies the film as a work, from its film page: original title, release year and
     * directors (Pathé gives them as one comma-separated string).
     */
    public function mapFilmDetails(array $rawShow): array
    {
        $title = $rawShow['originalTitle'] ?? $rawShow['title'] ?? null;
        $date = $rawShow['releaseAt']['FR_FR'] ?? null;
        $directors = is_string($rawShow['directors'] ?? null) ? $rawShow['directors'] : '';

        return [
            'originalTitle' => is_string($title) && '' !== trim($title) ? trim($title) : null,
            'year' => is_string($date) && 1 === preg_match('/^(\d{4})-/', $date, $matches) ? (int) $matches[1] : null,
            'directors' => array_values(array_filter(array_map('trim', explode(',', $directors)))),
        ];
    }
```

`CatalogSynchronizer`: inject `WorkLinker $workLinker` and `WorkRepository $workRepository`; add `'linked' => 0` to the statistics; the loop over the playing films reads the film page also when the work's directors are unknown, and describes the work:

```php
        // Original language (VOST/VO filter), synopsis and work of the films that play, read once from their film page.
        foreach ($playing as $showSlug => $film) {
            if (null !== $film->getOriginalLanguage() && null !== $film->getSynopsis() && null !== $film->getWork()->getDirectors()) {
                continue;
            }
            $rawShow = $this->client->getShow($showSlug);
            if (false === $rawShow) {
                $this->logger->warning('Film page unreadable, original language, synopsis and work unknown', ['film' => $showSlug]);
                continue;
            }
            $film->setOriginalLanguage($this->mapper->mapOriginalLanguage($rawShow));
            $film->setSynopsis($this->mapper->mapSynopsis($rawShow));
            $details = $this->mapper->mapFilmDetails($rawShow);
            $this->workLinker->describe($film, $details['originalTitle'] ?? $film->getTitle(), $details['year'], $details['directors']);
        }
        $this->em->flush();

        // Works not linked yet are looked up in Wikidata, at most once a day each; Wikidata failing never stops the sync.
        $stats['linked'] = $this->workLinker->linkDue(
            $this->workRepository->findWorksOfFilms(array_keys($playing)),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
```

Note: `describe()` may merge works and clear the entity manager; re-read `$film` objects are not used after the loop, so the flush stays valid. `SyncCommand`: add `%d works linked` to its summary line with `$stats['linked']`.

- [ ] **Step 4: Run the sync tests, then the whole suite**

Run: `make test c="--filter CatalogSyncTest"`, `make test`, `make phpstan`
Expected: all green.

- [ ] **Step 5: Run a real synchronization on the dev database (network)**

Run: `make sync c="--city=dijon"`
Expected: the summary reports some works linked; then
`docker compose exec -T database sh -c 'mysql -N -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT link_status, COUNT(*) FROM work GROUP BY link_status"'` shows `wikidata` rows. Note the numbers in the ledger.

- [ ] **Step 6: Commit**

```bash
git add src/Sdk/Pathe/PatheMapper.php src/Catalog/Sync src/Catalog/Command/SyncCommand.php tests/Builder/PatheApiBuilder.php tests/Integration/Catalog/Sync/CatalogSyncTest.php
git commit -m "feat(catalog): the synchronization describes the works and links them to Wikidata"
```

---

### Task 7: `work:link` links by hand

**Files:**
- Create: `src/Catalog/Command/WorkLinkCommand.php`, `tests/Integration/Catalog/WorkLinkCommandTest.php`

**Interfaces:**
- Consumes: `WorkLinker::linkManually()`, `markNoMatch()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace App\Tests\Integration\Catalog;

use App\Catalog\Entity\Film;
use App\Catalog\WorkLinkStatus;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\WikidataApiBuilder;
use App\Tests\Fake\FakeWikidataApi;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class WorkLinkCommandTest extends KernelTestCase
{
    use StoresEntities;

    private function command(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('work:link'));
    }

    public function testLinksTheWorkOfAFilmByHandOrStopsTheAttempts(): void
    {
        // Arrange
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()->withFilm('Q182153', 'Cars', 2006, ['John Lasseter'], 'tt0317219'));
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->build(), FilmBuilder::aFilm()->withSlug('unknown-film')->build());

        // Act
        $linked = $this->command()->execute(['film' => 'cars', 'wikidata-id' => 'Q182153']);
        $none = $this->command()->execute(['film' => 'unknown-film', '--none' => true]);
        $missing = $this->command()->execute(['film' => 'no-such-film', 'wikidata-id' => 'Q1']);

        // Assert
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame([0, 0, 1], [$linked, $none, $missing]);
        self::assertSame('tt0317219', $em->find(Film::class, 'cars')->getWork()->getImdbId());
        self::assertSame(WorkLinkStatus::NoMatch, $em->find(Film::class, 'unknown-film')->getWork()->getLinkStatus());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make test c="--filter WorkLinkCommandTest"`
Expected: FAIL, `The command "work:link" does not exist.`

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Catalog\Command;

use App\Catalog\Entity\Film;
use App\Catalog\WorkLinker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'work:link', description: 'Links the work of a film to a Wikidata item by hand, or stops the attempts (--none)')]
class WorkLinkCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private WorkLinker $workLinker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('film', InputArgument::REQUIRED, 'Slug of a film, e.g. cars')
            ->addArgument('wikidata-id', InputArgument::OPTIONAL, 'Wikidata item of the film, e.g. Q182153')
            ->addOption('none', null, InputOption::VALUE_NONE, 'The film is not in Wikidata: stop looking for it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $film = $this->em->find(Film::class, (string) $input->getArgument('film'));
        if (null === $film) {
            $io->error('Unknown film.');

            return Command::FAILURE;
        }

        if ($input->getOption('none')) {
            $this->workLinker->markNoMatch($film->getWork());
            $io->success('The work will not be looked up any more.');

            return Command::SUCCESS;
        }

        $id = (string) $input->getArgument('wikidata-id');
        if (1 !== preg_match('/^Q\d+$/', $id) || !$this->workLinker->linkManually($film->getWork(), $id)) {
            $io->error('Not linked: give the Q id of a film (Wikidata may also be unreachable).');

            return Command::FAILURE;
        }
        $io->success(sprintf('The work of %s is linked to %s.', $film->getTitle(), $id));

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test and the whole suite**

Run: `make test c="--filter WorkLinkCommandTest"`, `make test`
Expected: PASS, all green.

- [ ] **Step 5: Commit**

```bash
git add src/Catalog/Command/WorkLinkCommand.php tests/Integration/Catalog/WorkLinkCommandTest.php
git commit -m "feat(catalog): work:link links the work of a film by hand"
```

---

### Task 8: External links on the film page, works in the API

**Files:**
- Modify: `src/Catalog/Repository/FilmRepository.php` (`findBySlugs`), `templates/film/show.html.twig`, `src/Api/Controller/FilmController.php`, `translations/messages.{en,fr}.yaml`, `tests/Functional/Web/FilmPageTest.php`, `tests/Functional/Api/FilmApiTest.php` (or the existing API film test file)
- Create: `src/Api/Controller/WorkController.php`, `tests/Functional/Api/WorkApiTest.php`

**Interfaces:**
- Consumes: `Work` ids; `WorkBuilder::linkedTo()`.
- Produces: rows of `FilmRepository::findBySlug(s)` gain `workId` (RFC 4122 string), `wikidataId`, `imdbId`, `tmdbId`; `GET /api/works/{id}`.

- [ ] **Step 1: Write the failing tests**

`FilmPageTest`:

```php
    public function testLinksTheFilmToItsWorkElsewhere(): void
    {
        // Arrange
        $client = $this->signedIn();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->ofWork(WorkBuilder::aWork()->titled('Cars')->linkedTo('Q182153', 'tt0317219', '920')->build())->build());

        // Act
        $crawler = $client->request('GET', '/films/cars');

        // Assert
        self::assertSame(
            ['https://www.wikidata.org/wiki/Q182153', 'https://www.imdb.com/title/tt0317219/', 'https://www.themoviedb.org/movie/920'],
            $crawler->filter('#external-links a')->each(static fn ($link) => $link->attr('href')),
        );
    }
```

`tests/Functional/Api/WorkApiTest.php` (reuse the token helper of the API tests, `DijonCatalogWithToken`):

```php
    public function testAWorkListsItsFilmsAndItsIds(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $work = WorkBuilder::aWork()->titled('Cars')->linkedTo('Q182153', 'tt0317219')->build();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->ofWork($work)->build());

        // Act
        $film = $this->api('GET', '/api/films/cars');
        $found = $this->api('GET', '/api/works/'.$film['work']['id']);
        $this->api('GET', '/api/works/not-a-uuid');
        $malformed = $this->client->getResponse()->getStatusCode();
        $this->api('GET', '/api/works/0192a6f0-0000-7000-8000-000000000000');
        $unknown = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame('Q182153', $film['work']['wikidataId']);
        self::assertSame([['chain' => 'pathe', 'slug' => 'cars', 'title' => 'Cars']], $found['films']);
        self::assertSame('tt0317219', $found['imdbId']);
        self::assertSame([404, 404], [$malformed, $unknown]);
    }
```

- [ ] **Step 2: Run them to verify they fail**

Run: `make test c="--filter 'testLinksTheFilmToItsWork|WorkApiTest'"`
Expected: FAIL (no `#external-links`, route `/api/works/{id}` missing).

- [ ] **Step 3: Implement**

`FilmRepository::findBySlugs()`: join the work and select its ids, then convert the id:

```php
        $rows = $this->createQueryBuilder('f')
            ->select('f.slug', 'f.title', 'f.duration', 'f.releaseDate', 'f.genres', 'f.posterUrl', 'f.contentRating', 'f.synopsis',
                'w.id AS workId', 'w.wikidataId', 'w.imdbId', 'w.tmdbId')
            ->join('f.work', 'w')
            …
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['workId' => $row['workId']->toRfc4122()] + $row, $rows);
```

`templates/film/show.html.twig`, after the synopsis:

```twig
            {% if film.wikidataId %}
                {# Open data: the work of this film elsewhere (ids checked when stored). #}
                <p id="external-links" class="flex flex-wrap gap-4 text-sm">
                    <a href="https://www.wikidata.org/wiki/{{ film.wikidataId }}" target="_blank" rel="noopener noreferrer" class="text-brand-700 hover:underline">Wikidata</a>
                    {% if film.imdbId %}<a href="https://www.imdb.com/title/{{ film.imdbId }}/" target="_blank" rel="noopener noreferrer" class="text-brand-700 hover:underline">IMDb</a>{% endif %}
                    {% if film.tmdbId %}<a href="https://www.themoviedb.org/movie/{{ film.tmdbId }}" target="_blank" rel="noopener noreferrer" class="text-brand-700 hover:underline">TMDB</a>{% endif %}
                </p>
            {% endif %}
```

`Api\Controller\FilmController::show()`: add `'work' => ['id' => $film['workId'], 'wikidataId' => $film['wikidataId'], 'imdbId' => $film['imdbId'], 'tmdbId' => $film['tmdbId']],` and document it in the `OA\Response` description.

`src/Api/Controller/WorkController.php`:

```php
<?php

namespace App\Api\Controller;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

class WorkController extends AbstractController
{
    #[Route('/api/works/{id}', name: 'api_work', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    #[OA\Tag(name: 'Films')]
    #[OA\Response(response: 200, description: 'A work common to every chain: its external ids (null when unknown) and its films by chain')]
    #[OA\Response(response: 404, description: 'Unknown work')]
    public function show(string $id, EntityManagerInterface $em): JsonResponse
    {
        $work = $em->find(Work::class, Uuid::fromString($id));
        if (null === $work) {
            throw new NotFoundHttpException('error.work_not_found');
        }

        $films = array_map(
            static fn (Film $film): array => ['chain' => $film->getChain(), 'slug' => $film->getSlug(), 'title' => $film->getTitle()],
            $em->getRepository(Film::class)->findBy(['work' => $work], ['chain' => 'ASC', 'slug' => 'ASC']),
        );

        return new JsonResponse([
            'id' => $work->getId()->toRfc4122(),
            'originalTitle' => $work->getOriginalTitle(),
            'year' => $work->getYear(),
            'wikidataId' => $work->getWikidataId(),
            'imdbId' => $work->getImdbId(),
            'tmdbId' => $work->getTmdbId(),
            'films' => $films,
        ]);
    }
}
```

Translations: `error.work_not_found` — en `'Unknown work.'`, fr `'Œuvre inconnue.'`.

- [ ] **Step 4: Run the tests, the whole suite, the translation check**

Run: `make test c="--filter 'FilmPageTest|WorkApiTest|FilmApi'"`, `make test`, and for `en` and `fr`: `docker compose exec -T php bin/console debug:translation <locale> --only-missing`
Expected: all green, no missing translation.

- [ ] **Step 5: Commit**

```bash
git add src/Catalog/Repository/FilmRepository.php templates/film/show.html.twig src/Api/Controller translations tests/Functional
git commit -m "feat(web): open data links on the film page, works in the API"
```

---

### Task 9: The catalog dump carries the works

**Files:**
- Modify: `Makefile` (`db-dump`), `README.md`, `docs/self-hosting.md`, `docs/superpowers/specs/2026-10-04-workshop-design.md` (the "chain-scoped identifiers" TODO: mention that works are the common identifier now)

- [ ] **Step 1: Dump the works with the catalog**

In `db-dump`, the tables become `city cinema work film showtime` (works before the films that reference them).

- [ ] **Step 2: Check the dump round trip on the dev database (network for the sync)**

Run: `make sync`, `make db-dump`, `make db-load`; then
`docker compose exec -T database sh -c 'mysql -N -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM work; SELECT COUNT(*) FROM film WHERE work_id NOT IN (SELECT id FROM work)"'`
Expected: works present, `0` orphan films.

- [ ] **Step 3: Document**

README: in the everyday commands, `make sync` "also links the works to Wikidata"; a line "`bin/console work:link <film> <Q-id>` / `--none`: link a film's work by hand". `docs/self-hosting.md`: in "Your obligations", Wikidata data is CC0; the User-Agent names your `SOURCE_CODE_URL`. Note in both: dumps older than this change (`catalog-2026-10-05`) cannot be loaded any more; publish the new one with `make db-upload` and pin it (`CATALOG_DUMP_DATE`) — publishing is done by the maintainer when asked.

- [ ] **Step 4: Run the whole suite and commit**

Run: `make test`, `make phpstan`
Expected: all green.

```bash
git add Makefile README.md docs
git commit -m "docs: the catalog dump carries the works; how to link a work by hand"
```
