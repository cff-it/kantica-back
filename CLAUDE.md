# CLAUDE.md — Choralis Back-end

> Chorale Francophone de Faravohitra — Guide d'architecture pour Claude Code

---

## Workflow (vibe-coding)

- **Pas de plan d'implémentation** : implémenter directement la demande, sans rédiger de plan au préalable
  ni attendre de validation.
- **Plan de tests après implémentation** : une fois le code écrit, rédiger un plan de tests dans le dossier
  de la session (`session-tracking/<story>/<sous-ticket>/`), contenant :
  - les scénarios à tester (cas nominaux, cas d'erreur, cas limites) ;
  - pour chaque scénario : prérequis, étapes (requête HTTP, payload), résultat attendu (code HTTP, réponse, état en BDD) ;
  - les tests automatisés écrits ou à écrire (Feature / Unit).

---

## Stack technique

- **Framework** : Laravel
- **Pattern** : Action Pattern (`__invoke()`)
- **API** : Laravel Sanctum
- **Rôles & permissions** : Spatie Laravel-Permission
- **BDD** : PostgreSQL + Eloquent ORM

---

## Architecture des dossiers

```
app/
├── Http/
│   ├── Controllers/        # Reçoit la requête, délègue à une Action — rien d'autre
│   ├── Requests/           # Validation FormRequest uniquement
│   └── Resources/          # Formatage des réponses API (API Resources)
│
├── Actions/                # ⭐ Cœur métier — 1 classe = 1 action
│   ├── Song/
│   │   ├── CreateSong.php
│   │   ├── UpdateSong.php
│   │   └── AttachSongToMass.php
│   ├── Mass/
│   │   ├── CreateMass.php
│   │   └── GeneratePptx.php
│   ├── Event/
│   │   ├── CreateEvent.php
│   │   ├── RegisterMember.php
│   │   └── CheckInMember.php
│   └── User/
│       ├── CreateUser.php
│       └── AssignRole.php
│
├── Models/                 # Eloquent pur — zéro logique métier
├── Repositories/           # Requêtes BDD uniquement
├── DTOs/                   # Objets de transfert typés entre les couches
└── Policies/               # Autorisations Laravel
```

---

## Règles fondamentales

### 1. Le Controller ne fait que déléguer

```php
// ✅ Correct
public function store(CreateSongRequest $request, CreateSong $action): JsonResponse
{
    $song = $action($request->toDTO());
    return SongResource::make($song);
}

// ❌ Interdit — logique métier dans le controller
public function store(Request $request): JsonResponse
{
    $song = Song::create([...]);
    // traitement...
}
```

### 2. Les Actions sont des `readonly class` avec `__invoke()`

```php
// ✅ Correct
readonly class CheckInMember
{
    public function __construct(
        private EventRepository $events,
        private UserRepository $users,
    ) {}

    public function __invoke(CheckInDTO $dto): EventCheck
    {
        // toute la logique ici
    }
}

// ❌ Interdit — ne pas utiliser execute()
class CheckInMember
{
    public function execute(CheckInDTO $dto): EventCheck { ... }
}

// ❌ Interdit — class non readonly
class CheckInMember
{
    public function __construct(
        private EventRepository $events,
    ) {}
}
```

### 3. Une Action = une responsabilité

- Si une classe a besoin de **plusieurs méthodes publiques**, ce n'est plus une Action — c'est un **Service**
- Un Service a des méthodes nommées explicitement (`attach()`, `detach()`, etc.)
- Les Actions restent **atomiques et testables indépendamment**

### 4. Les Models ne contiennent pas de logique métier

```php
// ✅ Autorisé dans un Model
class Song extends Model
{
    protected $fillable = [...];
    protected $casts = [...];

    public function sections(): HasMany { ... }    // relation
    public function themes(): BelongsToMany { ... } // relation
}

// ❌ Interdit dans un Model
public function attachToMass(Mass $mass): void
{
    // logique métier — va dans une Action
}
```

### 5. Les DTOs typent les données entre les couches

```php
class CheckInDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly int $eventId,
        public readonly Carbon $checkedAt,
    ) {}
}

// Dans le FormRequest
public function toDTO(): CheckInDTO
{
    return new CheckInDTO(
        userId: $this->user()->id,
        eventId: $this->input('event_id'),
        checkedAt: now(),
    );
}
```

---

## Ce qui est interdit

| ❌ Interdit | ✅ Remplacer par |
|---|---|
| Logique métier dans les Controllers | Action |
| Logique métier dans les Models | Action |
| `execute()` dans les Actions | `__invoke()` |
| `class` simple pour une Action | `readonly class` |
| Fat Controller (>10 lignes par méthode) | Découper en Actions |
| DDD / CQRS / Clean Architecture complète | Action Pattern suffisant |
| Requêtes BDD dans les Controllers | Repository |

---

## Modules du projet

| Module | Actions principales |
|---|---|
| **Répertoire chants** | `CreateSong`, `UpdateSong`, `AttachSongToMass`, `ListSongsAction` (`GET /api/songs`, KAN-18 : recherche par titre ou auteur, 20 par page), `DeleteSongAction` (`DELETE /api/songs/{song}`, admin) |
| **Liturgie messes** | `CreateMass`, `AssignSongToMassPart`, `GeneratePptx` |
| **Membres & rôles** | `ListMembersAction` (`GET /api/members?search=&voice=&page=`, 20 par page, `page` bornée à 100 000 comme la liste des rôles : voir « Rôles (KAN-35) »), `CreateUser`, `AssignRole`, `AssignMassRole`, `AssignVoiceAction` (`PUT /api/members/{member}/voice`, KAN-34 : voix seule, 422 sans fiche, voix précise effacée si le groupe change) |
| **Événements** | `CreateEventAction` (`POST /api/events`, KAN-12 : type, intitulé, date, lieu, Secrétaire et Admin), `RegisterMember`, `CheckInMember`, `ListAttendanceOverviewAction` (`GET /api/events/attendance-overview`, KAN-12 : choix de l'événement en trois groupes), `ListEventAttendancesAction` (`GET /api/events/{event}/attendances`, KAN-12 : liste de saisie des présences), `RecordAttendancesAction` (`PUT /api/events/{event}/attendances`, KAN-12 : liste complète des présents, synchronisée) |
| **Paiements** | `CreateEventPayment`, `RecordUserPayment` |

---

### Rôles (KAN-35)

- « Gestion des rôles » (US-25) : `ListUsersWithRolesAction` (`GET /api/users?search=&page=`, 20 par page, rôle de
  chaque compte et `meta.role_counts`) et `AssignRoleAction` (`PUT /api/users/{user}/role`, `{ role }`).
- `page` est bornée à 100 000 dans `ListUsersRequest` et `ListMembersRequest` (422 « La page demandée est trop
  grande. »), comme `ListSongsRequest` (répertoire, KAN-18) : sans borne, `PHP_INT_MAX` passait la validation et le
  décalage de la page (`forPage`, `array_slice`) n'était plus un entier, d'où une erreur 500. Règles, messages et
  lecture de `page` sont communs aux listes paginées (membres, rôles, historique des imports, répertoire, et les
  événements « Déjà saisis » de KAN-12) : trait `ValidatesPage` (`app/Http/Requests/Concerns/`), avec les mêmes messages
  partout (« La page doit être un nombre entier. », « … supérieure ou égale à 1. », « La page demandée est trop
  grande. »). `ListPageTest` vérifie les cinq routes ensemble ; une nouvelle liste paginée reprend ce trait.
  **Vérifié** : `ManageRolesTest` et `ListMembersTest` (page 100 000 vide, 100 001 et `PHP_INT_MAX` refusées ; ces
  tests finissaient en 500 avant la borne), sous SQLite et PostgreSQL, et contre le back de dev (les deux routes).
- Même borne pour l'historique des imports (`ListUsersImportsRequest`, `GET /api/users/import?page=`, paginé en SQL).
  Sans elle, pas d'erreur 500 : le Query Builder convertit le décalage (un flottant) en entier, modulo 2^64, donc la
  page renvoyée était arbitraire ; avec `PHP_INT_MAX`, le décalage valait 0 et la réponse donnait les premiers
  imports. **Vérifié** : `ListUsersImportsTest` (ce comportement constaté avant la borne sous SQLite et PostgreSQL,
  puis 422), et contre le back de dev.
- La fiche membre (`GET /api/members/{id}`, `UserRepository::findRecordById`) renvoie aussi `roles` : la
  secrétaire voit le rôle, seul l'admin peut le changer (front : ligne « Rôle » de la fiche).
- Trois rôles Spatie (`RoleName` : `admin`, `secretaire`, `membre`), créés par `SyncRolesAction` (`RoleSeeder`,
  `app:deploy`). **Un seul rôle par compte** (`syncRoles`) ; `RoleName::highestOf()` donne le plus élevé pour les
  anciens comptes qui en ont plusieurs. Pas de permissions Spatie : le front se base sur les rôles de `GET /api/me`.
- Toutes les routes `/api/users` (hors import) sont **réservées à l'admin** (`UserPolicy`, appelée avec la classe
  pour que le 403 passe avant le 404). L'ancien `POST /api/users/{user}/roles`, sans contrôle de rôle, est supprimé.
- Garde-fous de `AssignRoleAction`, dans une transaction : le compte administrateur originel (`SUPER_ADMIN_MAIL`)
  garde le rôle Admin ; le dernier admin ne peut pas perdre le sien. Le verrou (ligne du rôle Admin, `lockRoleChanges`)
  est pris **avant** de lire le compte : deux changements simultanés passent l'un après l'autre. SQLite l'ignore :
  `ManageRolesTest` vérifie l'ordre des appels, et la requête `FOR UPDATE` quand la suite tourne sous PostgreSQL (CI).
- `meta.role_counts` ne compte que les comptes affichés (sans le compte originel), alors que la garde « dernier admin »
  compte le compte originel : avec lui et un seul autre admin, l'écran affiche « 1 admin » et cet admin peut quitter
  le rôle. Voulu : le compte originel est un vrai admin, masqué de la liste.

### Répertoire (KAN-18)

- Liste du répertoire (US-04) : `ListSongsAction` (`GET /api/songs?search=&page=`, `ListSongsRequest`), 20 cantiques par
  page, triés par titre sans tenir compte de la casse ni des accents (« Ô filii » est rangé avec les O). Comme pour les
  membres, recherche, tri et pagination sont faits en PHP sur tout le répertoire : même résultat sous PostgreSQL et SQLite.
- `search` : titre ou auteur, sans casse ni accents, tous les mots doivent se retrouver ; 100 caractères au plus. La
  référence et les paroles ne sont pas cherchées (champ « Titre ou auteur » de la maquette). `page` est bornée à 100 000 :
  sans borne, `PHP_INT_MAX` passait la validation et le décalage de la page (`forPage`) finissait en erreur 500. Les
  listes des membres, des rôles et de l'historique des imports ont la même borne (voir « Rôles (KAN-35) »).
- Réponse : `SongListItemResource` (`id`, `title`, `author`, `reference` : ce que la liste affiche, comme
  `MemberListItemResource`), lue par `SongRepository::allForList()` (ces seules colonnes, sans tri SQL) ; le cantique complet
  reste servi par `SongResource`. `meta.total` suit la recherche, `meta.songs_count` (« 13 cantiques ») porte sur tout le
  répertoire. Recherche et tri passent par `Str::ascii` : « Cœur » se lit « coeur », « Æterna » « aeterna ».
- Accès : `SongPolicy::viewAny`, sur la route, ouvert aux trois rôles (membre, secrétaire, admin) ; un compte sans rôle
  reçoit un 403, un compte en attente le 403 `ACCOUNT_PENDING_VALIDATION` du middleware `account.active`.
- Droits des autres routes `/api/songs` (relevés par les revues de KAN-18) : chaque route contrôle le rôle elle-même
  avec la classe (`->can(..., Song::class)`), donc **403 avant tout 404**. `GET /{song}` : même droit que la liste
  (`viewAny`, `SongPolicy::view` aligné). `POST /`, `PUT /{song}` et `DELETE /{song}` : **admin seulement**
  (`create`, `update`, `delete` avec `RoleName::Admin` ; KAN-13 : « Un utilisateur non admin ne peut pas créer de
  cantique », modification « uniquement pour l'Admin »). Avant, un compte sans rôle pouvait lire chaque cantique, et tout
  compte validé pouvait en créer, modifier ou supprimer.
- Défense en profondeur : les FormRequests contrôlent aussi le rôle, après la route. `CreateSongRequest` et
  `DeleteSongRequest` appellent la Policy avec la classe (`SongPolicyTest` le vérifie hors des routes) ;
  `UpdateSongRequest` (KAN-38) charge le cantique de la route et l'appelle avec lui (couverte par
  `SaveSongAuthorizationTest`). `view`, `update` et `delete` prennent donc le cantique en option (`?Song $song = null`),
  aucun droit n'en dépendant encore ; `SongPolicyTest` vérifie que les deux appels donnent la même réponse.
- Les routes `/{song}` limitent l'identifiant à 18 chiffres (`where` sur le groupe `songs`, `[0-9]{1,18}`, sous
  `PHP_INT_MAX`) : un identifiant non numérique ou trop grand donne un 404 au lieu d'une erreur 500 sur le paramètre
  `int` du contrôleur (`whereNumber` laissait passer `99999999999999999999`). Cette contrainte remplace les
  `whereNumber` de KAN-38 (rebase de sa branche sur `develop`, 28 novembre 2026) ; elle garantit aussi l'identifiant
  que lit `UpdateSongRequest`.
