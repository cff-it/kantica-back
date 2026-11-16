<?php

namespace App\Actions\User;

// Identifiant du fichier d'un lien Google Drive (« https://drive.google.com/open?id=... »).
// Seuls les liens Google Drive sont acceptés : l'application ne télécharge jamais une adresse quelconque.
readonly class ExtractDriveFileIdAction
{
    private const array HOSTS = ['drive.google.com', 'drive.usercontent.google.com',];

    public function __invoke(string $link): ?string
    {
        // Plusieurs fichiers envoyés : le formulaire les sépare par une virgule, seul le premier est pris
        $link = preg_split('/[\s,]+/', trim($link))[0] ?? '';
        $parts = parse_url($link);

        if (
            !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https',], true)
            || !in_array(mb_strtolower($parts['host'] ?? ''), self::HOSTS, true)
        ) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $id = $query['id'] ?? null;

        // Lien de partage : https://drive.google.com/file/d/<id>/view
        if (!is_string($id) && preg_match('#^/file/d/([\w-]+)#', $parts['path'] ?? '', $match) === 1) {
            $id = $match[1];
        }

        return is_string($id) && preg_match('/^[\w-]+$/', $id) === 1 ? $id : null;
    }
}
