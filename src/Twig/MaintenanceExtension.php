<?php

namespace App\Twig;

use App\Service\MaintenanceMode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose l'état du mode maintenance aux gabarits : `maintenance().enabled`,
 * `maintenance().message`, `maintenance().until`…
 */
class MaintenanceExtension extends AbstractExtension
{
    public function __construct(private readonly MaintenanceMode $maintenance)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('maintenance', fn (): MaintenanceMode => $this->maintenance),
        ];
    }
}
