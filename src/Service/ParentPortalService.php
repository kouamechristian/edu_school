<?php

namespace App\Service;

use App\Entity\Period;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Repository\GradeRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;

/**
 * Agrégations en lecture seule pour le Portail Parent.
 *
 * Toutes les requêtes sont scopées par l'identifiant réel de l'élève (Student.id)
 * via les repositories métier. On n'utilise volontairement pas GradeCalculationService,
 * qui est typé sur User et inadapté au modèle Grade → Student.
 *
 * Ce service ne réalise aucun contrôle d'accès : l'autorisation est portée par
 * ChildVoter, appelé en amont dans le contrôleur.
 */
class ParentPortalService
{
    public function __construct(
        private readonly StudentRepository $studentRepository,
        private readonly GradeRepository $gradeRepository,
        private readonly AbsenceRepository $absenceRepository,
        private readonly PeriodRepository $periodRepository,
        private readonly StudentFeeRepository $studentFeeRepository,
    ) {
    }

    /**
     * Enfants rattachés au parent connecté.
     *
     * Union (dédupliquée) des deux sources de rattachement :
     *  - le lien explicite issu de l'auto-association (User.children) ;
     *  - le lien historique par e-mail (Student.parentEmail ↔ User.email).
     *
     * On retourne TOUJOURS tous les enfants rattachés, indépendamment de l'année
     * sélectionnée : le sélecteur d'année n'affine que les données affichées (frais,
     * période…), il ne doit jamais faire « disparaître » un enfant — par exemple un
     * enfant tout juste rattaché et pas encore inscrit pour l'année en cours.
     *
     * @param int|null $schoolYearId conservé pour compatibilité d'appel ; non filtrant.
     * @return Student[]
     */
    public function getChildren(User $parent, ?int $schoolYearId = null): array
    {
        $children = [];

        // Lien explicite (auto-association), élèves actifs uniquement.
        foreach ($parent->getChildren() as $child) {
            if ($child->isActive()) {
                $children[$child->getId()] = $child;
            }
        }

        // Lien historique par e-mail.
        foreach ($this->studentRepository->findByParentEmail((string) $parent->getEmail()) as $child) {
            $children[$child->getId()] = $child;
        }

        $children = array_values($children);

        usort($children, static function (Student $a, Student $b): int {
            return [$a->getLastName(), $a->getFirstName()] <=> [$b->getLastName(), $b->getFirstName()];
        });

        return $children;
    }

    /**
     * Années scolaires distinctes rattachées aux enfants du parent (toutes années),
     * triées de la plus récente à la plus ancienne. Alimente le sélecteur d'année.
     *
     * @return \App\Entity\SchoolYear[]
     */
    public function getSchoolYears(User $parent): array
    {
        $years = [];
        foreach ($this->getChildren($parent) as $child) {
            $year = $child->getSchoolYear();
            if ($year) {
                $years[$year->getId()] = $year;
            }
        }

        $years = array_values($years);

        usort($years, static fn ($a, $b) => ($b->getStartDate() <=> $a->getStartDate()));

        return $years;
    }

    /**
     * Périodes (trimestres) de l'établissement et de l'année de l'élève.
     *
     * @return Period[]
     */
    public function getPeriods(Student $child): array
    {
        if (!$child->getSchool() || !$child->getSchoolYear()) {
            return [];
        }

        return $this->periodRepository->findBySchoolAndYear(
            $child->getSchool()->getId(),
            $child->getSchoolYear()->getId(),
        );
    }

    /**
     * Période courante de l'élève (ou la première disponible en repli).
     */
    public function getCurrentPeriod(Student $child): ?Period
    {
        if (!$child->getSchool() || !$child->getSchoolYear()) {
            return null;
        }

        $current = $this->periodRepository->findCurrentPeriod(
            $child->getSchool()->getId(),
            $child->getSchoolYear()->getId(),
        );

        if ($current) {
            return $current;
        }

        $periods = $this->getPeriods($child);

        return $periods[0] ?? null;
    }

