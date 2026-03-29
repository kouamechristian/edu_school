<?php

namespace App\Service\AI;

use App\Entity\User;

class ChatbotAIService
{
    private const MAX_HISTORY = 10;

    public function __construct(
        private readonly AIService $aiService,
    ) {
    }

    /**
     * @param array<array{role: string, content: string}> $conversationHistory
     */
    public function answer(string $question, User $user, array $conversationHistory = []): string
    {
        if (!$this->aiService->isEnabled()) {
            return 'Le chatbot IA est actuellement désactivé. Veuillez contacter l\'administration.';
        }

        if ($this->isOffTopic($question)) {
            return 'Je suis EDU-BOT, l\'assistant scolaire d\'EDU-SCHOOL. '
                . 'Je ne peux répondre qu\'aux questions relatives à la vie scolaire '
                . '(notes, absences, emploi du temps, règlement, etc.). '
                . 'Comment puis-je vous aider dans ce cadre ?';
        }

        $userType = $user->getUserType() ?? 'user';
        $systemPrompt = $this->buildSystemPrompt($userType, $user);

        $contextParts = [
            "Utilisateur connecté : {$user->getFirstName()} {$user->getLastName()} (rôle : {$userType})",
            "Date actuelle : " . (new \DateTime())->format('d/m/Y H:i'),
        ];

        $schools = $user->getSchools();
        if (!$schools->isEmpty()) {
            $schoolNames = [];
            foreach ($schools as $school) {
                $schoolNames[] = $school->getName();
            }
            $contextParts[] = "Établissement(s) : " . implode(', ', $schoolNames);
        }

        $context = implode("\n", $contextParts);

        $historyContext = '';
        if (!empty($conversationHistory)) {
            $recent = array_slice($conversationHistory, -self::MAX_HISTORY);
            $historyLines = [];
            foreach ($recent as $msg) {
                $role = $msg['role'] === 'user' ? 'Utilisateur' : 'Assistant';
                $historyLines[] = "{$role} : {$msg['content']}";
            }
            $historyContext = "\n\nHistorique de conversation :\n" . implode("\n", $historyLines);
        }

        return $this->aiService->askWithoutCache(
            $question,
            $context . $historyContext,
            $systemPrompt
        );
    }

    private function buildSystemPrompt(string $userType, User $user): string
    {
        $basePrompt = <<<SYSTEM
Tu es EDU-BOT, l'assistant IA d'EDU-SCHOOL, un logiciel de gestion scolaire.
Tu réponds en français, de manière concise, professionnelle et utile.
Tu ne révèles jamais de données personnelles sensibles (adresses, téléphones, mots de passe).
Tu ne parles que de sujets liés à la vie scolaire et au fonctionnement de l'établissement.
Si tu ne connais pas une information, dis-le clairement et oriente vers le bon interlocuteur.
Tu formates tes réponses en texte simple, sans markdown complexe.
SYSTEM;

        $roleContext = match ($userType) {
            'parent' => <<<ROLE
L'utilisateur est un PARENT d'élève. Tu peux l'aider avec :
- Les résultats scolaires de son enfant (notes, bulletins, moyennes)
- Le suivi des absences et retards
- L'emploi du temps et le calendrier scolaire
- La communication avec les enseignants
- Les informations sur les frais de scolarité et paiements
Tu ne donnes PAS d'informations sur d'autres élèves que son/ses enfant(s).
ROLE,
            'eleve' => <<<ROLE
L'utilisateur est un ÉLÈVE. Tu peux l'aider avec :
- Ses propres résultats (notes, moyennes)
- Son emploi du temps
- Le règlement intérieur de l'établissement
- Les dates importantes (examens, vacances, événements)
- Des conseils méthodologiques généraux
Tu ne donnes PAS d'informations sur d'autres élèves.
ROLE,
            'enseignant' => <<<ROLE
L'utilisateur est un ENSEIGNANT. Tu peux l'aider avec :
- Les statistiques de ses classes (moyennes, taux de réussite)
- Le suivi des absences de ses élèves
- Des outils et conseils pédagogiques
- La saisie des notes et évaluations
- La rédaction de commentaires de bulletins
Tu as accès aux données agrégées de ses classes uniquement.
ROLE,
            'directeur' => <<<ROLE
L'utilisateur est un DIRECTEUR d'établissement. Tu peux l'aider avec :
- Les statistiques globales de l'établissement
- Le suivi des effectifs et de l'assiduité
- Les rapports et tendances
- La gestion administrative
- Les indicateurs de performance
Tu as accès à une vue globale de l'établissement.
ROLE,
            'admin' => <<<ROLE
L'utilisateur est un ADMINISTRATEUR. Tu peux l'aider avec :
- Toutes les fonctionnalités du système
- Les statistiques multi-établissements
- La configuration et la gestion technique
- Les rapports consolidés
Tu as accès à l'ensemble des données du système.
ROLE,
            default => <<<ROLE
L'utilisateur a un rôle standard. Tu peux l'aider avec des questions générales sur le fonctionnement d'EDU-SCHOOL.
ROLE,
        };

        return $basePrompt . "\n\n" . $roleContext;
    }

    private function isOffTopic(string $question): bool
    {
        $offTopicPatterns = [
            '/\b(recette|cuisine|football|météo|politique|religion)\b/iu',
            '/\b(blague|joke|histoire drôle)\b/iu',
            '/\b(code|programme|python|javascript|html)\b/iu',
            '/\b(film|série|musique|jeu vidéo|gaming)\b/iu',
        ];

        foreach ($offTopicPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }
}
