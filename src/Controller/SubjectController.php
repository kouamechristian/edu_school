<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Level;
use App\Entity\Subject;
use App\Form\SubjectType;
use App\Repository\LevelRepository;
use App\Repository\SubjectRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subjects', name: 'admin_subject_')]
#[IsGranted('ROLE_DIRECTEUR')]
class SubjectController extends AbstractController
{
    use HandlesEntityDeletion;

    /**
     * Accueil : les matières sont rangées par niveau. On entre dans un niveau
     * pour voir et ajouter ses matières.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        SubjectRepository $subjectRepository,
        LevelRepository $levelRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les matières.');
            return $this->render('subject/index.html.twig', [
                'levels' => [],
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();
        $levels = $levelRepository->findBySchool($schoolId);

        // Nombre de matières (actives) par niveau, pour l'affichage des cartes.
        $levelData = [];
        foreach ($levels as $level) {
            $levelData[] = [
                'level' => $level,
                'count' => count($subjectRepository->findByLevel($level->getId())),
            ];
        }

        return $this->render('subject/index.html.twig', [
            'levels' => $levelData,
            'current_school' => $currentSchool,
        ]);
    }

    /**
     * Matières d'un niveau précis (avec ajout rattaché à ce niveau).
     */
    #[Route('/level/{id}', name: 'by_level', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function byLevel(Level $level, SubjectRepository $subjectRepository, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();

