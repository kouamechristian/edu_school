<?php

namespace App\Controller\Api;

use App\Controller\Concern\HandlesFileUpload;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * API REST consommée par l'application mobile « ed_photo » : connexion par jeton,
 * recherche d'un élève par matricule et téléversement de sa photo.
 *
 * Les contrôleurs de src/Controller/ étant déjà chargés (sans préfixe) par le
 * loader générique de config/routes.yaml, le préfixe « /api » est porté ici, au
 * niveau de la classe. L'authentification (hors login) est assurée par
 * {@see \App\Security\ApiTokenAuthenticator} et le contrôle d'accès ROLE_INSCRIPTION.
 */
#[Route('/api/mobile')]
class MobileApiController extends AbstractController
{
    use HandlesFileUpload;

    private const MAX_PHOTO_BYTES = 8 * 1024 * 1024; // 8 Mo
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private UserRepository $userRepository,
        private StudentRepository $studentRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Connexion mobile : identifiants (nom d'utilisateur ou e-mail + mot de passe)
     * → émission d'un jeton porteur à réutiliser dans l'en-tête Authorization.
     */
    #[Route('/login', name: 'api_mobile_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->error('missing_credentials', "Nom d'utilisateur et mot de passe requis.", Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->loadUserByIdentifier($username);
        if (!$user instanceof User || !$user->isActive() || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->error('invalid_credentials', 'Identifiants invalides.', Response::HTTP_UNAUTHORIZED);
        }

        // Tout le personnel peut utiliser l'application, SAUF les comptes purement
        // parent et/ou élève (aucun rôle « métier » au-delà de parent/élève/user).
        $staffRoles = array_diff(
            $user->getRoles(),
            ['ROLE_USER', 'ROLE_PARENT', 'ROLE_ELEVE']
        );
        if ($staffRoles === []) {
            return $this->error('forbidden', "Ce compte n'est pas autorisé à utiliser l'application.", Response::HTTP_FORBIDDEN);
        }

        $token = bin2hex(random_bytes(32));
        $user->setApiToken($token);
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getFullName(),
                'username' => $user->getUsername(),
            ],
            'schools' => $this->schoolsPayload($user),
        ]);
    }

    /**
     * Recherche d'un élève par matricule (interne ou national), limitée aux
     * établissements de l'utilisateur.
     */
    #[Route('/students', name: 'api_mobile_student_search', methods: ['GET'])]
    public function searchStudent(Request $request): JsonResponse
    {
        $matricule = trim((string) $request->query->get('matricule', ''));
        if ($matricule === '') {
            return $this->error('missing_matricule', 'Le matricule est requis.', Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $allowed = $this->allowedSchoolIds($user);
        $scope = $allowed;

        // Établissement sélectionné dans l'app (bascule multi-établissement). Il doit
        // faire partie des établissements autorisés (sauf super-admin non restreint).
        $schoolIdParam = $request->query->get('schoolId');
        if ($schoolIdParam !== null && $schoolIdParam !== '') {
            $schoolId = (int) $schoolIdParam;
            if ($allowed !== [] && !in_array($schoolId, $allowed, true)) {
                return $this->error('forbidden_school', 'Établissement non autorisé.', Response::HTTP_FORBIDDEN);
            }
            $scope = [$schoolId];
        }

        $student = $this->studentRepository->findOneByMatriculeInSchools($matricule, $scope);

        if ($student === null) {
            return $this->error('not_found', 'Aucun élève trouvé pour ce matricule.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->studentPayload($student, $request));
    }

    /**
     * Téléversement (ou remplacement) de la photo d'un élève.
     */
    #[Route('/students/{id}/photo', name: 'api_mobile_student_photo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function uploadPhoto(int $id, Request $request, SluggerInterface $slugger): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $allowed = $this->allowedSchoolIds($user);

        $student = $this->studentRepository->find($id);
        if ($student === null
            || ($allowed !== [] && !in_array($student->getSchool()?->getId(), $allowed, true))) {
            return $this->error('not_found', 'Élève introuvable.', Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) {
            return $this->error('missing_file', 'Aucun fichier « photo » reçu.', Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > self::MAX_PHOTO_BYTES) {
            return $this->error('file_too_large', 'La photo dépasse la taille maximale de 8 Mo.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME, true)) {
            return $this->error('invalid_type', 'Format non supporté (JPEG, PNG ou WebP attendu).', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $previousPhoto = $student->getPhoto();

        $path = $this->uploadFile($file, 'students', $slugger);
        if ($path === null) {
            return $this->error('upload_failed', "Échec de l'enregistrement de la photo.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $student->setPhoto($path);
        $this->entityManager->flush();

        // Suppression de l'ancien fichier (best-effort, une fois la nouvelle photo persistée).
        if ($previousPhoto && $previousPhoto !== $path) {
            $oldFile = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($previousPhoto, '/');
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        return new JsonResponse($this->studentPayload($student, $request));
    }

    /**
     * Identifiants des établissements autorisés pour l'utilisateur. Un tableau vide
     * signifie « aucune restriction » (super-administrateur ou compte non rattaché),
     * comme dans SchoolContextService.
     *
     * @return int[]
     */
    private function allowedSchoolIds(User $user): array
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return [];
        }

        $ids = [];
        foreach ($user->getSchools() as $school) {
            if ($school->getId() !== null) {
                $ids[] = $school->getId();
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array{id: int|null, name: string|null}>
     */
    private function schoolsPayload(User $user): array
    {
        $schools = [];
        foreach ($user->getSchools() as $school) {
            $schools[] = ['id' => $school->getId(), 'name' => $school->getName()];
        }

        return $schools;
    }

    private function studentPayload(Student $student, Request $request): array
    {
        $photo = $student->getPhoto();

        return [
            'id' => $student->getId(),
            'fullName' => $student->getFullName(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
            'gender' => $student->getGender(),
            'matriculeInterne' => $student->getMatriculeInterne(),
            'matriculeNational' => $student->getMatriculeNational(),
            'school' => $student->getSchool()?->getName(),
            'level' => $student->getLevel()?->getName(),
            'classroom' => $student->getClassroom()?->getName(),
            'schoolYear' => $student->getSchoolYear()?->getName(),
            'hasPhoto' => $photo !== null,
            // getBasePath() couvre les déploiements en sous-dossier (ex. /edu_school/public).
            'photoUrl' => $photo
                ? $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' . ltrim($photo, '/')
                : null,
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