- Suppression : `DeleteSongAction` reçoit l'identifiant, lit le cantique une seule fois (404 s'il n'existe pas, pour
  l'admin) et le supprime par le modèle, pour que les événements Eloquent (et les sections de KAN-38) suivent ; une
  suppression directe par identifiant éviterait la lecture mais les court-circuiterait. Le rôle « responsable » que
  citait `SongPolicy` n'existe pas : règle effective inchangée.
  `AttachSongToMassRequest` et `SongController::attach` n'ont aucune route (code mort).
- Thèmes liturgiques (filtre `?theme=`, couleur) : reportés à KAN-23, dont le modèle n'est pas tranché (un ou plusieurs
  thèmes par cantique, liste fixe ou administrable, couleurs).
- `Song::uniqueIds()` renvoie `['uuid']` : sans lui, `HasUuids` écrivait l'UUID dans `id` (bigint) et toute création de
  cantique échouait sous PostgreSQL. Correction reprise à l'identique de la PR de KAN-38, pour une fusion sans conflit.
- Cantiques de démonstration : `php artisan db:seed --class=SongReferenceSeeder` (les 13 cantiques de la maquette, sans
  thème), tant que l'ajout d'un cantique (KAN-13) n'existe pas. Lancé à la main, jamais par `app:deploy` ; relancé, il ne
  crée que les titres absents et ne touche à aucun autre cantique. Requête Bruno `songs/list`.
