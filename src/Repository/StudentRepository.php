<?php

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Trouve les élèves par classe
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.classroom = :classroomId')
            ->andWhere('s.isActive = :active')
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par niveau
     */
    public function findByLevel(int $levelId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.level = :levelId')
            ->andWhere('s.isActive = :active')
            ->setParameter('levelId', $levelId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par année scolaire
     */
    public function findBySchoolYear(int $schoolYearId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolYear = :schoolYearId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolYearId', $schoolYearId)
            ->setParameter('active', true)
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
        $qb = $this->createQueryBuilder('s')
            ->select('s.status AS status', 'COUNT(s.id) AS total')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->groupBy('s.status');

        if ($yearId !== null) {
            $qb->andWhere('s.schoolYear = :yearId')->setParameter('yearId', $yearId);
        }

        $result = ['affecte' => 0, 'non_affecte' => 0];
        foreach ($qb->getQuery()->getResult() as $row) {
            if (array_key_exists($row['status'], $result)) {
                $result[$row['status']] = (int) $row['total'];
            }
        }

        return $result;
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
            ->select('s.gender AS gender', 'COUNT(s.id) AS total')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->groupBy('s.gender');

        if ($yearId !== null) {
            $qb->andWhere('s.schoolYear = :yearId')->setParameter('yearId', $yearId);
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
     * Trouve les élèves actifs par établissement et niveau
     */
    public function findActiveBySchoolAndLevel(int $schoolId, int $levelId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.school = :schoolId')
            ->andWhere('s.level = :levelId')
            ->andWhere('s.isActive = true')
            ->andWhere('s.status = :status')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('levelId', $levelId)
            ->setParameter('status', 'affecte')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les élèves par classe
     */
    public function countByClassroom(int $classroomId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.classroom = :classroomId')
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
     * Élèves actifs d'une classe, triés par nom.
     *
     * @return Student[]
     */
    public function findActiveByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.classroom = :classroom')
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
}
