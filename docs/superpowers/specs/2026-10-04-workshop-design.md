# Workshop « PHP défensif » : application d'exemple (v1)

- Date : 2026-10-04
- Statut : design validé en conversation, spec à relire
- Atelier : 8 octobre 2026, 2 h, matière pour environ 8 h

## 1. Objectif

Construire l'application qui sert de point de départ à un atelier sur la
programmation défensive en PHP, de PHP 5 à PHP 8.5, plus les ajouts de 8.6
disponibles via les polyfills Symfony.

L'application est un **planificateur de marathon cinéma**. Un utilisateur
connecté choisit une date, un lieu et un nombre de films. Il obtient 3
programmes de séances Pathé qui s'enchaînent, sans film déjà vu.

**Le principe de la v1** : un projet propre, fonctionnel et sûr. Il ne contient
ni bug ni faille de sécurité. Ce qui s'améliore pendant l'atelier, c'est la
facilité pour un développeur d'utiliser correctement les classes et fonctions
exposées. En v1, les contrats sont implicites : tableaux plutôt que DTO,
scalaires plutôt qu'enums, retours `false|résultat`, peu d'exceptions et
génériques. L'atelier les rend explicites et vérifiés par le langage et
l'outillage.

> Les tentatives précédentes ont confondu « failles » et « violations ». Ce
> spec n'introduit aucune faille, aucun bug, et pas de liste de
> « violations » dans le dépôt. La correspondance entre pratiques et code
> vit dans ce spec (section 9) et dans le guide formateur.

### Critères de succès

1. `make up` puis `make fixtures` donnent une application utilisable en
   local, sans appel à Pathé, avec une connexion via le fournisseur OIDC
   local.
2. Les 3 programmes respectent les règles de la section 5.
3. Le web et l'API offrent les mêmes cas d'usage, via les mêmes services.
4. Les tests sont verts. PHPStan passe au niveau 5 sans erreur.
5. Chaque exercice des sections 9 et 10 a un point d'ancrage réel et
   crédible dans le code.
6. Aucune faille de sécurité, confirmée par une relecture dédiée avant le
   8 octobre.

## 2. Contraintes

- **Runtime** : `dunglas/frankenphp:1.12.7-php8.5-alpine` (PHP 8.5.11 ZTS),
  digest épinglé dans le Dockerfile. Worker mode activé. Hub Mercure
  intégré activé.
