<?php

namespace App\Twig;

use App\Service\GradeCalculationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private GradeCalculationService $gradeCalculationService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getAppreciation', [$this->gradeCalculationService, 'getAppreciation']),
            new TwigFunction('getMention', [$this->gradeCalculationService, 'getMention']),
            new TwigFunction('countFrequency', [$this, 'countFrequency']),
        ];
    }

    public function countFrequency(array $items, string $frequency): int
    {
        return count(array_filter($items, function($item) use ($frequency) {
            return $item->getFrequency() === $frequency;
        }));
    }
}
