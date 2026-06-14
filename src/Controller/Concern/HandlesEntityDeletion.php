<?php

namespace App\Controller\Concern;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime une entité en gérant proprement les contraintes d'intégrité.
 *
 * Lorsqu'une entité est encore référencée par d'autres données (contrainte de
 * clé étrangère), Doctrine lève une ForeignKeyConstraintViolationException qui,
 * sans traitement, génère une page d'erreur SQL brute. Ce trait intercepte ce
 * cas et affiche un message d'alerte clair via le système de flash.
 */
trait HandlesEntityDeletion
{
    /**
     * @return bool true si la suppression a réussi, false si elle a été bloquée
     */
    private function deleteEntity(
        EntityManagerInterface $entityManager,
        object $entity,
        string $successMessage,
        ?string $constraintMessage = null
    ): bool {
        try {
            $entityManager->remove($entity);
            $entityManager->flush();

            $this->addFlash('success', $successMessage);

            return true;
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->addFlash(
                'error',
                $constraintMessage
                    ?? 'Suppression impossible : cet élément est encore lié à d\'autres données. '
                        . 'Veuillez d\'abord supprimer ou réaffecter les éléments associés.'
            );

            return false;
        }
    }
}
