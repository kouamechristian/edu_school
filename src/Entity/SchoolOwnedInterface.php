<?php

namespace App\Entity;

/**
 * Marque une entité rattachée à un unique établissement.
 *
 * Le SchoolOwnershipSubscriber s'appuie sur cette interface pour interdire, de
 * façon centralisée, l'accès direct par identifiant (routes show/edit/delete…)
 * à une entité appartenant à un établissement que l'utilisateur n'a pas le droit
 * de consulter (cloisonnement multi-établissement / anti-IDOR).
 *
 * Un getSchool() renvoyant null signifie « non rattaché » : l'entité n'est alors
 * pas filtrée (comportement conservateur).
 */
interface SchoolOwnedInterface
{
    public function getSchool(): ?School;
}
