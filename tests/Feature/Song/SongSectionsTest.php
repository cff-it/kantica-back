<?php

namespace Tests\Feature\Song;

use App\Actions\Song\SyncSongSectionsAction;
use App\DTOs\Song\SongSectionDTO;
use App\Enums\RoleName;
use App\Enums\SectionType;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// KAN-38 : sections (couplets et refrains) d'un cantique, enregistrées avec le cantique (POST et PUT /api/songs)
class SongSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));
    }

    /**
     * @param  list<array{0: SectionType, 1: string, 2?: int}>  $sections  [type, texte, numéro facultatif] dans l'ordre de saisie
     */
    private function song(array $sections = [], string $title = 'Gloria'): Song
    {
        $song = Song::create(['title' => $title,]);

        foreach ($sections as $position => $section) {
            $song->sections()->create([
                'type'     => $section[0],
                'lyrics'   => $section[1],
                'number'   => $section[2] ?? null,
                'position' => $position,
            ]);
        }

        return $song;
    }

    /**
     * @param  mixed  $sections  null : la clé `sections` n'est pas envoyée
     * @return array<string, mixed>
     */
    private function payload(mixed $sections, string $title = 'Gloria'): array
    {
        return $sections === null ? ['title' => $title,] : ['title' => $title, 'sections' => $sections,];
    }

    /**
     * Sections enregistrées du cantique, dans l'ordre de leur position : [type, texte, position].
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function storedSections(Song $song): array
    {
        return $song->sections()->get()
            ->map(fn ($section) => [$section->type->value, $section->lyrics, $section->position,])
            ->all();
    }

    /**
     * Numéros saisis des sections du cantique, dans l'ordre de leur position (null : section sans numéro).
     *
     * @return list<int|null>
     */
    private function storedNumbers(Song $song): array
    {
        return $song->sections()->pluck('number')->all();
    }

    /**
     * @return list<int>
     */
    private function sectionIds(Song $song): array
    {
        return $song->sections()->pluck('id')->all();
    }

    // ── Ajout d'un cantique avec ses sections ───────────────────────────────

    public function test_an_admin_creates_a_song_with_its_sections_in_the_order_entered(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/songs', $this->payload([
            ['type' => 'couplet', 'text' => 'Gloire à Dieu'],
            ['type' => 'refrain', 'text' => 'Alléluia'],
            ['type' => 'couplet', 'text' => 'Paix sur la terre'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Gloria')
            ->assertJsonCount(3, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'couplet')
            ->assertJsonPath('data.sections.0.text', 'Gloire à Dieu')
            ->assertJsonPath('data.sections.0.position', 0)
            ->assertJsonPath('data.sections.1.type', 'refrain')
            ->assertJsonPath('data.sections.1.position', 1)
            ->assertJsonPath('data.sections.2.type', 'couplet')
            ->assertJsonPath('data.sections.2.position', 2);

        $this->assertTrue(Str::isUuid($response->json('data.uuid')));
        $this->assertTrue(Str::isUuid($response->json('data.sections.0.uuid')));

        $song = Song::findOrFail($response->json('data.id'));
        $this->assertSame([
            ['couplet', 'Gloire à Dieu', 0,],
            ['refrain', 'Alléluia', 1,],
            ['couplet', 'Paix sur la terre', 2,],
        ], $this->storedSections($song));
    }

    public function test_a_song_can_be_created_without_sections(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/songs', $this->payload(null))
            ->assertCreated()
            ->assertJsonPath('data.sections', []);

        $this->assertDatabaseCount('songs', 1);
        $this->assertDatabaseCount('song_sections', 0);
    }

    public function test_line_breaks_inside_the_text_are_kept(): void
    {
        $this->actingAsAdmin();
        $text = "Premier vers\nDeuxième vers\n\nTroisième vers";

        $response = $this->postJson('/api/songs', $this->payload([['type' => 'couplet', 'text' => $text,],]))
            ->assertCreated()
            ->assertJsonPath('data.sections.0.text', $text);

        $this->assertSame($text, Song::findOrFail($response->json('data.id'))->sections()->first()->lyrics);
    }

    public function test_a_new_song_cannot_reuse_the_id_of_an_existing_section(): void
    {
        $this->actingAsAdmin();
        $other = $this->song([[SectionType::Verse, 'Couplet existant',],]);

        $this->postJson('/api/songs', $this->payload([['id' => $other->sections()->value('id'), 'type' => 'couplet', 'text' => 'x',],]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.0.id' => 'Un nouveau cantique ne peut pas reprendre une section existante.',]);

        $this->assertDatabaseCount('songs', 1);
    }

    // ── Modification : ajout, modification, suppression, réordonnancement ───

    public function test_a_section_is_added_to_an_existing_song(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);
        [$first, $second] = $this->sectionIds($song);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $first, 'type' => 'couplet', 'text' => 'Un'],
            ['id' => $second, 'type' => 'refrain', 'text' => 'Deux'],
            ['type' => 'couplet', 'text' => 'Trois'],
        ], 'Gloria modifié'))
            ->assertOk()
            ->assertJsonPath('data.title', 'Gloria modifié')
            ->assertJsonCount(3, 'data.sections')
            ->assertJsonPath('data.sections.0.id', $first)
            ->assertJsonPath('data.sections.1.id', $second)
            ->assertJsonPath('data.sections.2.text', 'Trois');

        $this->assertSame([
            ['couplet', 'Un', 0,],
            ['refrain', 'Deux', 1,],
            ['couplet', 'Trois', 2,],
        ], $this->storedSections($song));
        $this->assertSame([$first, $second], array_slice($this->sectionIds($song), 0, 2));
    }

    public function test_a_section_is_modified_through_its_id(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Ancien texte',],]);
        $section = $song->sections()->first();

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $section->id, 'type' => 'refrain', 'text' => 'Nouveau texte'],
        ]))
            ->assertOk()
            ->assertJsonPath('data.sections.0.id', $section->id)
            ->assertJsonPath('data.sections.0.uuid', $section->uuid)
            ->assertJsonPath('data.sections.0.type', 'refrain')
            ->assertJsonPath('data.sections.0.text', 'Nouveau texte');

        $this->assertDatabaseCount('song_sections', 1);
        $this->assertSame([['refrain', 'Nouveau texte', 0,],], $this->storedSections($song));
    }

    public function test_sections_absent_from_the_list_are_deleted(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([
            [SectionType::Verse, 'Un',],
            [SectionType::Chorus, 'Deux',],
            [SectionType::Verse, 'Trois',],
        ]);
        [$first, , $third] = $this->sectionIds($song);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $first, 'type' => 'couplet', 'text' => 'Un'],
            ['id' => $third, 'type' => 'couplet', 'text' => 'Trois'],
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.sections');

        $this->assertSame([
            ['couplet', 'Un', 0,],
            ['couplet', 'Trois', 1,],
        ], $this->storedSections($song));
        $this->assertSame([$first, $third], $this->sectionIds($song));
    }

    public function test_sections_are_reordered_by_the_order_of_the_list(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([
            [SectionType::Verse, 'Un', 1,],
            [SectionType::Chorus, 'Refrain',],
            [SectionType::Verse, 'Deux', 2,],
        ]);
        [$verseOne, $chorus, $verseTwo] = $this->sectionIds($song);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $verseTwo, 'type' => 'couplet', 'number' => 2, 'text' => 'Deux'],
            ['id' => $verseOne, 'type' => 'couplet', 'number' => 1, 'text' => 'Un'],
            ['id' => $chorus, 'type' => 'refrain', 'text' => 'Refrain'],
        ]))
            ->assertOk()
            ->assertJsonPath('data.sections.0.id', $verseTwo)
            ->assertJsonPath('data.sections.1.id', $verseOne)
            ->assertJsonPath('data.sections.2.id', $chorus)
            // La numérotation est manuelle : réordonner ne renumérote pas, chaque section garde le numéro envoyé
            ->assertJsonPath('data.sections.0.number', 2)
            ->assertJsonPath('data.sections.1.number', 1)
            ->assertJsonPath('data.sections.2.number', null);

        $this->assertSame([
            ['couplet', 'Deux', 0,],
            ['couplet', 'Un', 1,],
            ['refrain', 'Refrain', 2,],
        ], $this->storedSections($song));
        $this->assertSame([2, 1, null], $this->storedNumbers($song));
        $this->assertSame([$verseTwo, $verseOne, $chorus], $this->sectionIds($song));
    }

    public function test_adding_modifying_deleting_and_reordering_can_be_saved_together(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([
            [SectionType::Verse, 'A',],
            [SectionType::Chorus, 'B',],
            [SectionType::Verse, 'C',],
        ]);
        [$a, $b, $c] = $this->sectionIds($song);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $c, 'type' => 'couplet', 'text' => 'C modifié'],
            ['type' => 'refrain', 'text' => 'D nouveau'],
            ['id' => $a, 'type' => 'couplet', 'text' => 'A'],
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data.sections');

        $this->assertSame([
            ['couplet', 'C modifié', 0,],
            ['refrain', 'D nouveau', 1,],
            ['couplet', 'A', 2,],
        ], $this->storedSections($song));
        $this->assertDatabaseMissing('song_sections', ['id' => $b,]);
        $this->assertDatabaseCount('song_sections', 3);
    }

    public function test_omitting_the_list_keeps_the_existing_sections(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);

        $this->putJson("/api/songs/{$song->id}", $this->payload(null, 'Nouveau titre'))
            ->assertOk()
            ->assertJsonPath('data.title', 'Nouveau titre')
            ->assertJsonCount(2, 'data.sections');

        $this->assertSame([['couplet', 'Un', 0,], ['refrain', 'Deux', 1,],], $this->storedSections($song));
    }

    public function test_an_empty_list_deletes_all_the_sections(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);

        $this->putJson("/api/songs/{$song->id}", $this->payload([]))
            ->assertOk()
            ->assertJsonPath('data.sections', []);

        $this->assertDatabaseCount('song_sections', 0);
        $this->assertDatabaseCount('songs', 1);
    }

    public function test_a_null_list_keeps_the_existing_sections(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);

        $this->putJson("/api/songs/{$song->id}", ['title' => 'Nouveau titre', 'sections' => null,])
            ->assertOk()
            ->assertJsonPath('data.title', 'Nouveau titre')
            ->assertJsonCount(2, 'data.sections');

        $this->assertSame([['couplet', 'Un', 0,], ['refrain', 'Deux', 1,],], $this->storedSections($song));
    }

    // Une section renvoyée telle quelle n'est pas réécrite : seule la section modifiée coûte une requête UPDATE
    public function test_only_the_sections_that_changed_are_written(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un', 1,], [SectionType::Chorus, 'Deux',],]);
        [$verse, $chorus] = $this->sectionIds($song);

        DB::enableQueryLog();
        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $verse, 'type' => 'couplet', 'number' => 1, 'text' => 'Un'],
            ['id' => $chorus, 'type' => 'refrain', 'text' => 'Deux modifié'],
        ]))->assertOk();
        $updates = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'update "song_sections"'));
        DB::disableQueryLog();

        $this->assertCount(1, $updates);
        $this->assertSame([['couplet', 'Un', 0,], ['refrain', 'Deux modifié', 1,],], $this->storedSections($song));
    }

    public function test_a_section_of_another_song_is_refused_and_nothing_is_saved(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',],]);
        $other = $this->song([[SectionType::Verse, 'Autre cantique',],], 'Credo');

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $other->sections()->value('id'), 'type' => 'couplet', 'text' => 'Volé'],
        ], 'Titre modifié'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.0.id' => 'Cette section n\'appartient pas à ce cantique.',]);

        $this->assertSame('Gloria', $song->fresh()->title);
        $this->assertSame([['couplet', 'Un', 0,],], $this->storedSections($song));
        $this->assertSame([['couplet', 'Autre cantique', 0,],], $this->storedSections($other));
    }

    public function test_an_unknown_section_id_is_refused(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',],]);

        $this->putJson("/api/songs/{$song->id}", $this->payload([['id' => 99999, 'type' => 'couplet', 'text' => 'Un'],]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.0.id',]);

        $this->assertSame([['couplet', 'Un', 0,],], $this->storedSections($song));
    }

    public function test_the_same_section_cannot_be_sent_twice(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',],]);
        $id = $song->sections()->value('id');

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $id, 'type' => 'couplet', 'text' => 'Un'],
            ['id' => $id, 'type' => 'couplet', 'text' => 'Un bis'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.0.id', 'sections.1.id',]);

        $this->assertSame([['couplet', 'Un', 0,],], $this->storedSections($song));
    }

    // Modification simultanée : la section a disparu entre la validation de la requête et le verrou du cantique. Le cas
    // ne se produit pas par HTTP en un seul processus, d'où l'appel direct de l'action.
    public function test_a_section_deleted_in_the_meantime_gives_a_validation_error_not_a_not_found(): void
    {
        $song = $this->song([[SectionType::Verse, 'Un',],]);
        $deletedId = $song->sections()->value('id');
        $song->sections()->delete();

        try {
            app(SyncSongSectionsAction::class)($song, [new SongSectionDTO($deletedId, SectionType::Verse, null, 'Un', ),]);

            $this->fail('Une ValidationException était attendue.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['sections.0.id' => ['Cette section n\'existe plus : rechargez le cantique.',],],
                $exception->errors(),
            );
        }
    }

    // ── Validations ─────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidSections(): array
    {
        return [
            'type missing'         => [[['text' => 'Texte',],], 'sections.0.type',],
            'type unknown'         => [[['type' => 'strophe', 'text' => 'Texte',],], 'sections.0.type',],
            'number zero'          => [[['type' => 'couplet', 'number' => 0, 'text' => 'Texte',],], 'sections.0.number',],
            'number negative'      => [[['type' => 'couplet', 'number' => -1, 'text' => 'Texte',],], 'sections.0.number',],
            'number not a number'  => [[['type' => 'couplet', 'number' => 'un', 'text' => 'Texte',],], 'sections.0.number',],
            'number decimal'       => [[['type' => 'couplet', 'number' => 1.5, 'text' => 'Texte',],], 'sections.0.number',],
            'number too large'     => [[['type' => 'couplet', 'number' => 1000, 'text' => 'Texte',],], 'sections.0.number',],
            'type empty'           => [[['type' => '', 'text' => 'Texte',],], 'sections.0.type',],
            'text missing'         => [[['type' => 'couplet',],], 'sections.0.text',],
            'text empty'           => [[['type' => 'couplet', 'text' => '',],], 'sections.0.text',],
            'text only whitespace' => [[['type' => 'couplet', 'text' => "  \n  ",],], 'sections.0.text',],
            'text not a string'    => [[['type' => 'couplet', 'text' => ['a',],],], 'sections.0.text',],
            'second one invalid'   => [[['type' => 'couplet', 'text' => 'Ok',], ['type' => 'strophe', 'text' => 'Ko',],], 'sections.1.type',],
            'not a list'           => ['abc', 'sections',],
            'too many sections'    => [array_fill(0, 101, ['type' => 'couplet', 'text' => 'Texte',], ), 'sections',],
            'text too long'        => [[['type' => 'couplet', 'text' => str_repeat('a', 5001),],], 'sections.0.text',],
        ];
    }

    #[DataProvider('invalidSections')]
    public function test_invalid_sections_are_refused_when_creating_a_song(mixed $sections, string $errorKey): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/songs', $this->payload($sections))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey,]);

        $this->assertDatabaseCount('songs', 0);
        $this->assertDatabaseCount('song_sections', 0);
    }

    #[DataProvider('invalidSections')]
    public function test_invalid_sections_are_refused_when_updating_a_song(mixed $sections, string $errorKey): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',],]);

        $this->putJson("/api/songs/{$song->id}", $this->payload($sections, 'Titre modifié'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey,]);

        $this->assertSame('Gloria', $song->fresh()->title);
        $this->assertSame([['couplet', 'Un', 0,],], $this->storedSections($song));
    }

    public function test_the_error_messages_are_explicit(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/songs', $this->payload([['type' => 'strophe', 'number' => 0, 'text' => '',],]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sections.0.type'   => 'Le type de la section doit être "couplet", "refrain" ou "pont".',
                'sections.0.number' => 'Le numéro de la section doit être au moins 1.',
                'sections.0.text'   => 'Le texte de la section est obligatoire.',
            ]);
    }

    public function test_the_limit_messages_are_explicit(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/songs', [
            'title'    => 'Gloria',
            'notes'    => str_repeat('a', 2001),
            'sections' => array_fill(0, 101, ['type' => 'couplet', 'text' => 'Texte',], ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'notes'    => 'Les notes ne peuvent pas dépasser 2000 caractères.',
                'sections' => 'Un cantique ne peut pas avoir plus de 100 sections.',
            ]);

        $this->postJson('/api/songs', $this->payload([['type' => 'couplet', 'text' => str_repeat('a', 5001),],]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sections.0.text' => 'Le texte d\'une section ne peut pas dépasser 5000 caractères.',
            ]);
    }

    public function test_the_limits_themselves_are_accepted(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/songs', [
            'title'    => 'Gloria',
            'notes'    => str_repeat('a', 2000),
            'sections' => array_fill(0, 100, ['type' => 'couplet', 'text' => str_repeat('a', 5000),], ),
        ])->assertCreated();

        $this->assertDatabaseCount('song_sections', 100);
    }

    // ── Types (couplet, refrain, pont) et numéros saisis ────────────────────

    public function test_a_bridge_section_is_accepted(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/songs', $this->payload([
            ['type' => 'couplet', 'text' => 'Un'],
            ['type' => 'pont', 'text' => 'Passage du pont'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.sections.1.type', 'pont');

        $this->assertSame(
            [['couplet', 'Un', 0,], ['pont', 'Passage du pont', 1,],],
            $this->storedSections(Song::findOrFail($response->json('data.id'))),
        );
    }

    public function test_the_numbers_entered_are_stored_as_sent(): void
    {
        $this->actingAsAdmin();

        // Numéro facultatif, sans ordre imposé et sans contrôle d'unicité : c'est l'admin qui numérote
        $response = $this->postJson('/api/songs', $this->payload([
            ['type' => 'couplet', 'number' => 3, 'text' => 'Trois'],
            ['type' => 'couplet', 'number' => 1, 'text' => 'Un'],
            ['type' => 'refrain', 'text' => 'Refrain'],
            ['type' => 'refrain', 'number' => null, 'text' => 'Refrain bis'],
            ['type' => 'couplet', 'number' => 1, 'text' => 'Un bis'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.sections.0.number', 3)
            ->assertJsonPath('data.sections.1.number', 1)
            ->assertJsonPath('data.sections.2.number', null)
            ->assertJsonPath('data.sections.3.number', null)
            ->assertJsonPath('data.sections.4.number', 1);

        $this->assertSame([3, 1, null, null, 1], $this->storedNumbers(Song::findOrFail($response->json('data.id'))));
    }

    public function test_a_section_sent_without_number_loses_its_number(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un', 2,],]);
        $id = $song->sections()->value('id');

        // Une section est remplacée en entier par ce qui est envoyé, comme le reste de la liste
        $this->putJson("/api/songs/{$song->id}", $this->payload([['id' => $id, 'type' => 'couplet', 'text' => 'Un'],]))
            ->assertOk()
            ->assertJsonPath('data.sections.0.number', null);

        $this->assertSame([null], $this->storedNumbers($song));
    }

    public function test_a_song_shows_its_sections_with_their_numbers(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([
            [SectionType::Verse, 'Un', 1,],
            [SectionType::Chorus, 'Refrain',],
            [SectionType::Verse, 'Deux', 2,],
            [SectionType::Bridge, 'Pont',],
        ]);

        $response = $this->getJson("/api/songs/{$song->id}")->assertOk();

        $this->assertSame(
            [['couplet', 1,], ['refrain', null,], ['couplet', 2,], ['pont', null,],],
            collect($response->json('data.sections'))->map(fn ($section) => [$section['type'], $section['number'],])->all(),
        );
    }

    public function test_the_song_list_does_not_load_the_sections(): void
    {
        $this->actingAsAdmin();
        $this->song([[SectionType::Verse, 'Un',],]);

        $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.sections');
    }

    // ── Autorisations (SongPolicy) ──────────────────────────────────────────

    public function test_a_member_cannot_create_or_update_a_song(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $song = $this->song([[SectionType::Verse, 'Un',],]);
        $payload = $this->payload([['type' => 'refrain', 'text' => 'Piratage',],], 'Titre modifié');

        $this->postJson('/api/songs', $payload)->assertForbidden();
        $this->putJson("/api/songs/{$song->id}", $payload)->assertForbidden();

        $this->assertDatabaseCount('songs', 1);
        $this->assertSame('Gloria', $song->fresh()->title);
        $this->assertSame([['couplet', 'Un', 0,],], $this->storedSections($song));
    }

    public function test_a_secretary_cannot_create_or_update_a_song(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));
        $song = $this->song();

        $this->postJson('/api/songs', $this->payload([]))->assertForbidden();
        $this->putJson("/api/songs/{$song->id}", $this->payload([]))->assertForbidden();
    }

    public function test_a_guest_is_not_authenticated(): void
    {
        $song = $this->song();

        $this->postJson('/api/songs', $this->payload([]))->assertUnauthorized();
        $this->putJson("/api/songs/{$song->id}", $this->payload([]))->assertUnauthorized();
    }

    public function test_authorization_is_checked_before_validation(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));
        $song = $this->song();
        $invalid = $this->payload([['type' => 'strophe', 'text' => '',],], '');

        $this->postJson('/api/songs', $invalid)->assertForbidden();
        $this->putJson("/api/songs/{$song->id}", $invalid)->assertForbidden();
    }

    public function test_an_unknown_or_non_numeric_song_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/songs/99999', $this->payload([]))->assertNotFound();
        $this->putJson('/api/songs/abc', $this->payload([]))->assertNotFound();
        $this->getJson('/api/songs/abc')->assertNotFound();
        $this->deleteJson('/api/songs/abc')->assertNotFound();
    }

    // ── Section utilisée dans l'ordre d'une messe (US-18) ───────────────────

    // Le cantique est chanté à une messe, avec ces sections dans l'ordre (comme le fera US-18)
    private function useInMass(Song $song, int ...$sectionIds): void
    {
        $massId = DB::table('masses')->insertGetId(['date' => '2026-12-06',]);
        $typeId = DB::table('mass_part_types')->insertGetId(['name' => 'Entrée',]);
        $partId = DB::table('mass_parts')->insertGetId([
            'uuid'              => (string) Str::uuid(),
            'mass_id'           => $massId,
            'mass_part_type_id' => $typeId,
        ]);
        $massSongId = DB::table('mass_songs')->insertGetId(['mass_part_id' => $partId, 'song_id' => $song->id,]);

        foreach ($sectionIds as $position => $sectionId) {
            DB::table('mass_song_sections')->insert([
                'mass_song_id'    => $massSongId,
                'song_section_id' => $sectionId,
                'position'        => $position,
            ]);
        }
    }

    /**
     * Comportement à confirmer avec la présidente (point ouvert de KAN-38) : supprimer une section que l'ordre d'une
     * messe utilise est refusé, au lieu de l'enlever en silence du programme de cette messe (cascade SQL). Rien n'est
     * enregistré : ni la suppression, ni le nouveau titre.
     */
    public function test_a_section_used_by_a_mass_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);
        [$verse, $chorus] = $this->sectionIds($song);
        $this->useInMass($song, $verse, $chorus);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $chorus, 'type' => 'refrain', 'text' => 'Deux'],
        ], 'Titre modifié'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections' => 'Une section utilisée dans l\'ordre d\'une messe ne peut pas être supprimée.',]);

        $this->assertSame('Gloria', $song->fresh()->title);
        $this->assertSame([['couplet', 'Un', 0,], ['refrain', 'Deux', 1,],], $this->storedSections($song));
        $this->assertDatabaseCount('mass_song_sections', 2);
    }

    public function test_an_empty_list_cannot_delete_the_sections_used_by_a_mass(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);
        $this->useInMass($song, ...$this->sectionIds($song));

        $this->putJson("/api/songs/{$song->id}", $this->payload([]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections',]);

        $this->assertDatabaseCount('song_sections', 2);
        $this->assertDatabaseCount('mass_song_sections', 2);
    }

    public function test_a_section_used_by_a_mass_can_be_modified_and_reordered(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);
        [$verse, $chorus] = $this->sectionIds($song);
        $this->useInMass($song, $verse, $chorus);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $chorus, 'type' => 'refrain', 'text' => 'Deux modifié'],
            ['id' => $verse, 'type' => 'couplet', 'text' => 'Un modifié'],
        ]))->assertOk();

        $this->assertSame([['refrain', 'Deux modifié', 0,], ['couplet', 'Un modifié', 1,],], $this->storedSections($song));
        $this->assertDatabaseCount('mass_song_sections', 2);
    }

    public function test_a_section_that_no_mass_uses_can_be_deleted_next_to_a_used_one(): void
    {
        $this->actingAsAdmin();
        $song = $this->song([[SectionType::Verse, 'Un',], [SectionType::Chorus, 'Deux',],]);
        [$verse, $chorus] = $this->sectionIds($song);
        $this->useInMass($song, $verse);

        $this->putJson("/api/songs/{$song->id}", $this->payload([
            ['id' => $verse, 'type' => 'couplet', 'text' => 'Un'],
        ]))->assertOk();

        $this->assertSame([['couplet', 'Un', 0,],], $this->storedSections($song));
        $this->assertDatabaseMissing('song_sections', ['id' => $chorus,]);
        $this->assertDatabaseCount('mass_song_sections', 1);
    }
}
