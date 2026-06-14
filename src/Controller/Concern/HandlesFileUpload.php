<?php

namespace App\Controller\Concern;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère le téléversement d'un fichier dans public/uploads/<sous-dossier> et
 * retourne son chemin relatif (utilisable avec asset()).
 *
 * À utiliser dans un contrôleur étendant AbstractController (pour getParameter()).
 */
trait HandlesFileUpload
{
    private function uploadFile(?UploadedFile $file, string $subdirectory, SluggerInterface $slugger): ?string
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $subdirectory;

        try {
            $file->move($destination, $newFilename);
        } catch (FileException) {
            return null;
        }

        return 'uploads/' . $subdirectory . '/' . $newFilename;
    }
}