- **Framework** : Symfony 8.1 (8.2 sort après l'atelier).
- **Polyfills** : `symfony/polyfill-php86` (`clamp()`, `SortDirection`,
  `ARRAY_FILTER_USE_VALUE`, `grapheme_strrev()`) et
  `symfony/polyfill-time` (`Time\Duration`). Les nouveautés 8.6 qui
  touchent la syntaxe ou le moteur (valeurs par défaut des propriétés
  `readonly`, application partielle, `#[\Override]` sur les constantes)
  ne sont pas disponibles et seront seulement montrées en slide.
- **Base de données** : MySQL 8.4 LTS. Les calculs de distance se font en
  PHP (formule de Haversine) : moins de 100 cinémas, inutile d'utiliser des
  index spatiaux.
- **Extensions** : intl et pdo_mysql via `docker-php-ext-install` dans la
  base. apcu via PIE dans la base. Xdebug via PIE et zip via
  `docker-php-ext-install`, uniquement dans la cible `dev`. Les paquets PIE
  sont épinglés dans `pie.json`.
- **Réseau** : Internet disponible le jour J, avec des coupures possibles.
  L'application ne doit jamais appeler Pathé pendant l'atelier.
- **Identifiants générés** : UUID v7 (composant Uid). Les données Pathé
  gardent leur slug comme clé naturelle.
- **Langues** : identifiants du code en anglais, interface et
  documentation en français, README en anglais.
- **Git** : Conventional Commits en anglais, commits signés au nom de
  `Thomas Dutrion <hello@tdutrion.fr>`.

## 3. Périmètre

### Pour le 8 octobre

- Docker compose : FrankenPHP (dev), MySQL, fournisseur OIDC local, worker
  Messenger. Un seul Dockerfile multistage avec les cibles `dev` et `prod`.
- Synchronisation Pathé (commande + tâche planifiée) et jeu de données figé.
- Connexion Google, GitHub et OIDC local. Liaison de plusieurs connexions
  à un même compte.
- « Déjà vu » déclaré par l'utilisateur.
- Planificateur en Twig (Symfony UX Turbo + Autocomplete) et en API JSON
  (jeton personnel).
- Une notification Mercure : « programmation mise à jour » après une
  synchronisation.
- PHPUnit, PHPStan, PHP-CS-Fixer, Makefile, guide formateur.

### Plus tard (hors périmètre de ce spec)

LinkedIn, flux OAuth Authorization Code + PKCE pour une application mobile
(`league/oauth2-server-bundle`), autres usages temps réel de Mercure, carte
Symfony UX Map, co-planification à plusieurs, branche de solution.

## 4. Architecture

### Conteneurs

| Service  | Image                                       | Rôle                                         |
|----------|---------------------------------------------|----------------------------------------------|
| `php`    | Dockerfile, cible `dev`                     | FrankenPHP : HTTPS, worker mode, hub Mercure |
| `worker` | même image                                  | `messenger:consume` (synchro planifiée)      |
| `db`     | `mysql:8.4`                                 | base de l'application                        |
| `oidc`   | `ghcr.io/navikt/mock-oauth2-server:6.0.4`   | fournisseur OIDC local, connexion interactive |

### Dockerfile multistage

1. `base` : image FrankenPHP épinglée, `docker-php-ext-install intl
   pdo_mysql`, PIE 1.5 copié depuis `ghcr.io/php/pie`, `pie install` pour
   apcu (dépendances de build installées puis supprimées), réglages PHP
   communs.
2. `dev` : `FROM base`, Composer, zip, Xdebug via PIE (désactivé par
   défaut, activable par variable d'environnement), `php.ini-development`,
   mode watch de FrankenPHP.
3. `prod` : `FROM base`, `composer install --no-dev --classmap-authoritative`
   dans une étape de build, OPcache réglé pour la production, utilisateur
   non-root, aucun outil de développement.

### Modules (namespaces sous `App\`)

| Module     | Responsabilité                                                                 | Dépend de                  |
|------------|--------------------------------------------------------------------------------|----------------------------|
| `Pathe`    | Client HTTP Pathé, mapping des réponses, commande de synchro, enregistrement des fixtures | `Catalog`                  |
| `Catalog`  | Entités `City`, `Cinema`, `Film`, `Showtime` et leurs repositories                     | —                          |
| `Planner`  | Calcul des programmes à partir du catalogue                                    | `Catalog`, `Account`       |
| `Account`  | `User`, connexions liées, films déjà vus, jetons d'API                         | `Catalog`                  |
| `Security` | Authenticator OAuth générique, registre des providers, authenticator par jeton | `Account`                  |
| `Web`      | Contrôleurs Twig, formulaires, composants UX                                   | `Planner`, `Account`, `Catalog` |
| `Api`      | Contrôleurs JSON, documentation OpenAPI (Nelmio)                               | `Planner`, `Account`, `Catalog` |

Les contrôleurs `Web` et `Api` sont minces. Ils appellent les **mêmes**
services applicatifs : `PlannerService`, `SeenFilmService`,
`AccountService`. Aucune logique métier n'est dupliquée entre les deux
canaux. Un test fonctionnel vérifie que le web et l'API donnent le même
résultat pour une même requête.

### Flux de données

```
Pathé (/api/*) ──► PatheClient ──► CatalogSynchronizer ──► MySQL ──► PlannerService ──► Web (Twig)
                       ▲                                                          └────► Api (JSON)
     fixtures enregistrées (make fixtures)
```

Seuls la commande de synchro et la commande d'enregistrement des fixtures
appellent Pathé. Le site lit uniquement la base.

## 5. Domaine

### Formulaire de planification

| Champ    | Règle                                                                                                     |
|----------|-----------------------------------------------------------------------------------------------------------|
| Date     | un jour pour lequel des séances sont synchronisées (Pathé publie la semaine en cours, du mercredi au mardi) |
| Lieu     | une ville Pathé (autocomplétion sur les villes synchronisées) ou la position du navigateur                |
| Rayon    | 1 à 50 km, 10 km par défaut                                                                               |
| Films    | 2 à 5                                                                                                     |
| Version  | optionnelle : VF, VOST, VO, VFST                                                                          |
| Pubs     | case « J'accepte d'arriver pendant les pubs (15 minutes) »                                                |

Le centre du rayon est la position GPS de la ville ou celle du navigateur.

### Séances candidates

Une séance est candidate si elle remplit toutes ces conditions :

- son `time` tombe à la date demandée (heure de Paris) ;
- son cinéma est ouvert et dans le rayon ;
- `status = available` et `reservabilityEnd` est dans le futur ;
- son film n'est pas dans la liste « déjà vu » de l'utilisateur ;
- sa version correspond, si une version est demandée.

### Règles d'enchaînement

- Une séance occupe la plage de `time` (début des pubs) à `endTime` (fin du
  film). Pathé donne `endTime = time + durée + 20 min`.
- Pour la séance suivante, l'heure d'arrivée au plus tôt vaut :
  - la fin de la précédente + 10 min, si c'est le même cinéma ;
  - la fin de la précédente + 10 min + le trajet, si c'est un autre
    cinéma. Le trajet est la distance à vol d'oiseau entre les deux
    cinémas, parcourue à 15 km/h.
- La séance suivante est compatible si l'arrivée au plus tôt est avant ou
  égale à son `time`. Avec l'option pubs, l'arrivée peut aller jusqu'à
  `time + 15 min`.
- Un programme ne contient jamais deux fois le même film.

### Les 3 programmes

1. Les séances candidates sont triées par `time`. Une exploration en
   profondeur construit les suites de N séances compatibles, avec un
   plafond de 20 000 nœuds explorés. Le plafond garde le temps de réponse
   sous la seconde.
2. Chaque programme complet est noté : temps d'attente total (somme des
   écarts entre arrivée et `time`), puis distance totale parcourue.
3. On retient les 3 meilleurs programmes deux à deux différents : chacun
   doit avoir au moins un film que l'autre n'a pas.
4. S'il en existe moins de 3, on renvoie ceux qu'on a, avec un message qui
   explique pourquoi (aucune séance candidate, rayon trop petit, trop de
   films demandés…).

### « Déjà vu »

- Bouton « Déjà vu » sur chaque film, dans les résultats et sur la fiche
  film. Annulable.
- Liste gérée depuis le profil.
- Bouton « Marquer ce programme comme vu » qui ajoute tous ses films.

### Comptes et connexion

- Un `User` (UUID v7) a une ou plusieurs connexions liées (`provider`,
  identifiant chez le provider, email, email vérifié ou non).
- Première connexion via un provider : si l'email est **vérifié par le
  provider** et appartient déjà à un compte, la connexion est liée à ce
  compte. Sinon, un nouveau compte est créé.
- Depuis le profil, l'utilisateur connecté peut lier un autre provider ou
  retirer une connexion, mais pas la dernière.
- Providers en v1 :
  - `google` : `league/oauth2-google`, scopes `openid email profile`.
  - `github` : `league/oauth2-github`, scope `user:email`, email vérifié lu
    via `/user/emails`.
  - `local` : `GenericProvider` de league sur le fournisseur OIDC local.
    Il sert de secours sans Internet et montre l'ajout d'un provider par
    configuration.
- Ajouter un provider = un bloc de configuration KnpU + les variables
  d'environnement, sans code nouveau, sauf si le provider renvoie ses
  informations utilisateur dans un format inédit.

### API

| Méthode | Route                  | Cas d'usage                    |
|---------|------------------------|--------------------------------|
| GET     | `/api/plans`           | planifier (mêmes paramètres que le formulaire) |
| GET     | `/api/cities`          | lister les villes              |
| GET     | `/api/films/{slug}`    | fiche film                     |
| GET     | `/api/me`              | profil                         |
| GET     | `/api/me/seen-films`   | lister les films déjà vus      |
| PUT     | `/api/me/seen-films/{slug}` | marquer un film comme vu  |
| DELETE  | `/api/me/seen-films/{slug}` | retirer un film déjà vu   |

- Authentification : un jeton personnel, généré depuis le profil, affiché
  une seule fois. Stocké sous forme de hash SHA-256, révocable, avec une
  date d'expiration. Envoyé dans l'en-tête `Authorization: Bearer`.
- Documentation OpenAPI générée par `nelmio/api-doc-bundle`.

## 6. Données Pathé

Source : la doc maintenue dans `pathe-fr-unofficial-api-doc` (69 chemins,
vérifiés le 4 octobre 2026).

### Endpoints utilisés

| Endpoint                                      | Usage                                                      |
|-----------------------------------------------|------------------------------------------------------------|
| `/cities`                                     | villes, position GPS, slugs des cinémas                    |
| `/cinemas`                                    | cinémas, adresses, GPS dans `theaters[].gpsPosition` (`x` = latitude, `y` = longitude) |
| `/shows`                                      | films : durée, genres, affiche, classification             |
| `/cinema/{slug}/shows`                        | quels films passent quels jours dans un cinéma             |
| `/show/{slug}/showtimes/{cinema}`             | toutes les séances d'un film dans un cinéma, par date (un appel couvre la semaine) |
| `/versions`                                   | référentiel des versions                                   |

### Synchronisation

- Périmètre configurable : liste de villes. Par défaut : `paris`, `lyon`
  et `dijon` (18 cinémas : 13 à Paris, 3 à Lyon, 2 à Dijon), soit environ
  700 requêtes et une douzaine de minutes à une requête par seconde.
- Ordre : villes → cinémas → films → programme de chaque cinéma → séances
  de chaque couple film × cinéma (un appel par couple couvre toute la
  semaine publiée).
- Les enregistrements sont mis à jour par slug (pas de suppression puis
  réinsertion). Les séances disparues de la fenêtre synchronisée sont
  supprimées.
- Une séance est identifiée de façon stable par le segment
  `V{vista}S{session}` de son lien de réservation `refCmd`.
- Politesse : User-Agent `PatheApiExplorer/1.0`, requêtes séquentielles,
  une par seconde au plus. Arrêt immédiat de la synchro sur un 403 ou un
  429.
- Planifiée toutes les 6 heures via Symfony Scheduler et Messenger
  (transport Doctrine), exécutée par le service `worker`.
- Les heures Pathé (`time`, `endTime`) n'ont pas de fuseau. Elles sont
  interprétées en Europe/Paris.

### Jeu de données figé

- `bin/console pathe:fixtures:record` enregistre les réponses brutes des
  villes configurées (Paris, Lyon, Dijon) dans `fixtures/pathe/`, avec
  leur date de capture. Trois profils : une grande ville où les trajets
  comptent, une ville moyenne, une petite ville où les programmes sont
  rares (cas « moins de 3 programmes »).
- `make fixtures` les rejoue avec un `MockHttpClient` à travers la même
  synchro. Les dates sont décalées pour que le premier jour capturé
  devienne aujourd'hui. L'application est ainsi utilisable n'importe quel
  jour, sans réseau.
- Le jeu de données est enregistré juste avant l'atelier et commité.

## 7. Sécurité (en place dès la v1)

Ces pratiques font partie du code de départ. L'atelier peut les montrer,
jamais les « réparer » :

- requêtes paramétrées partout (Doctrine) ;
- protection CSRF sur tous les formulaires et sur la déconnexion ;
- échappement Twig automatique, sans `|raw` sur des données externes ;
- paramètres OAuth `state` et PKCE gérés par la bibliothèque, redirections
  uniquement vers des routes internes ;
- liaison de compte uniquement sur un email vérifié ;
- jetons d'API générés avec `random_bytes()`, stockés hachés, comparés
  sur leur hash, jamais placés dans une URL ;
- aucun état propre à un utilisateur dans les services (worker mode) ;
- `#[\SensitiveParameter]` sur les secrets et les jetons ;
- secrets via variables d'environnement ou secrets Symfony, jamais
  commités. Les valeurs par défaut de Mercure (« ChangeMe ») sont
  remplacées.

## 8. Style de la v1

### Autorisé (ce que l'atelier fait évoluer)

- tableaux associatifs en entrée et en sortie des services, avec des
  docblocks `@param array` / `@return array` sans forme précise ;
- chaînes et entiers pour les concepts métier : versions, statuts,
  slugs, dates en `Y-m-d`, durées en minutes ;
- retours `X|false` ou `?X` pour signaler un échec ou une absence ;
- `\RuntimeException` et `\InvalidArgumentException` génériques, avec un
  message texte ;
- entités Doctrine avec getters, setters et propriétés nullables ;
- `match` sur des chaînes pour choisir un comportement ;
- `new \DateTimeImmutable()` et paramètres de configuration lus en chaînes
  dans les services ;
- `declare(strict_types=1)` absent ;
- tableaux passés aux templates Twig, `strict_variables` désactivé ;
- `JsonResponse` construite à partir de tableaux.

### Interdit

- toute faille de sécurité, tout bug connu ;
- logique dupliquée entre le web et l'API ;
- code mort, code mal formaté (PHP-CS-Fixer, règles `@Symfony`) ;
- tests rouges, erreurs PHPStan au niveau 5 ;
- noms trompeurs : le code doit rester lisible et honnête sur ce qu'il fait.

## 9. Pratiques et points d'ancrage

Chaque ligne indique où la pratique s'applique dans la v1. Le numéro
d'exercice renvoie à la section 10. « Montré » signifie que la pratique est
déjà en place (sécurité) ou n'est pas exécutable sur PHP 8.5.

| Pratique | PHP | Point d'ancrage v1 (forme naïve) | Cible | Ex. |
|---|---|---|---|---|
| Déclarations de type de classe et de tableau | 5.0/5.1 | `PatheMapper::mapCinema(array $raw)` | types précis, value objects | 1 |
| Exceptions SPL (`InvalidArgumentException`, `OutOfRangeException`…) | 5.1 | `\RuntimeException` partout | exceptions SPL dans les value objects | 1, 11 |
| `DateTimeImmutable` | 5.5 | `strtotime()` et minutes en entiers dans `ChainBuilder` | `ScreeningTime` | 3 |
| `finally` | 5.5 | verrou de synchro libéré dans chaque branche | `try/finally` | 11 |
| Constructeurs nommés | — | `new Coordinates($raw['x'], $raw['y'])` à chaque appel | `Coordinates::fromPathe()` | 1 |
| Variadiques typés | 5.6 | `new ShowtimeCollection(array $items)` | `ShowtimeList(Showtime ...$items)` | 12 |
| Typage scalaire + `strict_types` | 7.0 | fichiers sans `strict_types` ; `'3'` converti en `int` | `declare(strict_types=1)` partout | 13 |
| Types de retour | 7.0 | méthodes de service sans type de retour | retours typés | 13 |
| Hiérarchie `Throwable`/`Error`/`TypeError` | 7.0 | `catch (\Exception)` dans la synchro | `catch` ciblés | 11 |
| `random_bytes` / `random_int` | 7.0 | jetons d'API | — | montré |
| `assert()` et `zend.assertions` | 7.0 | invariants du planificateur non vérifiés | assertions en dev | 14 |
| Types nullables, `void`, `iterable` | 7.1 | `findBySlug(): ?array` | `find(): ?Film` / `get(): Film` | 6 |
| Visibilité des constantes de classe | 7.1 | `public const` partout dans `Planner` | `private const` | 13 |
| Multi-catch | 7.1 | blocs `catch` répétés | `catch (A \| B)` | 11 |
| `JSON_THROW_ON_ERROR` | 7.3 | `json_decode()` + test de `null` dans les fixtures | flag + exception | 11 |
| Propriétés typées | 7.4 | `/** @var string|null */` sur des propriétés | propriétés typées | 13 |
| Covariance et contravariance | 7.4 | interface de repository trop large | retours plus précis | 13 |
| Types union | 8.0 | `int|string $filmId` | `FilmSlug` | 1 |
| `mixed`, `static`, `never` | 8.0/8.1 | `fail(): void` suivi d'un `return null` | `fail(): never` | 13 |
| Promotion des propriétés du constructeur | 8.0 | DTO de sortie écrits à la main | promotion + `readonly` | 8 |
| `match` | 8.0 | `switch` sur les versions | `match` exhaustif sur un enum | 2 |
| `throw` en expression avec `??` | 8.0 | `if ($film === null) { throw … }` répété | `?? throw new FilmNotFound()` | 6 |
| Opérateur nullsafe | 8.0 | `$film->getContentRating() !== null ? $film->getContentRating()->getLabel() : null` dans Twig et l'API | `?->` ou Null Object | 14 |
| Arguments nommés | 8.0 | `new Showtime($a, $b, $c, $d, $e)` | arguments nommés | 1 |
| `get_debug_type`, `ValueError` | 8.0 | messages d'erreur construits avec `gettype()` | messages précis | 11 |
| Enums adossés, méthodes, `tryFrom()` | 8.1 | `'vf'`/`'vost'`, `'available'` en chaînes | `ShowtimeVersion`, `BookingStatus` | 2 |
| Propriétés `readonly` | 8.1 | `ScreeningTime` mutable | `readonly` | 8 |
| `new` dans les initialiseurs, Null Object | 8.1 | `?HubInterface $hub = null` + `if` | `NullPublisher` par défaut | 14 |
| Types intersection | 8.1 | `iterable $showtimes` | `Countable&IteratorAggregate` | 12 |
| Callables de première classe | 8.1 | `array_map([$this, 'map'], …)` | `$this->map(...)` | 12 |
| `array_is_list` | 8.1 | réponse Pathé `[]` ou objet indexé par date | normalisation à la frontière | 1 |
| Classes `readonly` | 8.2 | value objects avec une propriété oubliée | `final readonly class` | 8 |
| Types DNF, `true`/`false`/`null` autonomes | 8.2 | `array|false` en retour | type précis ou Result | 5 |
| `#[\SensitiveParameter]` | 8.2 | secrets OAuth, jetons | — | montré |
| Constantes de classe typées | 8.3 | `const DEFAULT_RADIUS = 10` | `const int DEFAULT_RADIUS = 10` | 13 |
| `#[\Override]` | 8.3 | implémentations de providers OAuth | `#[\Override]` | 9 |
| `json_validate()` | 8.3 | décodage pour tester la validité des fixtures | `json_validate()` | 11 |
| Clonage profond de `readonly` | 8.3 | programme cloné qui partage ses séances | `__clone` avec réaffectation | 8 |
| Exceptions de date précises | 8.3 | `DateTimeImmutable::createFromFormat()` + test de `false` | `DateMalformedStringException` | 3 |
| Property hooks | 8.4 | capacité `"244"` (chaîne Pathé) convertie partout | hook `set` ou value object | 7 |
| Visibilité asymétrique | 8.4 | `public array $films` modifiable sur `Programme` | `public private(set)` | 7 |
| `array_find`/`array_any`/`array_all` | 8.4 | `foreach` + `break` | fonctions natives | 12 |
| `#[\Deprecated]` | 8.4 | méthode de compatibilité gardée pendant le refactoring | `#[\Deprecated]` | 13 |
| Nullable implicite déprécié | 8.4 | évité en v1 (déprécié en 8.4) | — | montré |
| `BcMath\Number` | 8.4 | aucun usage monétaire en v1 | — | non retenu |
| Objets paresseux | 8.4 | utilisés par Symfony et Doctrine | — | montré |
| `#[\NoDiscard]` | 8.5 | `$programme->withShowtime($s);` résultat ignoré | `#[\NoDiscard]` sur withers et Result | 5 |
| `clone with` | 8.5 | withers écrits à la main | `clone($this, ['showtimes' => …])` | 8 |
| Opérateur pipe `\|>` | 8.5 | tableaux imbriqués dans le mapping Pathé | pipeline lisible | 15 |
| Extension URI | 8.5 | `preg_match` sur `refCmd` | `Uri\Rfc3986\Uri` | 15 |
| `array_first`/`array_last` | 8.5 | `reset()`/`end()` sur les séances | fonctions natives | 12 |
| Promotion de propriétés `final` | 8.5 | — | `final` sur les propriétés promues | 8 |
| `clamp()` | 8.6 (polyfill) | `max(1, min(50, $radius))` | `clamp()` dans `Radius` | 4 |
| Enum `SortDirection` | 8.6 (polyfill) | `'asc'`/`'desc'` en chaînes dans les listes | `\SortDirection` | 16 |
| `Time\Duration` | 8.6 (polyfill-time) | durées en minutes entières | `Time\Duration` | 3 |
| Valeurs par défaut `readonly`, application partielle | 8.6 | — | — | montré (slide) |
| Value objects auto-validés | — | slugs, coordonnées, rayon en scalaires | `CinemaSlug`, `Coordinates`, `Radius` | 1, 4 |
| Parse, don't validate (frontière) | — | formes Pathé incohérentes propagées | normalisation dans `Pathe` | 1 |
| Paire `find`/`get` | — | `findBySlug(): ?array` partout | `find(): ?X`, `get(): X` | 6 |
| Result pour les échecs attendus | — | `plan(): array\|false` | `PlanResult` | 5 |
| Hiérarchie d'exceptions par frontière | — | `RuntimeException('Erreur Pathé')` | `PatheApiException`, `BotBlockedException` | 11 |
| Tell, don't ask | — | `$showtime['status'] === 'available' && …` | `$showtime->isBookable($now)` | 7 |
| Immutabilité et withers | — | setters sur un programme partagé | withers | 8 |
| Entités avec invariants | — | `User` vide puis setters | constructeur nommé, pas de setter | 7, 10 |
| Booléen explicite plutôt que tableau | — | `AccountLinker::link(array $userInfo)` qui lit `$userInfo['email_verified'] ?? false` | `UserInfo` avec `VerifiedEmail` ou `UnverifiedEmail` | 10 |
| Extension par registre | — | `match ($provider)` | interface + services tagués | 9 |
| Entrées cachées | — | `new \DateTimeImmutable()` dans le service | `ClockInterface` | 14 |
| Configuration typée | — | `%env(PATHE_CITIES)%` découpé à la main | `%env(csv:…)%`, `%env(int:…)%` | 14 |
| DTO d'entrée validés | — | `$request->query->all()` | `#[MapQueryString]` + Validator | 4 |
| DTO de sortie et contrat d'API | — | `JsonResponse` construite à partir de tableaux | DTO + ObjectMapper, OpenAPI précis | 16 |
| Doctrine `enumType` et embeddables | — | colonnes `string`, latitude et longitude séparées | `enumType`, `Coordinates` embarqué | 7 |
| Twig `strict_variables` | — | tableaux et `default('')` | objets + `strict_variables` | 16 |
| Génériques PHPStan, formes de tableaux | — | `@return array` | `list<Showtime>`, `array{…}` | 12 |
| Niveau PHPStan et baseline | — | niveau 5 | niveau max, baseline réduite | 17 |
| Tests de mutation (Infection) | — | gardes non testées | gardes prouvées | 17 |

## 10. Parcours d'exercices

Chaque exercice part d'un point précis du code, a un objectif, et se
termine quand les tests passent à nouveau. Les exercices 1 à 6 forment le
parcours de 2 h. Les autres sont des extensions, à faire dans n'importe
quel ordre.

| # | Thème | Durée indicative |
|---|---|---|
| 1 | Frontière Pathé : value objects, normalisation des réponses | 25 min |
| 2 | Enums : versions et statuts de réservation | 15 min |
| 3 | Le temps : `ScreeningTime`, `Time\Duration` | 20 min |
| 4 | Entrée du planificateur : DTO, `#[MapQueryString]`, `Radius`, `FilmCount` | 20 min |
| 5 | Sortie du planificateur : collection typée, `PlanResult`, `#[\NoDiscard]` | 20 min |
| 6 | Repositories : `find`/`get`, exceptions métier | 15 min |
| 7 | Entités : invariants, visibilité asymétrique, property hooks, Doctrine | 45 min |
| 8 | Immutabilité : `readonly`, withers, `clone with`, clonage profond | 30 min |
| 9 | Providers OAuth : registre, `UserInfo` typé, `#[\Override]` | 40 min |
| 10 | Comptes : `UserInfo` typé à la liaison, email vérifié explicite, invariants de `User` | 30 min |
| 11 | Exceptions : hiérarchie Pathé, `catch` ciblés, `JSON_THROW_ON_ERROR` | 30 min |
| 12 | Collections : listes typées, `array_find`, génériques PHPStan | 30 min |
| 13 | Système de types : `strict_types`, retours, `never`, constantes typées | 30 min |
| 14 | Entrées cachées : horloge, configuration typée, Null Object, assertions | 30 min |
| 15 | Transformations : pipe `\|>`, extension URI | 20 min |
| 16 | Sortie : DTO d'API, ObjectMapper, `SortDirection`, Twig strict | 40 min |
| 17 | Outillage : PHPStan max, Infection | 30 min |

Total ≈ 7 h 50. Le guide formateur (`docs/exercices.md`) décrit pour
chaque exercice : le point de départ, l'objectif, les pièges, la version de
PHP concernée et le lien avec le deck.

## 11. Gestion des erreurs (v1)

- **Synchro** : une erreur réseau sur une requête est journalisée, puis
  la synchro passe au couple suivant. Un 403 ou un 429 arrête la synchro.
  La commande renvoie un code de sortie non nul en cas d'échec.
- **Planificateur** : une entrée invalide renvoie le formulaire avec ses
  erreurs (web) ou un 422 (API). L'absence de programme n'est pas une
  erreur : c'est un résultat vide accompagné d'un message.
- **Connexion** : un refus ou une erreur du provider renvoie vers la page
  de connexion avec un message. Aucun détail technique n'est affiché.
- **API** : erreurs en `application/problem+json`, sans trace.

## 12. Tests et qualité

- **PHPUnit** :
  - unitaires : règles d'enchaînement et notation du planificateur,
    mapping Pathé (sur les réponses du jeu de données figé), liaison de
    comptes ;
  - fonctionnels : parcours web, API, parité web/API, connexion via le
    fournisseur OIDC local (simulé dans les tests).
- Les tests ciblent surtout le comportement observable (HTTP, résultats du
  planificateur) pour rester valides pendant les refactorings.
- **PHPStan** : `phpstan.dist.neon` au niveau 5, sans erreur.
  `phpstan-max.neon` au niveau max avec une baseline, qui sert d'objectif
  de l'atelier.
- **PHP-CS-Fixer** : règles `@Symfony`.
- **Makefile** : `up`, `down`, `sh`, `console`, `composer`, `test`,
  `phpstan`, `cs`, `sync`, `fixtures`, `logs`.

## 13. Livrables du 8 octobre

1. Le dépôt `workshop` : application, Dockerfile, compose, Makefile, tests.
   Les participants reçoivent cette v1 complète et fonctionnelle (tag
   `v1.0.0`) comme point de départ ; les exercices la font évoluer.
2. Le jeu de données Pathé figé, enregistré juste avant l'atelier.
3. `README.md` (anglais) : démarrage, comptes OAuth facultatifs, fournisseur
   local.
4. `docs/exercices.md` (français) : le guide formateur des sections 9 et 10.

## 14. Risques et questions ouvertes

- **Délai** : 4 jours. Si le temps manque, on retire dans cet ordre la
  notification Mercure, puis Autocomplete (remplacé par une liste
  déroulante). Les trois connexions (Google, GitHub, local) restent dans
  tous les cas.
- **Comptes OAuth réels** : les participants ne peuvent pas tous créer des
  applications Google ou GitHub. Le provider local est le chemin par défaut.
  Les vrais providers fonctionnent si tu fournis des identifiants.
- **Pathé** : l'API n'est pas officielle et peut changer. Le jeu de données
  figé protège l'atelier.
- **Alpine et musl** : performances moindres que Debian, sans impact pour
  un usage local.
