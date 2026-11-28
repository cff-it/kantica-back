<?php

namespace Tests\Feature\Event;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Member\Concerns\CreatesMembers;
use Tests\TestCase;

/**
 * Création d'un événement (KAN-12, formulaire « Nouvel événement ») : `POST /api/events`, réservé à la Secrétaire et à
 * l'Admin. Avant, la route n'avait aucun contrôle de rôle : tout compte connecté pouvait créer un événement.
 */
class CreateEventTest extends TestCase
{
    use CreatesMembers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_secretary_creates_an_event(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Répétition')
            ->assertJsonPath('data.type', 'repetition')
            ->assertJsonPath('data.location', 'Salle paroissiale');

        $event = Event::sole();
        $this->assertSame('Répétition', $event->name);
        $this->assertSame(EventType::Repetition, $event->type);
        $this->assertSame('Salle paroissiale', $event->location);
        $this->assertNull($event->attendances_recorded_at);
        $this->assertFalse($event->need_payment);
    }

    public function test_an_admin_creates_an_event(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Admin));

        $this->postJson('/api/events', $this->payload(['type' => 'messe', 'name' => 'Messe',]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'messe');
    }

    public function test_every_type_of_the_form_is_accepted(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        foreach (['repetition', 'messe', 'concert', 'autre',] as $type) {
            $this->postJson('/api/events', $this->payload(['type' => $type,]))
                ->assertCreated()
                ->assertJsonPath('data.type', $type);
        }

        $this->assertSame(4, Event::count());
    }

    public function test_the_location_is_optional(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload(['location' => null,]))
            ->assertCreated()
            ->assertJsonPath('data.location', null);
        $this->postJson('/api/events', $this->payload(['location' => '',]))
            ->assertCreated()
            ->assertJsonPath('data.location', null);

        $payload = $this->payload();
        unset($payload['location']);
        $this->postJson('/api/events', $payload)->assertCreated();

        $this->assertSame(3, Event::whereNull('location')->count());
    }

    public function test_a_member_or_an_account_without_role_cannot_create_an_event(): void
    {
        foreach ([$this->userWithRole(RoleName::Member), User::factory()->create(),] as $user) {
            Sanctum::actingAs($user);

            $this->postJson('/api/events', $this->payload())->assertForbidden();
        }

        $this->assertSame(0, Event::count());
    }

    public function test_the_role_is_checked_before_the_payload(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Member));

        $this->postJson('/api/events', [])->assertForbidden();
    }

    public function test_a_guest_cannot_create_an_event(): void
    {
        $this->postJson('/api/events', $this->payload())->assertUnauthorized();

        $this->assertSame(0, Event::count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_payload_is_refused(array $payload, string $errorKey): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload($payload))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey,]);

        $this->assertSame(0, Event::count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'type absent'            => [['type' => null,], 'type',],
            'type inconnu'           => [['type' => 'chorale',], 'type',],
            'type en tableau'        => [['type' => ['messe',],], 'type',],
            'type en majuscules'     => [['type' => 'Messe',], 'type',],
            'intitulé absent'        => [['name' => null,], 'name',],
            'intitulé vide'          => [['name' => '',], 'name',],
            'intitulé trop long'     => [['name' => str_repeat('a', 256),], 'name',],
            'date de début absente'  => [['date_start' => null,], 'date_start',],
            'date de début invalide' => [['date_start' => 'bientôt',], 'date_start',],
            'lieu trop long'         => [['location' => str_repeat('a', 256),], 'location',],
            'fin avant le début'     => [['date_end' => '2026-12-05 10:00:00',], 'date_end',],
            'montant négatif'        => [['payment_amount' => -1,], 'payment_amount',],
        ];
    }

    public function test_the_messages_are_in_french(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload(['type' => 'chorale', 'name' => null,]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type' => 'Le type doit être l\'un de : repetition, messe, concert, autre.',
                'name' => 'L\'intitulé de l\'événement est obligatoire.',
            ]);
    }

    // Le front envoie la date et l'heure saisies : sans décalage, elles se lisent à l'heure de la chorale (UTC+3), et la
    // base garde l'heure UTC comme partout ailleurs. « Les présences se saisissent à partir du jour de l'événement » en dépend
    public function test_a_date_without_offset_is_read_in_the_choir_timezone(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload(['date_start' => '2026-12-05 17:00:00', 'date_end' => '2026-12-05 19:00:00',]))
            ->assertCreated()
            ->assertJsonPath('data.date_start', '2026-12-05T14:00:00+00:00')
            ->assertJsonPath('data.date_end', '2026-12-05T16:00:00+00:00');

        $this->assertSame('2026-12-05 14:00:00', DB::table('events')->value('date_start'));
    }

    public function test_a_date_with_an_offset_keeps_it(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload(['date_start' => '2026-12-05T17:00:00+03:00',]))->assertCreated();
        $this->postJson('/api/events', $this->payload(['date_start' => '2026-12-05T17:00:00Z',]))->assertCreated();

        $this->assertEquals(
            ['2026-12-05 14:00:00', '2026-12-05 17:00:00',],
            DB::table('events')->orderBy('id')->pluck('date_start')->all(),
        );
    }

    public function test_the_timezone_of_the_choir_is_configurable(): void
    {
        config()->set('kantica.timezone', 'UTC');
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload(['date_start' => '2026-12-05 17:00:00',]))->assertCreated();

        $this->assertSame('2026-12-05 17:00:00', DB::table('events')->value('date_start'));
    }

    public function test_the_attendances_recorded_at_cannot_be_set_at_creation(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleName::Secretary));

        $this->postJson('/api/events', $this->payload(['attendances_recorded_at' => '2026-12-05 10:00:00',]))->assertCreated();

        $this->assertNull(Event::sole()->attendances_recorded_at);
    }

    // Les événements d'avant la migration n'ont pas de type : ils prennent le défaut de la colonne
    public function test_an_event_created_without_type_in_code_is_an_other_event(): void
    {
        $event = Event::create(['name' => 'Ancien', 'date_start' => '2026-10-24 17:00:00',]);

        $this->assertSame(EventType::Other, $event->type);
        $this->assertSame('autre', Event::find($event->id)->type->value);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type'       => 'repetition',
            'name'       => 'Répétition',
            'date_start' => '2026-12-05 17:00:00',
            'location'   => 'Salle paroissiale',
            ...$overrides,
        ];
    }
}