- **Vérifié** : `ListSongsTest` (29 tests, dont « œ », un cantique sans auteur, le jeu de démonstration et la page trop
  grande), `DeleteSongTest` (10 tests, dont le 403 avant le 404, l'identifiant non numérique ou trop grand et une seule
  lecture du cantique), `ShowSongTest` (7 tests), `SaveSongAuthorizationTest` (10 tests : admin, autres rôles, sans rôle, invité,
  compte en attente), `SongPolicyTest` (appels avec et sans le cantique, contrôle des FormRequests), suite
  complète sous SQLite et sous PostgreSQL, appels réels sur la base de dev avec un compte membre (tri, recherche sans
  accents, compteur, 422, 401) et avec l'admin (suppressions 204, cantique inconnu 404, lecture d'un cantique 200).
  **Non vérifié** : la préprod Railway, où le jeu de démonstration n'a pas été chargé.

### Sections d'un cantique (KAN-38)

- Les sections (`SectionType` : `couplet`, `refrain`, `pont`) se saisissent avec le cantique : `sections: [{id?, type, number?, text}]`
  sur `POST /api/songs` et `PUT /api/songs/{song}`. `SyncSongSectionsAction` aligne les sections sur la liste (crée sans `id`,
  met à jour avec `id`, supprime les absentes), dans la transaction de `CreateSongAction` et `UpdateSongAction`. La position est
  le rang dans la liste ; le numéro (Couplet 1, 2, 3) est saisi, facultatif, jamais calculé. À la modification, `sections` absent
  ou `null` laisse les sections inchangées, `[]` les supprime toutes.