    /**
     * Bilan académique d'un élève pour une période donnée (notes publiées uniquement).
     *
     * @return array{
     *     period: ?Period,
     *     general_average: ?float,
     *     subjects: array<int, array{subject: \App\Entity\Subject, average: ?float, grades: \App\Entity\Grade[]}>,
     *     grade_count: int
     * }
     */
    public function getAcademicReport(Student $child, ?Period $period): array
    {
        $report = [
            'period' => $period,
            'general_average' => null,
            'subjects' => [],
            'grade_count' => 0,
        ];

        if (!$period) {
            return $report;
        }

        // Le parent ne voit que les évaluations publiées par les enseignants.
        $grades = array_filter(
            $this->gradeRepository->findByStudentAndPeriod($child->getId(), $period->getId()),
            static fn ($grade) => $grade->getEvaluation()?->isPublished() === true,
        );

        // Regroupement par matière.
        $bySubject = [];
        foreach ($grades as $grade) {
            $subject = $grade->getEvaluation()?->getSubject();
            if (!$subject) {
                continue;
            }

            $subjectId = $subject->getId();
            if (!isset($bySubject[$subjectId])) {
                $bySubject[$subjectId] = [
                    'subject' => $subject,
                    'average' => $this->gradeRepository->calculateAverageByStudentSubjectAndPeriod(
                        $child->getId(),
                        $subjectId,
                        $period->getId(),
                    ),
                    'grades' => [],
                ];
            }

            $bySubject[$subjectId]['grades'][] = $grade;
        }

        $report['subjects'] = array_values($bySubject);
        $report['grade_count'] = count($grades);
        $report['general_average'] = $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod(
            $child->getId(),
            $period->getId(),
        );

        return $report;
    }

    /**
     * Bilan d'assiduité d'un élève, éventuellement restreint à une période.
     *
     * @return array{
     *     absences: \App\Entity\Absence[],
     *     total: int,
     *     justified: int,
     *     pending: int,
     *     unjustified: int
     * }
     */
    public function getAttendanceReport(Student $child, ?Period $period = null): array
    {
        $absences = $this->absenceRepository->findByStudent($child->getId());

        if ($period) {
            $absences = array_values(array_filter(
                $absences,
                static fn ($absence) => $absence->getPeriod()?->getId() === $period->getId(),
            ));
        }

        $justified = 0;
        $pending = 0;
        $unjustified = 0;

        foreach ($absences as $absence) {
            if ($absence->isJustified()) {
                $justified++;
            } elseif ($absence->isPendingJustification()) {
                $pending++;
            } else {
                $unjustified++;
            }
        }

        return [
            'absences' => $absences,
            'total' => count($absences),
            'justified' => $justified,
            'pending' => $pending,
            'unjustified' => $unjustified,
        ];
    }

    /**
     * Situation financière d'un élève (totaux issus des frais affectés).
     *
     * @return array{
     *     total_tuition: float,
     *     total_discount: float,
     *     discount_label: string,
     *     total_paid: float,
     *     remaining: float
     * }
     */
    public function getFinancialReport(Student $child): array
    {
        // Situation de l'année de scolarité courante (inscription la plus récente),
        // les frais étant désormais rattachés à l'inscription.
        $inscription = $child->getScolariteRegistration();

        return [
            'total_tuition' => $inscription ? $inscription->getTotalTuition() : $child->getTotalTuition(),
            // total_tuition est déjà net de remise ; la remise est indiquée à titre informatif.
            'total_discount' => $inscription ? $inscription->getTotalDiscount() : $child->getTotalDiscount(),
            'discount_label' => $inscription ? $inscription->getDiscountLabel() : '',
            'total_paid' => $inscription ? $inscription->getTotalPaid() : $child->getTotalPaid(),
            'remaining' => $inscription ? $inscription->getRemainingTuition() : $child->getRemainingTuition(),
        ];
    }

