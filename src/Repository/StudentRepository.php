<?php

namespace App\Repository;

use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 *
 * @method Student|null find($id, $lockMode = null, $lockVersion = null)
 * @method Student|null findOneBy(array $criteria, array $orderBy = null)
 * @method Student[]    findAll()
 * @method Student[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    public function save(Student $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Student $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Joint les inscriptions de l'élève (alias 's') via sa préinscription d'origine.
     *
     * L'inscription n'est plus liée directement à l'élève : elle est portée par la
     * préinscription (nouvel élève → `student`, ancien élève → `existingStudent`).
     * Pose les alias `{alias}_pre` (PreRegistration) et `{alias}` (Registration) et
     * accepte une condition DQL supplémentaire optionnelle sur l'inscription.
     */
    private function joinRegistrations(QueryBuilder $qb, string $alias = 'i', ?string $with = null): QueryBuilder
    {
        $condition = $alias . '.preRegistration = ' . $alias . '_pre';
        if ($with !== null) {
            $condition .= ' AND ' . $with;
        }

        // « s.preRegistration » (nouvel élève, côté propriétaire) ou « existingStudent »
        // (ancien élève) : on ne peut pas filtrer sur PreRegistration.student qui est le
        // côté inverse d'un OneToOne (interdit en DQL).
        return $qb
            ->innerJoin(PreRegistration::class, $alias . '_pre', 'WITH', 's.preRegistration = ' . $alias . '_pre OR ' . $alias . '_pre.existingStudent = s')
            ->innerJoin(Registration::class, $alias, 'WITH', $condition);
    }

    /**
     * Trouve les élèves par établissement
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par classe (via leur inscription).
     *
     * Une classe appartient à une seule année scolaire : filtrer sur l'inscription
     * rattachée à cette classe donne donc directement les élèves de l'année concernée.
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->joinRegistrations($this->createQueryBuilder('s'), 'i')
            ->andWhere('i.classroom = :classroomId')
            ->andWhere('s.isActive = :active')
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par niveau (via la classe de leur inscription)
     */
    public function findByLevel(int $levelId): array
    {
        return $this->joinRegistrations($this->createQueryBuilder('s'), 'r')
            ->innerJoin('r.classroom', 'rc')
            ->andWhere('rc.level = :levelId')
            ->andWhere('s.isActive = :active')
            ->setParameter('levelId', $levelId)
            ->setParameter('active', true)
            ->distinct()
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par année scolaire (via leur inscription)
     */
    public function findBySchoolYear(int $schoolYearId): array
    {
        return $this->joinRegistrations($this->createQueryBuilder('s'), 'r')
            ->andWhere('r.schoolYear = :schoolYearId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolYearId', $schoolYearId)
            ->setParameter('active', true)
            ->distinct()
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un élève par son matricule interne
     */
    public function findByMatriculeInterne(string $matriculeInterne): ?Student
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.matriculeInterne = :matriculeInterne')
            ->andWhere('s.isActive = :active')
            ->setParameter('matriculeInterne', $matriculeInterne)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les élèves par nom ou prénom
     */
    public function findByName(string $name): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.firstName LIKE :name OR s.lastName LIKE :name')
            ->andWhere('s.isActive = :active')
            ->setParameter('name', '%' . $name . '%')
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les élèves par établissement
     */
    public function countBySchool(int $schoolId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Répartition des élèves actifs d'un établissement par statut d'affectation.
     * Restreint à l'année scolaire si fournie.
     *
     * @return array{affecte:int, non_affecte:int}
     */
    public function countByStatusForSchool(int $schoolId, ?int $yearId = null): array
    {
        // Statut administratif porté par l'élève ; année éventuelle via l'inscription.
        $build = function (string $status) use ($schoolId, $yearId) {
            $qb = $this->createQueryBuilder('s')
                ->select('COUNT(DISTINCT s.id)')
                ->andWhere('s.school = :schoolId')
                ->andWhere('s.isActive = :active')
                ->andWhere('s.status = :status')
                ->setParameter('schoolId', $schoolId)
                ->setParameter('active', true)
                ->setParameter('status', $status);

            if ($yearId !== null) {
                $this->joinRegistrations($qb, 'i')
                    ->andWhere('i.schoolYear = :yearId')->setParameter('yearId', $yearId);
            }

            return (int) $qb->getQuery()->getSingleScalarResult();
        };

        return ['affecte' => $build('affecte'), 'non_affecte' => $build('non_affecte')];
    }

    /**
     * Répartition des élèves actifs d'un établissement par genre (M / F).
     * Restreint à l'année scolaire si fournie.
     *
     * @return array{M:int, F:int}
     */
    public function countByGenderForSchool(int $schoolId, ?int $yearId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.gender AS gender', 'COUNT(DISTINCT s.id) AS total')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->groupBy('s.gender');

        if ($yearId !== null) {
            $this->joinRegistrations($qb, 'r')
                ->andWhere('r.schoolYear = :yearId')
                ->setParameter('yearId', $yearId);
        }

        $result = ['M' => 0, 'F' => 0];
        foreach ($qb->getQuery()->getResult() as $row) {
            if ($row['gender'] !== null && array_key_exists($row['gender'], $result)) {
                $result[$row['gender']] = (int) $row['total'];
            }
        }

        return $result;
    }

    /**
     * Trouve les élèves actifs d'un niveau (via la classe de leur inscription).
     * L'affectation réelle est déjà garantie par la jointure sur la classe
     * (r.classroom) et le niveau (rc.level) : un élève n'apparaît que s'il est
     * inscrit dans une classe de ce niveau. On ne filtre PAS sur le champ legacy
     * « Student.status » qui n'est pas renseigné par l'inscription normale
     * (EnrollmentService), ce qui excluait à tort des élèves bien affectés.
     *
     * Si $schoolYearId est fourni, on ne retient que les élèves dont l'inscription
     * dans une classe du niveau concerne cette année scolaire (utile pour les
     * bulletins, afin d'exclure les inscriptions d'années antérieures).
     *
     * Sert aux bulletins (élèves du niveau) et à l'affectation en masse d'un frais.
     */
    public function findActiveBySchoolAndLevel(int $schoolId, int $levelId, ?int $schoolYearId = null): array
    {
        $qb = $this->joinRegistrations($this->createQueryBuilder('s'), 'r')
            ->innerJoin('r.classroom', 'rc')
            ->where('s.school = :schoolId')
            ->andWhere('rc.level = :levelId')
            ->andWhere('s.isActive = true')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('levelId', $levelId);

        if ($schoolYearId !== null) {
            $qb->andWhere('r.schoolYear = :schoolYearId')
                ->setParameter('schoolYearId', $schoolYearId);
        }

        return $qb
            ->distinct()
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les élèves par classe (via leur inscription)
     */
    public function countByClassroom(int $classroomId): int
    {
        return (int) $this->joinRegistrations($this->createQueryBuilder('s'), 'i')
            ->select('COUNT(DISTINCT s.id)')
            ->andWhere('i.classroom = :classroomId')
            ->andWhere('s.isActive = :active')
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les enfants rattachés à un parent via l'adresse e-mail du parent.
     *
     * Lien volontairement basé sur le schéma existant (Student.parentEmail ↔ User.email),
     * sans nouvelle entité. La comparaison est insensible à la casse et aux espaces.
     * Centraliser ce lien ici (et dans ChildVoter) permet de basculer vers une vraie
     * relation Doctrine plus tard sans toucher aux contrôleurs.
     *
     * @return Student[]
     */
    public function findByParentEmail(string $parentEmail): array
    {
        $normalized = mb_strtolower(trim($parentEmail));

        if ($normalized === '') {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(TRIM(s.parentEmail)) = :email')
            ->andWhere('s.isActive = :active')
            ->setParameter('email', $normalized)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrouve un élève actif par son matricule interne ET sa date de naissance.
     *
     * Sert à l'auto-association d'un parent : la double clé (matricule + date de
     * naissance) fait office de preuve de lien sans exposer d'autres données.
     * Le matricule est comparé sans espaces ni casse ; la date est comparée au jour.
     */
    public function findOneActiveByMatriculeAndBirthDate(string $matricule, \DateTimeInterface $dateOfBirth): ?Student
    {
        $normalized = mb_strtolower(trim($matricule));

        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(TRIM(s.matriculeInterne)) = :matricule')
            ->andWhere('s.dateOfBirth = :dob')
            ->andWhere('s.isActive = :active')
            ->setParameter('matricule', $normalized)
            ->setParameter('dob', $dateOfBirth->format('Y-m-d'))
            ->setParameter('active', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Élèves actifs d'une classe (via leur inscription), triés par nom.
     *
     * @return Student[]
     */
    public function findActiveByClassroom(int $classroomId): array
    {
        return $this->joinRegistrations($this->createQueryBuilder('s'), 'i')
            ->andWhere('i.classroom = :classroom')
            ->andWhere('s.isActive = :active')
            ->setParameter('classroom', $classroomId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrouve le dernier élève enregistré dans un établissement pour un matricule national.
     *
     * Sert à la réinscription d'un ancien élève : on récupère ses données les plus
     * récentes pour pré-remplir le formulaire de préinscription. Comparaison insensible
     * à la casse et aux espaces de début/fin.
     */
    public function findLatestBySchoolAndNational(int $schoolId, string $matriculeNational): ?Student
    {
        $normalized = mb_strtolower(trim($matriculeNational));

        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->where('school.id = :schoolId')
            ->andWhere('LOWER(TRIM(s.matriculeNational)) = :mn')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('mn', $normalized)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retrouve un élève actif à partir de son seul matricule national (tous
     * établissements confondus). Sert à l'auto-inscription d'un parent qui rattache
     * son enfant — déjà connu de l'établissement — via le matricule national.
     *
     * Comparaison insensible à la casse et aux espaces de début/fin ; en cas de
     * doublon, on retourne l'enregistrement le plus récent.
     */
    public function findOneActiveByMatriculeNational(string $matriculeNational): ?Student
    {
        $normalized = mb_strtolower(trim($matriculeNational));

        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(TRIM(s.matriculeNational)) = :mn')
            ->andWhere('s.isActive = :active')
            ->setParameter('mn', $normalized)
            ->setParameter('active', true)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre d'inscriptions (enregistrements élève) par matricule national,
     * pour un établissement donné.
     *
     * Le matricule national étant stable d'une année à l'autre, cela permet d'afficher
     * combien de fois un même élève a été inscrit depuis sa venue dans l'établissement.
     *
     * @param string[] $matriculeNationals
     * @return array<string, int> matricule national => nombre d'inscriptions
     */
    public function countBySchoolGroupedByNational(int $schoolId, array $matriculeNationals): array
    {
        if ($matriculeNationals === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('s.matriculeNational AS mn, COUNT(s.id) AS cnt')
            ->leftJoin('s.school', 'school')
            ->where('school.id = :schoolId')
            ->andWhere('s.matriculeNational IN (:nationals)')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('nationals', $matriculeNationals)
            ->groupBy('s.matriculeNational')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $r) {
            $counts[trim((string) $r['mn'])] = (int) $r['cnt'];
        }

        return $counts;
    }

    /**
     * Élèves actifs de l'établissement avec un reste à payer sur au moins une ligne de frais active.
     */
    public function findWithRemainingBalanceBySchool(int $schoolId): array
    {
        $idRows = $this->createQueryBuilder('s')
            ->select('s.id')
            ->innerJoin('s.studentFees', 'sf')
            ->innerJoin('sf.fee', 'f')
            ->where('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->andWhere('f.isActive = :feeActive')
            ->groupBy('s.id')
            ->having('SUM(sf.amount) - SUM(sf.paidAmount) > 0.0001')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->setParameter('feeActive', true)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($idRows as $row) {
            $v = (int) current($row);
            if ($v > 0) {
                $ids[] = $v;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Élèves actifs d'un établissement (année / classe optionnelles) avec leurs lignes
     * de frais et les frais eux-mêmes pré-chargés, pour le suivi du recouvrement.
     *
     * @return Student[]
     */
    public function findForRecouvrement(int $schoolId, ?int $yearId = null, ?int $classroomId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.studentFees', 'sf')
            ->addSelect('sf')
            ->leftJoin('sf.fee', 'f')
            ->addSelect('f')
            ->where('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC');

        if ($yearId) {
            // Sélection des élèves via leur inscription de l'année (jointure de
            // filtrage uniquement, sans hydrater la collection d'inscriptions).
            $this->joinRegistrations($qb, 'i', 'i.schoolYear = :yearId')
                ->setParameter('yearId', $yearId);

            if ($classroomId) {
                $qb->andWhere('i.classroom = :classroomId')->setParameter('classroomId', $classroomId);
            }
        } elseif ($classroomId) {
            $this->joinRegistrations($qb, 'i', 'i.classroom = :classroomId')
                ->setParameter('classroomId', $classroomId);
        }

        return $qb->getQuery()->getResult();
    }
}
