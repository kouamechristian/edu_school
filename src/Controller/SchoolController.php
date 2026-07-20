<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\School;
use App\Form\SchoolType;
use App\Repository\SchoolRepository;
use App\Service\GeniusPay\GeniusPayConfigResolver;
use App\Service\GeniusPay\GeniusPayCredentialsUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/schools', name: 'admin_school_')]
#[IsGranted('ROLE_ADMIN')]
class SchoolController extends AbstractController
{
    use HandlesEntityDeletion;

    /**
     * Configuration de la passerelle de paiement en ligne GeniusPay.
     *
     * Les trois secrets sont chiffrés avant stockage et ne sont jamais réaffichés :
     * l'écran montre une forme masquée. Laisser un champ vide conserve la valeur
     * existante — c'est ce qui permet de modifier une seule clé sans avoir à
     * ressaisir les autres.
     */
    #[Route('/{id}/geniuspay', name: 'geniuspay', methods: ['GET', 'POST'])]
    public function geniuspay(
        School $school,
        Request $request,
        EntityManagerInterface $entityManager,
        GeniusPayCredentialsUpdater $credentialsUpdater,
        GeniusPayConfigResolver $configResolver,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('geniuspay' . $school->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('admin_school_geniuspay', ['id' => $school->getId()]);
            }

            $warning = $credentialsUpdater->apply(
                $school,
                $request->request->get('api_key'),
                $request->request->get('api_secret'),
                $request->request->get('webhook_secret'),
                $request->request->getBoolean('enabled'),
            );

            $entityManager->flush();

            $this->addFlash('success', 'Configuration GeniusPay enregistrée.');
            if ($warning !== null) {
                $this->addFlash('warning', $warning);
            }

            return $this->redirectToRoute('admin_school_geniuspay', ['id' => $school->getId()]);
        }

        return $this->render('school/geniuspay.html.twig', [
            'school' => $school,
            'masked' => $configResolver->maskedSummary($school),
            'webhook_url' => $this->generateUrl('webhook_geniuspay', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SchoolRepository $schoolRepository, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $schools = $paginator->paginate($schoolRepository->findAll(), $request->query->getInt('page', 1), 50);
        $countByType = $schoolRepository->countByType();

        return $this->render('school/index.html.twig', [
            'schools' => $schools,
            'stats' => $countByType,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        GeniusPayCredentialsUpdater $credentialsUpdater,
    ): Response {
        $school = new School();
        $form = $this->createForm(SchoolType::class, $school);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyUploads($form, $school, $slugger);
            $warning = $this->applyGeniusPayCredentials($form, $school, $credentialsUpdater);

            $entityManager->persist($school);
            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été créé avec succès.');
            if ($warning !== null) {
                $this->addFlash('warning', $warning);
            }

            return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school/new.html.twig', [
            'school' => $school,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(School $school): Response
    {
        return $this->render('school/show.html.twig', [
            'school' => $school,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        School $school,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        GeniusPayCredentialsUpdater $credentialsUpdater,
    ): Response {
        $form = $this->createForm(SchoolType::class, $school, [
            'geniuspay_enabled' => $school->isGeniuspayEnabled(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyUploads($form, $school, $slugger);
            $warning = $this->applyGeniusPayCredentials($form, $school, $credentialsUpdater);

            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été modifié avec succès.');
            if ($warning !== null) {
                $this->addFlash('warning', $warning);
            }

            return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school/edit.html.twig', [
            'school' => $school,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, School $school, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$school->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $school,
                'L\'établissement a été supprimé avec succès.',
                'Suppression impossible : cet établissement est encore lié à des périodes, années scolaires ou autres données. Veuillez d\'abord supprimer ou réaffecter ces éléments.'
            );
        }

        return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, School $school, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$school->getId(), $request->request->get('_token'))) {
            $school->setIsActive(!$school->isActive());
            $entityManager->flush();

            $status = $school->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "L'établissement a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Téléverse le logo et le cachet de direction (s'ils ont été fournis) et
     * met à jour l'établissement avec les chemins correspondants.
     */
    /**
     * Reporte les identifiants GeniusPay saisis dans le formulaire vers l'entité.
     *
     * Les champs sont non mappés à dessein : ils transportent la valeur en clair,
     * que seul GeniusPayCredentialsUpdater a le droit d'écrire (chiffrée).
     *
     * @return string|null avertissement éventuel à afficher
     */
    private function applyGeniusPayCredentials(
        FormInterface $form,
        School $school,
        GeniusPayCredentialsUpdater $updater,
    ): ?string {
        return $updater->apply(
            $school,
            $form->get('geniuspayApiKeyPlain')->getData(),
            $form->get('geniuspayApiSecretPlain')->getData(),
            $form->get('geniuspayWebhookSecretPlain')->getData(),
            (bool) $form->get('geniuspayEnabledPlain')->getData(),
        );
    }

    private function applyUploads($form, School $school, SluggerInterface $slugger): void
    {
        $logoFile = $form->get('logoFile')->getData();
        if ($logoFile instanceof UploadedFile) {
            $path = $this->storeUpload($logoFile, $slugger);
            if ($path !== null) {
                $school->setLogo($path);
            }
        }

        $cachetFile = $form->get('cachetDirectionFile')->getData();
        if ($cachetFile instanceof UploadedFile) {
            $path = $this->storeUpload($cachetFile, $slugger);
            if ($path !== null) {
                $school->setCachetDirection($path);
            }
        }
    }

    /**
     * Déplace un fichier téléversé dans public/uploads/schools et retourne son
     * chemin relatif (utilisable avec asset()), ou null en cas d'échec.
     */
    private function storeUpload(UploadedFile $file, SluggerInterface $slugger): ?string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/schools';

        try {
            $file->move($destination, $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors du téléversement du fichier : ' . $e->getMessage());
            return null;
        }

        return 'uploads/schools/' . $newFilename;
    }
}

