<?php

namespace App\Controller\Concern;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère le téléversement d'un fichier dans public/uploads/<sous-dossier> et
 * retourne son chemin relatif (utilisable avec asset()).
 *
 * Sécurité : le fichier étant déposé sous public/, seule une liste blanche
 * d'extensions est acceptée (déduite du contenu réel via guessExtension(), pas
 * du nom fourni par le client). Tout le reste (php, phtml, html, svg, exe…) est
 * refusé et la méthode retourne null. Une taille maximale est aussi imposée.
 *
 * À utiliser dans un contrôleur étendant AbstractController (pour getParameter()).
 */
trait HandlesFileUpload
{
    /**
     * @param string[]|null $allowedExtensions Liste blanche spécifique (sinon celle par défaut)
     */
    private function uploadFile(
        ?UploadedFile $file,
        string $subdirectory,
        SluggerInterface $slugger,
        ?array $allowedExtensions = null,
    ): ?string {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return null;
        }

        // Taille maximale : 10 Mo.
        if ($file->getSize() === false || $file->getSize() > 10 * 1024 * 1024) {
            return null;
        }

        // Extension déduite du type MIME réel (contenu), jamais du nom client.
        $extension = strtolower((string) $file->guessExtension());
        // Liste blanche par défaut : images + documents bureautiques courants.
        $allowed = $allowedExtensions ?? [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf',
            'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'csv',
        ];
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            return null;
        }

        // Le sous-dossier est fourni par le code, mais on le borne par sécurité.
        $subdirectory = preg_replace('/[^a-z0-9_\-]/i', '', $subdirectory);

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalFilename);
        if ($safeFilename === '') {
            $safeFilename = 'fichier';
        }
        $newFilename = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $extension;

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $subdirectory;

        try {
            $file->move($destination, $newFilename);
        } catch (FileException) {
            return null;
        }

        return 'uploads/' . $subdirectory . '/' . $newFilename;
    }
}
