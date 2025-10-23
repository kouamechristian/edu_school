<?php

namespace App\Controller;

use App\Entity\PreRegistrationDocument;
use App\Entity\DocumentType;
use App\Repository\PreRegistrationDocumentRepository;
use App\Repository\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/pre-registration-documents', name: 'admin_pre_registration_document_')]
#[IsGranted('ROLE_ADMIN')]
class PreRegistrationDocumentController extends AbstractController
{
    #[Route('/upload/{preRegistrationId}', name: 'upload', methods: ['POST'])]
    public function upload(
        Request $request,
        int $preRegistrationId,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        DocumentTypeRepository $documentTypeRepository
    ): Response {
        $preRegistration = $entityManager->getRepository(\App\Entity\PreRegistration::class)->find($preRegistrationId);
        
        if (!$preRegistration) {
            $this->addFlash('error', 'Préinscription non trouvée.');
            return $this->redirectToRoute('admin_pre_registration_index');
        }

        $documentTypeId = $request->request->get('document_type');
        $documentType = $documentTypeRepository->find($documentTypeId);
        
        if (!$documentType) {
            $this->addFlash('error', 'Type de document non trouvé.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        $uploadedFile = $request->files->get('file');
        
        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Vérifier que le fichier a été uploadé correctement
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire.',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé.',
                UPLOAD_ERR_NO_TMP_DIR => 'Le répertoire temporaire est manquant.',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier.',
            ];
            
            $errorMessage = $errorMessages[$uploadedFile->getError()] ?? 'Erreur inconnue lors de l\'upload.';
            $this->addFlash('error', $errorMessage);
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Vérifier que le fichier temporaire existe et est lisible
        if (!is_uploaded_file($uploadedFile->getPathname())) {
            $this->addFlash('error', 'Le fichier temporaire n\'est pas valide ou n\'existe pas.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Lire le contenu du fichier immédiatement pour éviter qu'il soit supprimé
        $fileContent = file_get_contents($uploadedFile->getPathname());
        if ($fileContent === false) {
            $this->addFlash('error', 'Impossible de lire le contenu du fichier.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Vérifier le type MIME
        if (!$documentType->isMimeTypeAllowed($uploadedFile->getMimeType())) {
            $this->addFlash('error', 'Type de fichier non autorisé pour ce type de document.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Vérifier la taille du fichier
        if ($uploadedFile->getSize() > $documentType->getMaxFileSize()) {
            $this->addFlash('error', 'Le fichier est trop volumineux. Taille maximale : ' . $documentType->getFormattedMaxFileSize());
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        $uploadDirectory = $this->getParameter('pre_registration_documents_directory');
        
        // Vérifier que le répertoire de destination existe
        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0755, true)) {
                $this->addFlash('error', 'Impossible de créer le répertoire de destination.');
                return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
            }
        }

        // Vérifier que le répertoire est accessible en écriture
        if (!is_writable($uploadDirectory)) {
            $this->addFlash('error', 'Le répertoire de destination n\'est pas accessible en écriture.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Sauvegarder le fichier en utilisant le contenu lu
        $destinationPath = $uploadDirectory . '/' . $newFilename;
        
        if (file_put_contents($destinationPath, $fileContent) === false) {
            $this->addFlash('error', 'Impossible de sauvegarder le fichier.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }
        
        // Vérifier que le fichier a été sauvegardé correctement
        if (!file_exists($destinationPath)) {
            $this->addFlash('error', 'Le fichier n\'a pas été sauvegardé correctement.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        // Créer l'entité document
        $document = new PreRegistrationDocument();
        $document->setFileName($newFilename);
        $document->setOriginalFileName($uploadedFile->getClientOriginalName());
        $document->setMimeType($uploadedFile->getMimeType());
        $document->setFileSize($uploadedFile->getSize());
        $document->setFilePath($this->getParameter('pre_registration_documents_directory') . '/' . $newFilename);
        $document->setPreRegistration($preRegistration);
        $document->setDocumentType($documentType);

        $entityManager->persist($document);
        $entityManager->flush();

        // Mettre à jour le statut de la préinscription
        if ($preRegistration->getStatus() === 'pending') {
            $preRegistration->setStatus('documents_required');
        }

        $entityManager->flush();

        $this->addFlash('success', 'Le document a été uploadé avec succès.');

        return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(
        PreRegistrationDocument $document,
        EntityManagerInterface $entityManager
    ): Response {
        $document->setIsValidated(true);
        $document->setValidatedAt(new \DateTime());
        $document->setValidatedBy($this->getUser());

        $entityManager->flush();

        $this->addFlash('success', 'Le document a été validé.');

        return $this->redirectToRoute('admin_pre_registration_show', ['id' => $document->getPreRegistration()->getId()]);
    }

    #[Route('/{id}/invalidate', name: 'invalidate', methods: ['POST'])]
    public function invalidate(
        PreRegistrationDocument $document,
        EntityManagerInterface $entityManager
    ): Response {
        $document->setIsValidated(false);
        $document->setValidatedAt(null);
        $document->setValidatedBy(null);
        $document->setValidationNotes(null);

        $entityManager->flush();

        $this->addFlash('success', 'Le document a été invalidé.');

        return $this->redirectToRoute('admin_pre_registration_show', ['id' => $document->getPreRegistration()->getId()]);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(PreRegistrationDocument $document): Response
    {
        $filePath = $document->getFilePath();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier non trouvé.');
        }

        return $this->file($filePath, $document->getOriginalFileName());
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        PreRegistrationDocument $document,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            // Supprimer le fichier physique
            $filePath = $document->getFilePath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $preRegistrationId = $document->getPreRegistration()->getId();
            
            $entityManager->remove($document);
            $entityManager->flush();

            $this->addFlash('success', 'Le document a été supprimé avec succès.');

            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistrationId]);
        }

        return $this->redirectToRoute('admin_pre_registration_index');
    }
}