        // Sécurité : le niveau doit appartenir à l'établissement courant.
        if (!$currentSchool || $level->getSchool()?->getId() !== $currentSchool->getId()) {
            $this->addFlash('warning', "Ce niveau n'appartient pas à l'établissement courant.");
            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject/by_level.html.twig', [
            'level' => $level,
            'subjects' => $this->subjectsInBulletinOrder($subjectRepository, $level),
        ]);
    }

    /**
     * Monte / descend une matière dans l'ordre du bulletin de son niveau.
     */
    #[Route('/{id}/move/{direction}', name: 'move', requirements: ['id' => '\d+', 'direction' => 'up|down'], methods: ['POST'])]
    public function move(
        Request $request,
        Subject $subject,
        string $direction,
        SubjectRepository $subjectRepository,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $level = $subject->getLevel();
        $currentSchool = $contextService->getCurrentSchool();

        if (!$level || !$currentSchool || $subject->getSchool()?->getId() !== $currentSchool->getId()) {
            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('move' . $subject->getId(), $request->request->get('_token'))) {
            $subjects = $this->subjectsInBulletinOrder($subjectRepository, $level);

            $index = null;
            foreach ($subjects as $i => $s) {
                if ($s->getId() === $subject->getId()) {
                    $index = $i;
                    break;
                }
            }

            if ($index !== null) {
                $target = $direction === 'up' ? $index - 1 : $index + 1;

                if ($target >= 0 && $target < count($subjects)) {
                    // Échange les positions puis réattribue un ordre séquentiel (1..n).
                    [$subjects[$index], $subjects[$target]] = [$subjects[$target], $subjects[$index]];

                    foreach ($subjects as $position => $s) {
                        $s->setBulletinOrderNumber($position + 1);
                    }

                    $entityManager->flush();
                    $this->addFlash('success', 'L\'ordre des matières sur le bulletin a été mis à jour.');
                }
            }
        }

        return $this->redirectToRoute('admin_subject_by_level', ['id' => $level->getId()], Response::HTTP_SEE_OTHER);
    }

    /**
     * Matières d'un niveau dans l'ordre du bulletin (sans numéro en dernier, puis par nom).
     *
     * @return Subject[]
     */
    private function subjectsInBulletinOrder(SubjectRepository $subjectRepository, Level $level): array
    {
        $subjects = $subjectRepository->findByLevel($level->getId());

        usort($subjects, fn (Subject $a, Subject $b) => [$a->getBulletinOrderNumber() ?? PHP_INT_MAX, $a->getName()]
            <=> [$b->getBulletinOrderNumber() ?? PHP_INT_MAX, $b->getName()]);

        return $subjects;
    }

    /**
     * Copie dans ce niveau des matières déjà créées dans un autre niveau de
     * l'établissement (les matières portant déjà le même nom sont ignorées).
     */
    #[Route('/level/{id}/import', name: 'import', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function import(
        Level $level,
        Request $request,
        SubjectRepository $subjectRepository,
        LevelRepository $levelRepository,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool || $level->getSchool()?->getId() !== $currentSchool->getId()) {
            $this->addFlash('warning', "Ce niveau n'appartient pas à l'établissement courant.");
            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        // Noms des matières déjà présentes dans le niveau cible (comparaison insensible à la casse).
        $existingNames = [];
        foreach ($subjectRepository->findByLevel($level->getId()) as $existing) {
            $existingNames[mb_strtolower(trim($existing->getName()))] = true;
        }

        // Niveaux sources possibles : les autres niveaux de l'établissement.
        $sourceLevels = array_values(array_filter(
            $levelRepository->findBySchool($currentSchool->getId()),
            fn (Level $l) => $l->getId() !== $level->getId()
        ));

        $sourceLevel = null;
        $sourceId = $request->isMethod('POST') ? $request->request->getInt('source') : $request->query->getInt('source');
        foreach ($sourceLevels as $candidate) {
            if ($candidate->getId() === $sourceId) {
                $sourceLevel = $candidate;
                break;
            }
        }

        $sourceSubjects = $sourceLevel ? $subjectRepository->findByLevel($sourceLevel->getId()) : [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_subjects' . $level->getId(), $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide. Veuillez réessayer.');
                return $this->redirectToRoute('admin_subject_import', ['id' => $level->getId()], Response::HTTP_SEE_OTHER);
            }

            $selectedIds = array_map('intval', $request->request->all('subjects'));
            $created = 0;
            $skipped = 0;

            foreach ($sourceSubjects as $source) {
                if (!in_array($source->getId(), $selectedIds, true)) {
                    continue;
                }

                $key = mb_strtolower(trim($source->getName()));
                if (isset($existingNames[$key])) {
                    ++$skipped;
                    continue;
                }

                $copy = (new Subject())
                    ->setName($source->getName())
                    ->setSchool($currentSchool)
                    ->setLevel($level)
                    ->setType($source->getType())
                    ->setSubjectEquivalent($source->getSubjectEquivalent())
                    ->setCoefficient($source->getCoefficient())
                    ->setHoursPerWeek($source->getHoursPerWeek())
                    ->setBulletinOrderNumber($source->getBulletinOrderNumber())
                    ->setNoteSurBulletin($source->getNoteSurBulletin())
                    ->setLv($source->getLv())
                    ->setMatiereConduite($source->getMatiereConduite())
                    ->setArtMusique($source->getArtMusique())
                    ->setColor($source->getColor())
                    ->setDescription($source->getDescription());

                $entityManager->persist($copy);
                $existingNames[$key] = true;
                ++$created;
            }

            if ($created > 0) {
                $entityManager->flush();
            }

            if ($created === 0 && $skipped === 0) {
                $this->addFlash('warning', 'Aucune matière sélectionnée.');
                return $this->redirectToRoute('admin_subject_import', ['id' => $level->getId(), 'source' => $sourceLevel?->getId()], Response::HTTP_SEE_OTHER);
            }

            $message = sprintf('%d matière(s) importée(s) depuis « %s ».', $created, $sourceLevel->getName());
            if ($skipped > 0) {
                $message .= sprintf(' %d ignorée(s) car déjà présente(s) dans ce niveau.', $skipped);
            }
            $this->addFlash($created > 0 ? 'success' : 'info', $message);

            return $this->redirectToRoute('admin_subject_by_level', ['id' => $level->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject/import.html.twig', [
            'level' => $level,
            'source_levels' => $sourceLevels,
            'source_level' => $sourceLevel,
            'source_subjects' => $sourceSubjects,
            'existing_names' => $existingNames,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        LevelRepository $levelRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        $subject = new Subject();

        if ($currentSchool) {
            $subject->setSchool($currentSchool);
        }

        // Pré-remplir le niveau si on arrive depuis la page d'un niveau.
        $levelId = $request->query->getInt('level');
        $presetLevel = null;
        if ($levelId > 0) {
            $presetLevel = $levelRepository->find($levelId);
            if ($presetLevel && $currentSchool && $presetLevel->getSchool()?->getId() === $currentSchool->getId()) {
                $subject->setLevel($presetLevel);
            } else {
                $presetLevel = null;
            }
        }

        $form = $this->createForm(SubjectType::class, $subject, [
            'show_level' => $presetLevel === null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$subject->getSchool() && $currentSchool) {
                $subject->setSchool($currentSchool);
            }

            $entityManager->persist($subject);
            $entityManager->flush();

            $this->addFlash('success', 'La matière a été créée avec succès.');

            return $this->redirectToSubjectList($subject->getLevel()?->getId());
        }

        return $this->render('subject/new.html.twig', [
            'subject' => $subject,
            'form' => $form,
            'preset_level' => $presetLevel,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Subject $subject): Response
    {
        return $this->render('subject/show.html.twig', [
            'subject' => $subject,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SubjectType::class, $subject);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La matière a été modifiée avec succès.');

            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject/edit.html.twig', [
            'subject' => $subject,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        $levelId = $subject->getLevel()?->getId();

        if ($this->isCsrfTokenValid('delete'.$subject->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $subject,
                'La matière a été supprimée avec succès.',
                'Suppression impossible : cette matière est encore utilisée par des cours ou évaluations.'
            );
        }

        return $this->redirectToSubjectList($levelId);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$subject->getId(), $request->request->get('_token'))) {
            $subject->setIsActive(!$subject->isActive());
            $entityManager->flush();

            $status = $subject->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "La matière a été {$status} avec succès.");
        }

        return $this->redirectToSubjectList($subject->getLevel()?->getId());
    }

    /**
     * Redirige vers la page du niveau concerné, ou vers l'accueil des matières
     * si le niveau est inconnu.
     */
    private function redirectToSubjectList(?int $levelId): Response
    {
        if ($levelId) {
            return $this->redirectToRoute('admin_subject_by_level', ['id' => $levelId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
    }
}