    /**
     * Données condensées du tableau de bord parent (une ligne par enfant).
     *
     * @return array<int, array{
     *     child: Student,
     *     period: ?Period,
     *     general_average: ?float,
     *     absences: int,
     *     remaining: float
     * }>
     */
    public function getDashboard(User $parent, ?int $schoolYearId = null): array
    {
        $cards = [];

        foreach ($this->getChildren($parent, $schoolYearId) as $child) {
            $period = $this->getCurrentPeriod($child);
            $inscription = $child->getScolariteRegistration($schoolYearId);

            $cards[] = [
                'child' => $child,
                'period' => $period,
                'general_average' => $period
                    ? $this->gradeRepository->calculateGeneralAverageByStudentAndPeriod($child->getId(), $period->getId())
                    : null,
                'absences' => $this->getAttendanceReport($child, $period)['total'],
                'remaining' => $inscription ? $inscription->getRemainingTuition() : $child->getRemainingTuition(),
            ];
        }

        return $cards;
    }

    /**
     * Détail des frais de l'élève rangés par échéancier : pour chaque frais, ses
     * échéances (ordre, date, montant) avec l'imputation des paiements en cascade
     * (les plus anciennes d'abord) → déjà réglé / reste à payer par échéance.
     *
     * @return list<array{name: string, category: string, total: float, paid: float,
     *     remaining: float, schedules: list<array{order: int, due: ?\DateTimeInterface,
     *     amount: float, paid: float, remaining: float}>}>
     */
    public function getFeeScheduleDetail(Student $child): array
    {
        $detail = [];

        foreach ($this->studentFeeRepository->findByStudent($child->getId()) as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee) {
                continue;
            }

            $schedules = $fee->getSchedules()->toArray();
            usort($schedules, static fn ($a, $b) => ($a->getOrderNumber() ?? 0) <=> ($b->getOrderNumber() ?? 0));

            // Imputation du montant payé sur les échéances, en cascade. Le bouton
            // « Payer » n'est actif que sur la PREMIÈRE échéance non soldée (la prochaine
            // due), car l'imputation d'un paiement se fait de la plus ancienne à la plus
            // récente : on règle donc les échéances dans l'ordre.
            $paidLeft = (float) $studentFee->getPaidAmount();
            $payActive = $fee->isActive();
            $nextPayableSet = false;
            $rows = [];

            if ($schedules !== []) {
                foreach ($schedules as $i => $schedule) {
                    $amount = (float) $schedule->getAmount();
                    $imputed = min($paidLeft, $amount);
                    $paidLeft -= $imputed;
                    $remaining = round($amount - $imputed, 2);
                    $payable = $payActive && !$nextPayableSet && $remaining > 0;
                    if ($payable) {
                        $nextPayableSet = true;
                    }
                    $rows[] = [
                        'order' => $schedule->getOrderNumber() ?? ($i + 1),
                        'due' => $schedule->getDueDate(),
                        'amount' => $amount,
                        'paid' => round($imputed, 2),
                        'remaining' => $remaining,
                        'payable' => $payable,
                    ];
                }
            } else {
                // Frais sans échéancier : une échéance unique.
                $amount = (float) $studentFee->getAmount();
                $imputed = min($paidLeft, $amount);
                $remaining = round($amount - $imputed, 2);
                $rows[] = [
                    'order' => 1,
                    'due' => null,
                    'amount' => $amount,
                    'paid' => round($imputed, 2),
                    'remaining' => $remaining,
                    'payable' => $payActive && $remaining > 0,
                ];
            }

            $detail[] = [
                'student_fee_id' => $studentFee->getId(),
                'name' => (string) $fee->getName(),
                'category' => $fee->getCategoryLabel(),
                'total' => (float) $studentFee->getAmount(),
                'paid' => (float) $studentFee->getPaidAmount(),
                'remaining' => $studentFee->getRemainingAmount(),
                'schedules' => $rows,
            ];
        }

        return $detail;
    }
}
