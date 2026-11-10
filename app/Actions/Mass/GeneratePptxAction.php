<?php

namespace App\Actions\Mass;

use App\Repositories\MassRepository;

readonly class GeneratePptxAction
{
    public function __construct(private MassRepository $masses) {}

    public function __invoke(int $massId): string
    {
        $mass = $this->masses->findById($massId);

        // TODO: implémenter la génération PPTX (ex: avec phpoffice/phppresentation)
        $path = storage_path("app/public/masses/mass_{$mass->id}.pptx");

        return $path;
    }
}
