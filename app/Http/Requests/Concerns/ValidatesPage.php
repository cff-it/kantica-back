<?php

namespace App\Http\Requests\Concerns;

// Paramètre `page` des listes paginées (membres, rôles, historique des imports, répertoire, événements déjà saisis) :
// règles, messages et lecture au même endroit, pour que les listes ne divergent pas (testé par ListPageTest). Borne haute : au-delà
// de PHP_INT_MAX / 20, le décalage de la page n'est plus un entier (erreur 500 avec `forPage`, page arbitraire avec un
// décalage SQL)
trait ValidatesPage
{
    protected const int MAX_PAGE = 100000;

    /**
     * @return list<string>
     */
    protected function pageRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE,];
    }

    /**
     * @return array<string, string>
     */
    protected function pageMessages(): array
    {
        return [
            'page.integer' => 'La page doit être un nombre entier.',
            'page.min'     => 'La page doit être supérieure ou égale à 1.',
            'page.max'     => 'La page demandée est trop grande.',
        ];
    }

    // Page demandée, 1 par défaut : la validation a déjà écarté les valeurs hors bornes
    protected function requestedPage(): int
    {
        return max(1, $this->integer('page', 1));
    }
}
