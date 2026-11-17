<?php

namespace App\Http\Resources;

use App\Enums\DatePrecision;
use App\Enums\SacramentType;
use App\Models\User;
use App\Models\UserSacrament;
use App\Support\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'last_name'    => $this->last_name,
            // Les prénoms (un mot par ligne, déjà triés par `order`) sont rejoints pour pré-remplir le formulaire
            'first_name'   => $this->firstnames->isEmpty() ? null : $this->firstnames->pluck('first_name')->implode(' '),
            'voice_group'  => $this->profile?->voiceGroup?->abbreviation,
            'voice'        => $this->profile?->voice?->name,
            'phone'        => $this->profile?->phone,
            // L'email de contact est l'email du compte (lecture seule sur la fiche)
            'contact_mail' => $this->mail,
            'completed'    => $this->profile !== null,
            // Fiche complète (GET et PUT /api/members/{id}) : seulement quand les sacrements, l'engagement et le contact
            // d'urgence sont chargés. GET /api/me et les autres appelants gardent la fiche courte.
            $this->mergeWhen($this->hasFullRecord(), fn () => $this->fullRecord()),
        ];
    }

    private function hasFullRecord(): bool
    {
        return $this->resource->relationLoaded('sacraments')
            && $this->resource->relationLoaded('engagement')
            && $this->resource->relationLoaded('emergencyContacts');
    }

    /**
     * Les dates sont écrites 1998-03-12, 1998-05 ou 2008 selon ce qui est connu (voir PartialDate) ; un sacrement ou
     * un contact absent donne un bloc aux champs nuls, pour que le formulaire garde toujours la même forme.
     *
     * @return array<string, mixed>
     */
    private function fullRecord(): array
    {
        $profile = $this->profile;
        $engagement = $this->engagement;

        return [
            'birth_date'                 => PartialDate::fromStored($profile?->birth_date, DatePrecision::Day)?->toString(),
            'birth_place'                => $profile?->birth_place,
            'address'                    => $profile?->address,
            'social_pseudo'              => $profile?->social_pseudo,
            'region_registration_number' => $profile?->region_registration_number,
            'picture_url'                => $profile?->picture_url,
            'adhesion_date'              => PartialDate::fromStored($engagement?->adhesion_date, $engagement?->adhesion_date_precision)?->toString(),
            'engagement_date'            => PartialDate::fromStored($engagement?->engagement_date, $engagement?->engagement_date_precision)?->toString(),
            'engagement_place'           => $engagement?->engagement_place,
            'baptism'                    => $this->sacramentBlock(SacramentType::Baptism),
            'first_communion'            => $this->sacramentBlock(SacramentType::FirstCommunion),
            'confirmation'               => $this->sacramentBlock(SacramentType::Confirmation),
            'marriage'                   => $this->marriageBlock(),
            'emergency_contact'          => $this->emergencyContactBlock(),
        ];
    }

    /**
     * @return array{date: string|null, place: string|null}
     */
    private function sacramentBlock(SacramentType $type): array
    {
        $sacrament = $this->sacramentOfType($type);

        return [
            'date'  => PartialDate::fromStored($sacrament?->date, $sacrament?->date_precision)?->toString(),
            'place' => $sacrament?->place,
        ];
    }

    /**
     * @return array{spouse: string|null, date: string|null, place: string|null}
     */
    private function marriageBlock(): array
    {
        $marriage = $this->sacramentOfType(SacramentType::Marriage);

        return [
            'spouse' => $marriage?->spouse_name,
            'date'   => PartialDate::fromStored($marriage?->date, $marriage?->date_precision)?->toString(),
            'place'  => $marriage?->place,
        ];
    }

    // La fiche n'a qu'un bloc de contact : les liens, joints par une virgule en base, redeviennent une liste
    /**
     * @return array{relationships: list<string>, name: string|null, phone: string|null}
     */
    private function emergencyContactBlock(): array
    {
        $contact = $this->emergencyContacts->sortBy('id')->first();
        $relationships = array_map('trim', explode(',', (string) $contact?->relationship));

        return [
            'relationships' => array_values(array_filter($relationships, fn (string $relationship) => $relationship !== '')),
            'name'          => $contact?->contact_name,
            'phone'         => $contact?->contact_phone,
        ];
    }

    private function sacramentOfType(SacramentType $type): ?UserSacrament
    {
        return $this->sacraments->first(fn (UserSacrament $sacrament) => $sacrament->type === $type);
    }
}
