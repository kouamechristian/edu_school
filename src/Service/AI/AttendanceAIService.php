<?php

namespace App\Service\AI;

use App\Entity\Student;

class AttendanceAIService
{
    public function __construct(
        private readonly AIService $aiService,
    ) {
    }

    /**
     * @param array<array{date: string, day_of_week: string, type: string, justified: bool, duration_hours: ?float}> $absences
     * @return array{risk_level: string, pattern: string, recommendation: string, total_absences: int, unjustified_count: int}
     */
    public function analyzeAbsences(Student $student, array $absences): array
    {
        if (empty($absences)) {
            return [
                'risk_level' => 'low',
                'pattern' => 'Aucune absence enregistrée.',
                'recommendation' => 'Assiduité exemplaire, à encourager.',
                'total_absences' => 0,
                'unjustified_count' => 0,
            ];
        }

        $stats = $this->computeLocalStats($absences);

        if (!$this->aiService->isEnabled()) {
            return $this->getFallbackAnalysis($stats);
        }

        $levelCategory = $student->getLevel()?->getCategoryLabel() ?? 'Non défini';
        $absencesText = $this->formatAbsencesForPrompt($absences, $stats);

        $systemPrompt = <<<SYSTEM
Tu es un conseiller pédagogique expert en suivi de l'assiduité scolaire.
Tu analyses les données d'absences et détectes des patterns (jours récurrents, périodes sensibles, tendances).
Tu réponds UNIQUEMENT au format JSON valide, sans aucun texte avant ou après.
SYSTEM;

        $prompt = <<<PROMPT
Analyse les absences de l'élève ID:{$student->getId()} ({$levelCategory}).

Statistiques :
{$absencesText}

Réponds en JSON strict avec cette structure :
{
  "risk_level": "low|medium|high",
  "pattern": "description du pattern détecté en 1-2 phrases",
  "recommendation": "recommandation concrète pour l'équipe pédagogique en 1-2 phrases"
}

Critères de risque :
- low : < 3 absences, toutes justifiées
- medium : 3-6 absences OU pattern récurrent OU absences non justifiées
- high : > 6 absences OU absences non justifiées répétées OU pattern alarmant
PROMPT;

        $response = $this->aiService->ask($prompt, '', $systemPrompt);

        try {
            $jsonStr = $this->extractJson($response);
            $parsed = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);

            return [
                'risk_level' => $parsed['risk_level'] ?? $stats['computed_risk'],
                'pattern' => $parsed['pattern'] ?? 'Analyse non disponible.',
                'recommendation' => $parsed['recommendation'] ?? 'Suivi régulier recommandé.',
                'total_absences' => $stats['total'],
                'unjustified_count' => $stats['unjustified'],
            ];
        } catch (\JsonException) {
            return $this->getFallbackAnalysis($stats);
        }
    }

    private function computeLocalStats(array $absences): array
    {
        $total = count($absences);
        $unjustified = 0;
        $dayDistribution = [];
        $monthDistribution = [];
        $totalHours = 0;

        foreach ($absences as $absence) {
            if (!($absence['justified'] ?? false)) {
                $unjustified++;
            }
            $day = $absence['day_of_week'] ?? 'inconnu';
            $dayDistribution[$day] = ($dayDistribution[$day] ?? 0) + 1;

            if (isset($absence['date'])) {
                $month = substr($absence['date'], 0, 7);
                $monthDistribution[$month] = ($monthDistribution[$month] ?? 0) + 1;
            }

            $totalHours += $absence['duration_hours'] ?? 0;
        }

        arsort($dayDistribution);
        $topDay = array_key_first($dayDistribution);

        if ($total > 6 || $unjustified > 3) {
            $risk = 'high';
        } elseif ($total > 3 || $unjustified > 0) {
            $risk = 'medium';
        } else {
            $risk = 'low';
        }

        return [
            'total' => $total,
            'unjustified' => $unjustified,
            'day_distribution' => $dayDistribution,
            'month_distribution' => $monthDistribution,
            'top_day' => $topDay,
            'total_hours' => round($totalHours, 1),
            'computed_risk' => $risk,
        ];
    }

    private function formatAbsencesForPrompt(array $absences, array $stats): string
    {
        $lines = [
            "Total absences : {$stats['total']}",
            "Non justifiées : {$stats['unjustified']}",
            "Heures perdues : {$stats['total_hours']}h",
            '',
            'Répartition par jour :',
        ];

        foreach ($stats['day_distribution'] as $day => $count) {
            $lines[] = "  - {$day} : {$count} absence(s)";
        }

        $lines[] = '';
        $lines[] = 'Répartition par mois :';
        foreach ($stats['month_distribution'] as $month => $count) {
            $lines[] = "  - {$month} : {$count} absence(s)";
        }

        $lines[] = '';
        $lines[] = 'Détail (dernières 15) :';
        $recent = array_slice($absences, -15);
        foreach ($recent as $a) {
            $justified = ($a['justified'] ?? false) ? 'justifiée' : 'non justifiée';
            $lines[] = "  - {$a['date']} ({$a['day_of_week']}) : {$a['type']} - {$justified}";
        }

        return implode("\n", $lines);
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/\{[^{}]*\}/s', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }

    private function getFallbackAnalysis(array $stats): array
    {
        $pattern = "Jour le plus fréquent : {$stats['top_day']} ({$stats['day_distribution'][$stats['top_day']]} fois).";

        $recommendation = match ($stats['computed_risk']) {
            'high' => 'Situation préoccupante. Convoquer la famille et envisager un suivi renforcé.',
            'medium' => 'Vigilance requise. Prendre contact avec la famille pour comprendre la situation.',
            default => 'Situation normale. Continuer le suivi habituel.',
        };

        return [
            'risk_level' => $stats['computed_risk'],
            'pattern' => $pattern,
            'recommendation' => $recommendation,
            'total_absences' => $stats['total'],
            'unjustified_count' => $stats['unjustified'],
        ];
    }
}