- Une section utilisée dans l'ordre d'une messe (`mass_song_sections`) ne peut pas être supprimée (422, rien n'est enregistré) :
  la cascade SQL la retirerait en silence de messes déjà préparées ou chantées. Choix à confirmer avec la présidente avant US-18.
  Les sections sont verrouillées avant ce contrôle et le cantique l'est à la modification (`findByIdForUpdate`), pour que deux
  modifications simultanées passent l'une après l'autre.
- Bornes : 100 sections par cantique, 5000 caractères par texte de section, 2000 pour `notes`.
- `GET /api/songs/{song}` ajoute `usage` : `ShowSongAction`, `SongUsageRepository` (jointures et tri en SQL, messes jusqu'à la
  date du jour comprise) et `SongDetailResource`. `times_sung` compte toutes les utilisations, `history` garde les 50 plus
  récentes. La liste, l'ajout et la modification ne renvoient pas `usage`.
- `Song` et `SongSection` ciblent la colonne `uuid` (`uniqueIds()`) : `HasUuids` visait sinon la clé `id`, entière. Huit autres
  modèles ont le même défaut (`Libretto`, `Prayer`, `UserPayment`, `SongLibretto`, `LinkType`, `MassPart`, `SongLink`,
  `SongTheme`), non corrigé ici.

### Présences (KAN-12)

- Saisie des présences d'un événement (US-11) : `ListEventAttendancesAction` (`GET /api/events/{event}/attendances`) et
  `RecordAttendancesAction` (`PUT /api/events/{event}/attendances`, `{ present_member_ids: number[] }`). Les deux
  réponses ont le même format (`EventAttendanceResource`) : `data.event` (`id`, `name`, `date_start`), `present_count`,
  `total_count` (« 5 / 9 présents ») et `members` (`id`, `last_name`, `first_name`, `voice_group`, `voice`, `present`).
- Accès : **Secrétaire et Admin** (`EventPolicy::manageAttendances`, appelée avec la classe sur la route : 403 avant tout
  404 ; `RecordAttendancesRequest` le vérifie aussi). `{event}` est limité à 18 chiffres (`where`, comme `{song}`) : un
  identifiant non numérique ou trop grand donne un 404.
- Modèle : la table `event_checks` du MCD (`EventCheck`) porte la présence, sans nouvelle table. La migration
  `2026_11_28_000000` ajoute l'unicité (`event_id`, `user_id`), après avoir réduit les doublons éventuels à la première
  ligne. `date_in` est l'heure de la première coche, `date_out` reste nul, et une présence maintenue n'est jamais
  réécrite.
- Lecture : tous les membres **actifs et les comptes en attente** (`MemberRepository::allAttendees()`, réponse « oui » du
  ticket : la maquette en montre un), sans le compte administrateur originel, triés comme la liste des membres
  (`MemberSortKey`, partagé avec `ListMembersAction`). `present_count` ne compte que les membres listés, pour ne jamais
  dépasser `total_count`.
- Enregistrement : le front envoie la liste complète des présents. Synchronisation dans une transaction : présences
  manquantes ajoutées, présences décochées supprimées. Idempotente, `[]` décoche tout. Le verrou (ligne de l'événement,
  `findForUpdate`) est pris **avant** de lire les présences : deux saisies simultanées passent l'une après l'autre. SQLite
  l'ignore : `EventAttendancesTest` vérifie la requête `FOR UPDATE` quand la suite tourne sous PostgreSQL (CI).
- Seuls les membres de la liste (actifs et en attente) sont concernés : un identifiant inconnu ou de l'administrateur
  originel donne un 422 sur `present_member_ids` et rien n'est écrit ; une présence d'un compte hors liste n'est jamais
  supprimée. Un identifiant en double donne un 422 (`distinct`), 1000 identifiants au plus. Chaque enregistrement réussi
  pose `events.attendances_recorded_at` (voir « Choix et création des événements »).
- `CheckInMemberAction` (`POST /api/events/{event}/check-in`) refuse maintenant toute présence existante, sortie
  enregistrée ou non : avec la contrainte d'unicité, un second pointage après `date_out` donnerait une erreur 500. Il
  prend le même verrou que la saisie (`findForUpdate`, dans une transaction, avant de chercher le doublon) et convertit
  une violation d'unicité en 422 ; `addAttendances` écrit en `insertOrIgnore`. **Toute écriture dans `event_checks` doit
  prendre ce verrou** : sinon une course avec la saisie donnerait une erreur 500.
- Points du ticket tranchés (réponses du ticket) : comptes en attente dans la liste, saisie corrigeable à tout moment (le
  `PUT` est idempotent), saisie réservée à la Secrétaire et à l'Admin pour l'instant. Restent ouverts : pointage par le
  membre lui-même (non pour l'instant), absences excusées (non).
- Hors périmètre : `POST /api/events/{event}/check-in` n'a aucun contrôle de rôle (le pointage par le membre lui-même est
  un point ouvert du ticket).
- Requêtes Bruno `events/attendances-get` et `events/attendances-put`.
- **Vérifié** : `EventAttendancesTest` (37 tests : lecture, tri, comptes en attente listés et acceptés, compte originel
  refusé, droits, 403 avant 404, identifiants invalides, première saisie, correction, idempotence, liste vide, présence
  écrite entre la lecture et l'écriture, contrainte d'unicité, migration des doublons) et `CheckInMemberTest` (4 tests :
  doublon, pointage après une sortie, verrou pris dans une transaction avant le doublon, course convertie en 422).
  **Non vérifié** : PostgreSQL (verrou `FOR UPDATE`, migrations), laissé à la CI ; appels réels sur le back de dev.

### Choix et création des événements (KAN-12, ajout du 28/11)

- Modèle : `events` gagne `type` (`EventType` : `repetition`, `messe`, `concert`, `autre` ; défaut `autre` pour les lignes
  existantes), `location` (255, facultatif) et `attendances_recorded_at`, avec un index `(type, date_start)`. La
  migration `2026_11_28_100000` marque aussi « saisis » les événements qui ont déjà des présences (date de la dernière
  présence écrite). `attendances_recorded_at` n'est pas modifiable en masse : seule `RecordAttendancesAction` le pose, à
  chaque enregistrement, **même à 0 présent**. C'est ce champ, et non le nombre de présences, qui fait passer un
  événement en « Déjà saisis ». `EventFactory` (états `ofType`, `startingAt`, `recorded`) sert aux tests.
- Fuseau : `app.timezone` reste en UTC (les dates déjà en base sont en UTC : le changer les décalerait toutes). « Le jour
  de l'événement » se lit dans `config('kantica.timezone')` (variable `KANTICA_TIMEZONE`, défaut `Indian/Antananarivo`,
  UTC+3), via `App\Support\ChoirTime`. Une date **sans décalage** envoyée à `POST /api/events` se lit dans ce fuseau
  (« 2026-12-05 17:00 » est enregistrée 14 h UTC) ; avec un décalage (`+03:00`, `Z`) il est respecté. Les réponses
  restent en UTC (ISO 8601, `+00:00`) : le front convertit à l'affichage.
