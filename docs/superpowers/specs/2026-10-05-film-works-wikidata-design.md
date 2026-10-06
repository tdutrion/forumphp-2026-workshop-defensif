# Film works and Wikidata — design

Date: 2026-10-05. Status: approved in conversation, to be reviewed in writing.

## Goal

Give every film a **common identifier across cinema chains** (a *work*), linked when possible to
**open data**, so that:

- a film shown by several chains is one work: "Already seen" and "Not for me" hold for every chain,
  and a programme never offers the same work twice;
- the work carries external identifiers (Wikidata, IMDb, TMDB) for the future paid
  recommendations (graph database) and for links on the film page;
- a film that cannot be linked still works exactly as today;
- the workshop still runs offline: linking only happens at synchronization time.

## Findings that shaped the design

- **Source.** Wikidata is CC0 and needs no key. IMDb datasets are for personal and non-commercial
  use only, and TMDB requires a commercial agreement: both are incompatible with the paid options
  announced on the landing page. IMDb and TMDB identifiers are kept only as references read from
  Wikidata.
- **Coverage (spike of 2026-10-05, 20 films showing at Pathé, naive matching).** 4 of 20 found in
  Wikidata (20 %). Causes: most 2026 releases have no Wikidata item yet; Pathé's `originalTitle`
  is often the French title; re-releases carry the re-release date (Hellraiser, 1988, re-released
  in 2026). Wikidata cannot be the only identifier: every film gets an internal work, enriched
  when Wikidata knows it, and retried at each synchronization.
- **Query method.** SPARQL queries took several seconds per film and timed out; the action API
  (`wbsearchentities` then `wbgetentities`) answered in well under a second.

## Data model

- **`Work`** (new entity, `App\Catalog\Entity\Work`):
  - `id`: UUID v7, the common identifier;
  - `originalTitle`, `year` (original release year, nullable), `directors` (JSON list);
  - `wikidataId` (e.g. `Q182153`), `imdbId` (`tt…`), `tmdbId` (all nullable, `wikidataId` unique);
  - `fingerprint`: normalized original title + year + surname of the first director (nullable when
    a part is unknown; indexed, not unique);
  - `linkStatus`: `unlinked`, `wikidata`, `fingerprint`, `manual`, `no_match` (enum);
  - `linkAttemptedAt`: last automatic attempt (UTC, nullable).
- **`Film`** (existing, one per chain) gets a mandatory `work` relation. Every film gets a work
  when it is synchronized: an existing one (same Wikidata id, or same fingerprint) or a new one.
- **`SeenFilm`, `UnwantedFilm`** reference the **work** instead of the film. The migration converts
  the existing rows (one row per user and work, the oldest date kept). The unique keys become
  (user, work).
- **Planner**: the candidate showtimes carry the work id; a programme never holds two showtimes of
  the same work, and the films seen or not for me are excluded by work.

Fingerprint normalization: lower case, accents removed, punctuation and spaces collapsed, articles
kept (no language guessing). Two films of different chains with the same non-null fingerprint are
the same work. The fingerprint is only used when all three parts are known.

## Linking to Wikidata

- **Input**, from the Pathé film page (`/show/{slug}`) already read for the original language and
  the synopsis: `originalTitle`, `title`, `releaseAt` (year), `directors`, `duration`. No extra
  request to Pathé.
- **SDK** `src/Sdk/Wikidata/`, built like the Pathé SDK (PSR-18 client, PSR-17 factory, PSR-6
  cache pool `cache.wikidata`, PSR-3 logger, delay between requests, a User-Agent naming the
  application and its source code URL, as Wikidata asks):
  - `searchFilms(string $title, string $language): list of item ids` (`wbsearchentities`, 10 at most);
  - `getItems(list of ids): array` of type ids (P31), release years (P577), director labels
    (P57 resolved), IMDb (P345) and TMDB (P4947) ids (`wbgetentities`, one request for all ids).
  - Errors and unexpected answers return `false` (same contract as the Pathé SDK) and are logged.
- **Search**: the original title, then the French title when it differs, in French and English.
- **A candidate is kept when**: its type is a film or a known subtype (film, feature film,
  animated film, documentary film, short film, silent film…); **and** its release year is within
  one year of Pathé's **or** a director matches (re-releases); **and**, when Pathé gives a director,
  the surname of one of them is among the candidate's directors (accent and case insensitive).
- **Decision**: exactly one candidate kept → the work is linked (`wikidata`) with its ids; none or
  several → nothing is linked, never a guess; `linkAttemptedAt` is set.
- **Retry**: works in `unlinked` or `fingerprint` status are retried at most once a day, during the
  synchronization (the scheduled one included). `manual` and `no_match` works are never retried.
- **Failure**: a Wikidata error never fails the Pathé synchronization; the work stays as it is and
  is retried the next day. The synchronization statistics report the works linked.

## Manual linking

- `bin/console work:link <film-slug> <Q-id>`: checks that the item is a film (same type rule), then
  links the work of the film (`manual`, ids read from Wikidata). The command refuses an item that
  is not a film.
- `bin/console work:link <film-slug> --none`: marks the work `no_match` (no more attempts).
- A manual link always wins over automatic linking.

## Merging works

When a Wikidata id (automatic or manual) already belongs to another work, the two works are
merged in one transaction: the oldest work is kept; the films, "Already seen" and "Not for me"
rows of the other one move to it (duplicates per user dropped, the oldest date kept); the other
work is deleted. The same applies when a fingerprint matches a work of another chain at
synchronization time.

## What users and the API see

- **Film page**: "Wikidata", "IMDb" and "TMDB" links (new tab, `rel="noopener noreferrer"`) when the
  work has the ids; nothing otherwise.
- **Already seen / Not for me**: buttons and URLs unchanged (film slug); the service records the
  choice on the film's work.
- **History and Not for me pages**: one row per work, titled and linked to one of its films.
- **API**: films carry `work` (`id`, `wikidataId`, `imdbId`, `tmdbId`, null when absent);
  `GET /api/works/{id}` returns a work and its films by chain. Documented in the OpenAPI doc.
- **Catalog dump**: includes the works and their links (`make db-dump` / `make db-load`).

## Testing

No test reaches the network: a `FakeWikidataApi` (like `FakePatheApi`) serves the answers.
Functional and integration tests, arrange-act-assert, builders for the collaborators:

- a single candidate links the work, with its IMDb and TMDB ids;
- ambiguous or empty results link nothing;
- a re-release is linked through its director despite the year;
- an unlinked work is retried after a day, not before;
- a manual link is never replaced; `--none` stops the attempts; a non-film item is refused;
- a second, fake chain: the same film (same fingerprint) becomes one work; seen on one chain is
  seen on the other; the planner does not offer it twice;
- merging moves films and marks without duplicates;
- the film page shows the links; the API exposes the work;
- the migration converts existing seen and unwanted rows;
- a Wikidata failure does not fail the Pathé synchronization.

## Out of scope

- A second real chain (its SDK is a separate project).
- The recommendation engine and the graph database (paid option).
- Linking films through other open sources than Wikidata.
- An administration screen for links (the console command is enough for now).
