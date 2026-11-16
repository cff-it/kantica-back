<?php

namespace Tests\Unit\User;

use App\Actions\User\ExtractDriveFileIdAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtractDriveFileIdActionTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function links(): array
    {
        return [
            'lien « open » du formulaire'             => ['https://drive.google.com/open?id=1AbC-dEf_123', '1AbC-dEf_123',],
            'lien de partage'                         => ['https://drive.google.com/file/d/1AbC-dEf_123/view?usp=sharing', '1AbC-dEf_123',],
            'lien de téléchargement'                  => ['https://drive.usercontent.google.com/download?id=1AbC&export=download', '1AbC',],
            'plusieurs fichiers : le premier'         => ['https://drive.google.com/open?id=first, https://drive.google.com/open?id=second', 'first',],
            'espaces autour du lien'                  => ['  https://drive.google.com/open?id=abc  ', 'abc',],
            'hôte en majuscules'                      => ['https://DRIVE.google.com/open?id=abc', 'abc',],
            'http accepté'                            => ['http://drive.google.com/open?id=abc', 'abc',],
            'autre site'                              => ['https://exemple.com/open?id=abc', null,],
            'hôte qui ressemble à Google Drive'       => ['https://drive.google.com.exemple.com/open?id=abc', null,],
            'adresse interne'                         => ['http://169.254.169.254/latest/meta-data', null,],
            'schéma file'                             => ['file:///etc/passwd', null,],
            'lien Drive sans identifiant'             => ['https://drive.google.com/open', null,],
            'identifiant avec des caractères louches' => ['https://drive.google.com/open?id=abc%26export%3Dx/../', null,],
            'identifiant en tableau'                  => ['https://drive.google.com/open?id[]=abc', null,],
            'pas un lien'                             => ['pas de photo', null,],
            'vide'                                    => ['', null,],
        ];
    }

    #[DataProvider('links')]
    public function test_it_extracts_the_file_id_of_google_drive_links_only(string $link, ?string $expected): void
    {
        $this->assertSame($expected, (new ExtractDriveFileIdAction())($link));
    }
}