- Règle du jour J : `GET` et `PUT /api/events/{event}/attendances` répondent 422 (clé `event`, « Les présences se
  saisissent à partir du jour de l'événement. ») si `date_start` est après la fin de la journée en cours dans le fuseau
  de la chorale (`EnsureAttendancesOpenAction`). Les événements passés restent saisissables et corrigeables ; le 403 et
  le 404 passent avant.
- Création : `POST /api/events` (`CreateEventAction`) : `type`, `name` et `date_start` requis ; `location`, `date_end`,
  `need_payment` et `payment_amount` facultatifs. **Secrétaire et Admin** (`EventPolicy::create`, `->can('create',
  Event::class)` sur la route, contrôlée aussi par `CreateEventRequest`), à confirmer avec la présidente. Avant, la route
  n'avait aucun contrôle de rôle (tout compte connecté créait un événement) et la Policy citait un rôle « responsable »
  absent de `RoleName`. `update` et `manageRegistrations` le citent encore (aucune route : non touchées).
  `EventResource` renvoie `type` et `location`.
- Aperçu : `GET /api/events/attendance-overview?type=&page=` (`ListAttendanceOverviewAction`, Secrétaire et Admin,
  déclarée **avant** `GET /{event}`, qui n'a pas de contrainte numérique). `data` : `to_record` (sans saisie, jusqu'à la
  fin de la journée en cours, du plus récent au plus ancien), `upcoming` (sans saisie, après aujourd'hui, du plus proche,
  **10 au plus**), `recorded` (saisie posée, du plus récent, **20 par page**, `page` bornée comme les autres listes). Les
  trois groupes forment une partition : un événement saisi est toujours dans `recorded`, même daté du futur. Chaque
  élément : `id`, `name`, `type`, `date_start`, `location` ; `recorded` ajoute `present_count` (présences des membres de
  la liste de saisie seulement) et `total` (effectif de la liste). `meta` : `counts` (`to_record`, `upcoming`,
  `recorded`, les **totaux** de chaque groupe : `upcoming` peut dépasser les 10 lignes affichées), `page`, `per_page`,
  `last_page`. `?type=` filtre les trois groupes et les décomptes ; sans lui, tous les types. Cinq requêtes quel que soit
  le nombre d'événements (testé).
- Démonstration : `php artisan db:seed --class=EventReferenceSeeder` (8 événements aux dates relatives à aujourd'hui : 2
  saisis avec des présences, 3 à saisir dont un du jour, 3 à venir). À la main, jamais par `app:deploy` ; relancé, il ne
  crée que les intitulés absents.
- Requêtes Bruno `events/create` et `events/attendance-overview`.
- À confirmer avec la présidente : qui crée les événements (Secrétaire et Admin pour l'instant) ; `total` de « 8 / 9
  présents » = effectif actuel et non celui de la date de l'événement. Modification et suppression d'un événement : pas
  maquettées, aucune route.
- **Vérifié** : `CreateEventTest` (25 tests), `AttendanceOverviewTest` (21 : groupes, tri, bornes du jour à la seconde,
  décomptes, filtre, plafond de 10, pagination, nombre de requêtes constant, droits), `AttendanceDayRuleTest` (15 : règle
  du jour J dans le fuseau de la chorale, `attendances_recorded_at`), `EventColumnsMigrationTest` (2),
  `EventReferenceSeederTest` (5) et `ListPageTest` (cinq routes), suite complète sous SQLite (837 réussis, 1 ignoré).
  **Non vérifié** : PostgreSQL (migration, requêtes de l'aperçu), laissé à la CI ; appels réels sur le back de dev.

## Conventions de nommage

- **Actions** : verbe + nom + suffixe `Action` → `CreateSongAction`, `CheckInMemberAction`, `GeneratePptxAction`
- **DTOs** : nom + DTO → `CheckInDTO`, `CreateSongDTO`
- **Repositories** : nom + Repository → `SongRepository`, `EventRepository`
- **Policies** : nom + Policy → `SongPolicy`, `EventPolicy`
- **Resources** : nom + Resource → `SongResource`, `UserResource`

---

## Déploiement (préprod Railway)

Le service `kantica-back` du projet Railway `kantica` (environnement `preprod`) se construit avec
`Dockerfile.railway`, pas avec le `Dockerfile` racine (réservé au docker-compose local), et suit `develop`.

- Un seul conteneur : nginx, PHP-FPM et le worker `queue:work`, lancés par supervisord (`docker/railway/`). Le
  worker reste dans le conteneur du web parce qu'ils partagent les fichiers d'import (disque `local`).
- À chaque démarrage, `docker/railway/entrypoint.sh` lance `php artisan app:deploy` (migrations et données de
  référence) puis `php artisan optimize`. Un échec arrête le déploiement.
- Un volume est monté sur `storage/app` (photos des membres, fichiers d'import).
- Variables à définir sur le service : `APP_KEY`, `APP_URL` (domaine public du back), `DB_*`, et
  `SUPER_ADMIN_MAIL` / `SUPER_ADMIN_PASSWORD` tant qu'aucun admin n'existe.
- Les mails partent dans les logs (`MAIL_MAILER=log`) tant qu'aucun SMTP n'est configuré.

Vérifié en local (image construite, migrations, compte admin, connexion, worker démarré). L'envoi réel de mails
et l'import de membres sur Railway ne sont pas vérifiés.

## Style de code (Laravel Pint)

Le style est vérifié en CI par `vendor/bin/pint --test` (job `lint` de `.github/workflows/ci.yml`).
La configuration est dans `pint.json` : preset `laravel` + les ajustements ci-dessous.

- **Avant chaque commit** : lancer `vendor/bin/pint` (corrige) ou `vendor/bin/pint --test` (vérifie seulement).
- Ne pas modifier `pint.json` pour faire passer un fichier : corriger le code.

### Règles propres au projet (écarts au preset Laravel)

| Règle | Convention | Exemple |
|---|---|---|
| Alignement `=>` et `=` | Libre : l'alignement manuel est conservé, Pint n'y touche pas | `'title'  => $dto->title,` |
| Virgule finale sur une ligne | Autorisée (et recommandée) | `[1, 2, 3,]` |
| Espace après la virgule finale | Aucun | `['--force' => true,]` |
| Opérateur `!` | Pas d'espace après | `if (!$user)` |
| Instanciation | Toujours avec parenthèses | `new MailMessage()` |
| Concaténation `.` | Sans espaces | `$a.$b` |

### Règles du preset Laravel appliquées (à respecter)

- Imports triés par ordre alphabétique, aucun import inutilisé.
- Pas de nom de classe complet dans le code (`\Foo\Bar::class`) : ajouter un `use`.
- Appels chaînés sur plusieurs lignes : indentés de 4 espaces par rapport à la ligne de départ.

```php
return $this->belongsToMany(Libretto::class, 'song_libretti')
    ->withPivot('position')
    ->orderByPivot('position');
```

- Closure multi-lignes passée en argument : un argument par ligne.

```php
'subscriptions' => $this->whenLoaded(
    'subscriptions',
    fn () => $this->subscriptions->map(fn ($s) => [
        'user_id'  => $s->user_id,
        'has_paid' => $s->has_paid,
    ]),
),
```

---

## Principes SOLID appliqués

- **S** — chaque Action a une seule responsabilité
- **O** — étendre via de nouvelles Actions, ne pas modifier les existantes
- **L** — les DTOs sont substituables
- **I** — pas d'interfaces imposées inutilement
- **D** — injection de dépendances via le container Laravel
