<?php

namespace App\Service\AI;

use App\Entity\Student;

class BulletinAIService
{
    public function __construct(
        private readonly AIService $aiService,
    ) {
    }

    public function generateComment(Student $student, array $grades, string $periodName): string
    {
        if (!$this->aiService->isEnabled()) {
            return $this->getDefaultComment($grades);
        }

        $levelCategory = $student->getLevel()?->getCategory() ?? 'college';
        $toneGuide = $this->getToneGuide($levelCategory);

        $gradesText = $this->formatGradesForPrompt($grades);
        $average = $this->calculateAverage($grades);

        $firstName = $student->getFirstName() ?? 'Élève';
        $studentId = $student->getId();
        $levelName = $student->getLevel()?->getName() ?? 'Non défini';
        $categoryLabel = $student->getLevel()?->getCategoryLabel() ?? 'Non défini';
        $classroom = $student->getClassroom() ? (string) $student->getClassroom() : 'Non spécifiée';

        $systemPrompt = <<<SYSTEM
Tu es un assistant pédagogique spécialisé dans la rédaction de commentaires de bulletins scolaires.
Tu rédiges en français, de manière professionnelle et bienveillante.
{$toneGuide}
Tu ne mentionnes JAMAIS le nom de famille de l'élève dans le commentaire.
Tu produis uniquement le commentaire (3 à 5 lignes), sans titre ni introduction.
SYSTEM;

        $prompt = <<<PROMPT
Rédige un commentaire de bulletin scolaire pour la période "{$periodName}".

Élève : {$firstName} (ID: {$studentId})
Niveau : {$levelName} ({$categoryLabel})
Classe : {$classroom}

Résultats :
{$gradesText}

Moyenne générale estimée : {$average}/20
PROMPT;

        return $this->aiService->ask($prompt, '', $systemPrompt);
    }

    private function getToneGuide(string $category): string
    {
        return match ($category) {
            'maternelle', 'primaire' => <<<TONE
NIVEAU PRIMAIRE : Utilise un ton simple, chaleureux et encourageant.
Mets l'accent sur les progrès et l'effort. Utilise le prénom de l'élève.
Suggère des axes d'amélioration de manière positive (ex: "pourrait encore progresser en...").
TONE,
            'college' => <<<TONE
NIVEAU COLLÈGE : Utilise un ton clair et constructif.
Sois précis sur les points forts et les axes d'amélioration.
Encourage le travail régulier et l'autonomie.
TONE,
            'lycee' => <<<TONE
NIVEAU LYCÉE : Utilise un ton précis et analytique.
Mentionne les compétences acquises et celles à renforcer.
Fais référence aux exigences du niveau et aux perspectives (examens, orientation).
TONE,
            'universite' => <<<TONE
NIVEAU UNIVERSITAIRE : Utilise un ton formel et académique.
Évalue la rigueur intellectuelle, la capacité d'analyse et l'autonomie.
Sois direct et concis, avec des recommandations professionnelles.
TONE,
            default => 'Utilise un ton professionnel et bienveillant adapté au contexte scolaire.',
        };
    }

    private function formatGradesForPrompt(array $grades): string
    {
        if (empty($grades)) {
            return 'Aucune note disponible pour cette période.';
        }

        $lines = [];
        foreach ($grades as $grade) {
            $subject = $grade['subject'] ?? 'Matière inconnue';
            $value = $grade['value'] ?? '-';
            $max = $grade['max'] ?? '20';
            $coef = $grade['coefficient'] ?? '1';
            $lines[] = "- {$subject} : {$value}/{$max} (coef. {$coef})";
        }

        return implode("\n", $lines);
    }

    private function calculateAverage(array $grades): string
    {
        $totalWeighted = 0;
        $totalCoef = 0;

        foreach ($grades as $grade) {
            $value = $grade['value'] ?? null;
            $max = (float) ($grade['max'] ?? 20);
            $coef = (float) ($grade['coefficient'] ?? 1);

            if ($value === null || !is_numeric($value) || $max <= 0) {
                continue;
            }

            $normalized = ((float) $value / $max) * 20;
            $totalWeighted += $normalized * $coef;
            $totalCoef += $coef;
        }

        if ($totalCoef === 0.0) {
            return 'N/A';
        }

        return number_format($totalWeighted / $totalCoef, 2);
    }

    private function getDefaultComment(array $grades): string
    {
        $avg = $this->calculateAverage($grades);
        if ($avg === 'N/A') {
            return 'Pas de notes disponibles pour générer un commentaire.';
        }

        $avgFloat = (float) $avg;
        if ($avgFloat >= 16) {
            return 'Excellent trimestre. Résultats très satisfaisants, continuez ainsi.';
        }
        if ($avgFloat >= 14) {
            return 'Bon trimestre. Résultats satisfaisants avec une bonne implication dans le travail.';
        }
        if ($avgFloat >= 12) {
            return 'Trimestre correct. Des efforts sont à poursuivre pour consolider les acquis.';
        }
        if ($avgFloat >= 10) {
            return 'Trimestre passable. Un travail plus régulier permettrait d\'améliorer les résultats.';
        }

        return 'Trimestre insuffisant. Un investissement personnel plus important est nécessaire.';
    }
}
