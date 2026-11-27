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
| **Répertoire chants** | `CreateSong`, `UpdateSong`, `AttachSongToMass`, `ListSongsAction` (`GET /api/songs`, KAN-18 : recherche par titre ou auteur, 20 par page) |
| **Liturgie messes** | `CreateMass`, `AssignSongToMassPart`, `GeneratePptx` |
| **Membres & rôles** | `CreateUser`, `AssignRole`, `AssignMassRole`, `AssignVoiceAction` (`PUT /api/members/{member}/voice`, KAN-34 : voix seule, 422 sans fiche, voix précise effacée si le groupe change) |
| **Événements** | `CreateEvent`, `RegisterMember`, `CheckInMember` |
| **Paiements** | `CreateEventPayment`, `RecordUserPayment` |

---

### Rôles (KAN-35)

- « Gestion des rôles » (US-25) : `ListUsersWithRolesAction` (`GET /api/users?search=&page=`, 20 par page, rôle de
  chaque compte et `meta.role_counts`) et `AssignRoleAction` (`PUT /api/users/{user}/role`, `{ role }`).
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
  listes des membres et des rôles n'ont pas encore cette borne.
- Réponse : `SongListItemResource` (`id`, `title`, `author`, `reference` : ce que la liste affiche, comme
  `MemberListItemResource`), lue par `SongRepository::allForList()` (ces seules colonnes, sans tri SQL) ; le cantique complet
  reste servi par `SongResource`. `meta.total` suit la recherche, `meta.songs_count` (« 13 cantiques ») porte sur tout le
  répertoire. Recherche et tri passent par `Str::ascii` : « Cœur » se lit « coeur », « Æterna » « aeterna ».
- Accès : `SongPolicy::viewAny`, sur la route, ouvert aux trois rôles (membre, secrétaire, admin) ; un compte sans rôle
  reçoit un 403, un compte en attente le 403 `ACCOUNT_PENDING_VALIDATION` du middleware `account.active`.
- Autres routes `/api/songs` (relevé par la revue de KAN-18) : `DELETE /{song}` est **réservé à l'admin** par
  `DeleteSongRequest` (`SongPolicy::delete`, cantique chargé pour la Policy, 404 s'il n'existe pas) ; avant, tout compte
  validé pouvait supprimer un cantique. `POST` et `PUT` passent par la Policy dans les FormRequests de KAN-38 (PR de Kevin,
  non fusionnée au 27 novembre 2026 : sur `develop`, leur `authorize()` renvoie encore `true`, tout compte validé peut donc
  créer ou modifier un cantique tant qu'elle n'est pas fusionnée). `GET /{song}` n'a pas de contrôle de rôle : à poser avec
  la fiche (KAN-25). `AttachSongToMassRequest` et `SongController::attach` n'ont aucune route (code mort).
- Thèmes liturgiques (filtre `?theme=`, couleur) : reportés à KAN-23, dont le modèle n'est pas tranché (un ou plusieurs
  thèmes par cantique, liste fixe ou administrable, couleurs).
- `Song::uniqueIds()` renvoie `['uuid']` : sans lui, `HasUuids` écrivait l'UUID dans `id` (bigint) et toute création de
  cantique échouait sous PostgreSQL. Correction reprise à l'identique de la PR de KAN-38, pour une fusion sans conflit.
- Cantiques de démonstration : `php artisan db:seed --class=SongReferenceSeeder` (les 13 cantiques de la maquette, sans
  thème), tant que l'ajout d'un cantique (KAN-13) n'existe pas. Lancé à la main, jamais par `app:deploy` ; relancé, il ne
  crée que les titres absents et ne touche à aucun autre cantique. Requête Bruno `songs/list`.
- **Vérifié** : `ListSongsTest` (29 tests, dont « œ », un cantique sans auteur, le jeu de démonstration et la page trop
  grande), `DeleteSongTest` (5 tests), suite complète sous SQLite et sous PostgreSQL, appels réels sur la base de dev avec
  un compte membre (tri, recherche sans accents, compteur, 422, 401). **Non vérifié** : la préprod Railway, où le jeu de
  démonstration n'a pas été chargé.

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
