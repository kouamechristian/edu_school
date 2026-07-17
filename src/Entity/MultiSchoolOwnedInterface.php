<?php

namespace App\Entity;

/**
 * Marque une entité rattachée à un ou plusieurs établissements (ex. Employee,
 * qui peut travailler dans plusieurs établissements).
 *
 * Le SchoolOwnershipSubscriber autorise l'accès dès lors que l'entité partage au
 * moins un établissement avec ceux autorisés à l'utilisateur. Une collection vide
 * signifie « non rattaché » : l'entité n'est alors pas filtrée.
 *
 * @see SchoolOwnedInterface pour le cas mono-établissement.
 */
interface MultiSchoolOwnedInterface
{
    /**
     * @return iterable<School>
     */
    public function getSchools(): iterable;
}
