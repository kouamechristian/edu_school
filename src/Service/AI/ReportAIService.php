<?php

namespace App\Service\AI;

use App\Entity\School;

class ReportAIService
{
    public function __construct(
        private readonly AIService $aiService,
    ) {
    }

    /**
     * @param array{
     *     total_students?: int,
     *     active_students?: int,
     *     total_teachers?: int,
     *     total_staff?: int,
     *     average_grade?: float,
     *     attendance_rate?: float,
     *     absence_count?: int,
     *     unjustified_absences?: int,
     *     total_revenue?: float,
     *     total_expenses?: float,
     *     pending_payments?: int,
     *     new_registrations?: int,
     *     period?: string,
     * } $stats
     */
    public function generateSummary(School $school, array $stats): string
    {
        if (!$this->aiService->isEnabled()) {
            return $this->getDefaultSummary($school, $stats);
        }

        $statsText = $this->formatStatsForPrompt($stats);

        $systemPrompt = <<<SYSTEM
Tu es un consultant en gestion scolaire expert. Tu rédiges des résumés exécutifs mensuels
pour les directeurs d'établissements scolaires.
Le résumé doit être structuré, professionnel et actionnable.
Tu rédiges en français. Tu ne mentionnes aucune donnée personnelle.
Format : 3 sections courtes (Tendances, Alertes, Recommandations), chacune avec 2-4 points.
SYSTEM;

        $prompt = <<<PROMPT
Génère un résumé exécutif mensuel pour l'établissement "{$school->getName()}" ({$school->getTypeLabel()}).

Données du mois :
{$statsText}

Structure attendue :
📊 TENDANCES
(2-3 observations sur l'évolution des indicateurs)

⚠️ ALERTES
(points nécessitant une attention immédiate, s'il y en a)

✅ RECOMMANDATIONS
(2-3 actions concrètes à envisager)
PROMPT;

        return $this->aiService->ask($prompt, '', $systemPrompt);
    }

    private function formatStatsForPrompt(array $stats): string
    {
        $lines = [];

        if (isset($stats['total_students'])) {
            $lines[] = "Effectif total : {$stats['total_students']} élèves (actifs : " . ($stats['active_students'] ?? $stats['total_students']) . ')';
        }
        if (isset($stats['total_teachers'])) {
            $lines[] = "Enseignants : {$stats['total_teachers']}";
        }
        if (isset($stats['total_staff'])) {
            $lines[] = "Personnel : {$stats['total_staff']}";
        }
        if (isset($stats['average_grade'])) {
            $lines[] = "Moyenne générale : " . number_format($stats['average_grade'], 2) . '/20';
        }
        if (isset($stats['attendance_rate'])) {
            $lines[] = "Taux de présence : " . number_format($stats['attendance_rate'], 1) . '%';
        }
        if (isset($stats['absence_count'])) {
            $unjust = $stats['unjustified_absences'] ?? 0;
            $lines[] = "Absences : {$stats['absence_count']} (dont {$unjust} non justifiées)";
        }
        if (isset($stats['total_revenue'])) {
            $lines[] = "Recettes : " . number_format($stats['total_revenue'], 0, ',', ' ') . ' FCFA';
        }
        if (isset($stats['total_expenses'])) {
            $lines[] = "Dépenses : " . number_format($stats['total_expenses'], 0, ',', ' ') . ' FCFA';
        }
        if (isset($stats['pending_payments'])) {
            $lines[] = "Paiements en attente : {$stats['pending_payments']}";
        }
        if (isset($stats['new_registrations'])) {
            $lines[] = "Nouvelles inscriptions : {$stats['new_registrations']}";
        }
        if (isset($stats['period'])) {
            $lines[] = "Période : {$stats['period']}";
        }

        return empty($lines) ? 'Aucune donnée disponible.' : implode("\n", $lines);
    }

    private function getDefaultSummary(School $school, array $stats): string
    {
        $parts = ["Résumé mensuel — {$school->getName()} ({$school->getTypeLabel()})\n"];

        if (isset($stats['total_students'])) {
            $parts[] = "Effectif : {$stats['total_students']} élèves.";
        }
        if (isset($stats['attendance_rate'])) {
            $rate = number_format($stats['attendance_rate'], 1);
            $parts[] = "Taux de présence : {$rate}%.";
            if ($stats['attendance_rate'] < 90) {
                $parts[] = "⚠️ Le taux de présence est en dessous du seuil de 90%.";
            }
        }
        if (isset($stats['average_grade'])) {
            $avg = number_format($stats['average_grade'], 2);
            $parts[] = "Moyenne générale : {$avg}/20.";
        }
        if (isset($stats['unjustified_absences']) && $stats['unjustified_absences'] > 0) {
            $parts[] = "⚠️ {$stats['unjustified_absences']} absences non justifiées à traiter.";
        }

        $parts[] = "\n(Résumé généré sans IA — activez le module pour une analyse détaillée.)";

        return implode("\n", $parts);
    }
}
