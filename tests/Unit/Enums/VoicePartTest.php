<?php

namespace Tests\Unit\Enums;

use App\Enums\VoicePart;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoicePartTest extends TestCase
{
    #[DataProvider('labels')]
    public function test_it_recognises_a_voice_by_its_initial_or_its_name(string $label, ?VoicePart $expected): void
    {
        $this->assertSame($expected, VoicePart::fromLabel($label));
    }

    /**
     * @return array<string, array{string, VoicePart|null}>
     */
    public static function labels(): array
    {
        return [
            'soprano initial'      => ['S', VoicePart::Soprano,],
            'lowercase initial'    => ['s', VoicePart::Soprano,],
            'spaces around'        => [' a ', VoicePart::Alto,],
            'soprano name'         => ['Soprano', VoicePart::Soprano,],
            'alto name'            => ['ALTO', VoicePart::Alto,],
            'tenor initial'        => ['T', VoicePart::Tenor,],
            'tenor with accent'    => ['Ténor', VoicePart::Tenor,],
            'tenor without accent' => ['tenor', VoicePart::Tenor,],
            'bass initial'         => ['B', VoicePart::Bass,],
            'french bass'          => ['Basse', VoicePart::Bass,],
            'english bass'         => ['bass', VoicePart::Bass,],
            'precise voice'        => ['S1', null,],
            'unknown voice'        => ['Mezzo', null,],
            'empty'                => ['', null,],
        ];
    }
}
