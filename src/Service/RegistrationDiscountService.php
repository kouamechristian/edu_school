<?php

namespace App\Service;

use App\Entity\Registration;
use App\Entity\StudentFee;
use App\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * Remise sur le montant total des frais d'une inscription (une année d'un élève).
 *
 * La remise est définie sur l'inscription (pourcentage ou montant fixe) puis répartie
 * sur ses lignes de frais au prorata de leur montant brut. Chaque ligne porte sa part
 * (StudentFee::discountAmount) et son montant NET (StudentFee::amount) : caisse,
 * imputation, recouvrement, espace parent et reçus raisonnent déjà sur `amount`, ils
 * tiennent donc compte de la remise sans autre modification.
 *
 * Lignes concernées : frais actifs de l'année, hors arriérés des années antérieures.
 * Une ligne ne peut jamais descendre sous ce qui a déjà été payé dessus : la part
 * qu'elle ne peut absorber est reportée sur les autres lignes.
 *
 * Aucune méthode ne flush.
 */
class RegistrationDiscountService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Définit la remise de l'inscription et l'applique aux frais.
     *
     * @throws \DomainException si la remise est invalide ou dépasse le reste à payer
     */
    public function grant(Registration $registration, string $type, float $value, string $reason, ?User $grantedBy): float
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif de la remise est obligatoire.');
        }
        if (!in_array($type, [Registration::DISCOUNT_PERCENT, Registration::DISCOUNT_AMOUNT], true)) {
            throw new \DomainException('Type de remise invalide.');
        }
        if ($value <= 0) {
            throw new \DomainException('La valeur de la remise doit être supérieure à 0.');
        }
        if ($type === Registration::DISCOUNT_PERCENT && $value > 100) {
            throw new \DomainException('Une remise en pourcentage ne peut pas dépasser 100 %.');
        }

        $lines = $this->eligibleLines($registration);
        if ($lines === []) {
            throw new \DomainException('Aucun frais de l\'année sur lequel appliquer une remise.');
        }

        $gross = $this->grossTotal($lines);
        $target = $this->targetAmount($type, $value, $gross);
        if ($type === Registration::DISCOUNT_AMOUNT && $target > $gross) {
            throw new \DomainException(sprintf(
                'La remise (%s F) dépasse le montant total des frais (%s F).',
                $this->money($target),
                $this->money($gross)
            ));
        }

        $capacity = $this->capacity($lines);
        if ($target > $capacity + 0.001) {
            throw new \DomainException(sprintf(
                'La remise (%s F) dépasse ce qui reste à payer avant remise (%s F) : une partie des frais est déjà réglée.',
                $this->money($target),
                $this->money($capacity)
            ));
        }

        $registration->setDiscount($type, $value, $reason, $grantedBy);
        $this->distribute($lines, $target);

        return $target;
    }

    /**
     * Supprime la remise : chaque ligne retrouve son montant brut.
     */
    public function revoke(Registration $registration): void
    {
        foreach ($this->eligibleLines($registration) as $line) {
            $line->applyDiscount(0.0);
        }
        $registration->setDiscount(null, null, null, null);
    }

    /**
     * Réapplique la remise après une modification des frais de l'inscription (frais
     * ajouté ou retiré, changement de niveau…). Un pourcentage est recalculé sur le
     * nouveau total ; un montant fixe est conservé. Si les frais déjà payés ne laissent
     * plus assez de place, la remise est plafonnée.
     *
     * @return float part de la remise qui n'a pas pu être appliquée (0 en temps normal)
     */
    public function reapply(Registration $registration): float
    {
        if (!$registration->hasDiscount()) {
            return 0.0;
        }

        $lines = $this->eligibleLines($registration);
        $target = min(
            $this->targetAmount($registration->getDiscountType(), (float) $registration->getDiscountValue(), $this->grossTotal($lines)),
            $this->grossTotal($lines)
        );
        $applied = $this->distribute($lines, $target);
        $shortfall = round($target - $applied, 2);

        if ($shortfall > 0) {
            $this->logger->warning('Remise plafonnée : frais déjà payés au-delà du net', [
                'registration' => $registration->getId(),
                'shortfall' => $shortfall,
            ]);
        }

        return $shortfall;
    }

    /**
     * @return StudentFee[]
     */
    public function eligibleLines(Registration $registration): array
    {
        $lines = [];
        foreach ($registration->getStudentFees() as $line) {
            if ($line->getFee()?->isActive() && !$line->isArriereAnterieur()) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Répartit $target au prorata des montants bruts, sans jamais faire passer une ligne
     * sous son montant payé. Montants entiers (F CFA) ; l'écart d'arrondi va aux lignes
     * qui ont encore de la marge. Renvoie le montant effectivement réparti.
     *
     * @param StudentFee[] $lines
     */
    private function distribute(array $lines, float $target): float
    {
        $target = round($target);
        $share = [];
        $cap = [];
        foreach ($lines as $i => $line) {
            $share[$i] = 0.0;
            $cap[$i] = floor(max(0.0, $line->getGrossAmount() - (float) $line->getPaidAmount()));
        }

        // Prorata itératif : une ligne saturée est retirée et le reliquat redistribué.
        $open = array_keys($lines);
        $remaining = $target;
        while ($remaining > 0 && $open !== []) {
            $weight = 0.0;
            foreach ($open as $i) {
                $weight += $lines[$i]->getGrossAmount();
            }
            if ($weight <= 0) {
                break;
            }

            // Une ligne dont la part dépasse sa marge prend sa marge et sort du partage.
            $pool = $remaining;
            $saturated = false;
            foreach ($open as $k => $i) {
                if ($pool * $lines[$i]->getGrossAmount() / $weight >= $cap[$i]) {
                    $share[$i] = $cap[$i];
                    $remaining -= $cap[$i];
                    unset($open[$k]);
                    $saturated = true;
                }
            }
            if ($saturated) {
                continue;
            }

            foreach ($open as $i) {
                $part = floor($remaining * $lines[$i]->getGrossAmount() / $weight);
                $share[$i] += $part;
            }
            $remaining = $target - array_sum($share);

            // Reliquat d'arrondi : 1 F par ligne ayant encore de la marge, plus grosses d'abord.
            usort($open, fn (int $a, int $b) => $lines[$b]->getGrossAmount() <=> $lines[$a]->getGrossAmount());
            foreach ($open as $i) {
                if ($remaining <= 0) {
                    break;
                }
                if ($share[$i] < $cap[$i]) {
                    $share[$i]++;
                    $remaining--;
                }
            }
            break;
        }

        foreach ($lines as $i => $line) {
            $line->applyDiscount($share[$i]);
        }

        return array_sum($share);
    }

    private function targetAmount(?string $type, float $value, float $gross): float
    {
        return $type === Registration::DISCOUNT_PERCENT
            ? round($gross * $value / 100)
            : round($value);
    }

    /**
     * @param StudentFee[] $lines
     */
    private function grossTotal(array $lines): float
    {
        return array_sum(array_map(fn (StudentFee $l) => $l->getGrossAmount(), $lines));
    }

    /**
     * Remise maximale applicable : brut − déjà payé, ligne par ligne.
     *
     * @param StudentFee[] $lines
     */
    private function capacity(array $lines): float
    {
        return array_sum(array_map(fn (StudentFee $l) => floor(max(0.0, $l->getGrossAmount() - (float) $l->getPaidAmount())), $lines));
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }
}
