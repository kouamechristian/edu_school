<?php

namespace App\Service\GeniusPay;

/**
 * Échec d'un échange avec GeniusPay. Le message est destiné à être montré au
 * parent : il ne doit contenir ni secret, ni détail d'implémentation.
 */
class GeniusPayException extends \RuntimeException
{
}
