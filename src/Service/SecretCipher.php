<?php

namespace App\Service;

/**
 * Chiffrement symétrique des secrets stockés en base (clés de passerelle de paiement…).
 *
 * La clé est dérivée d'APP_SECRET : les secrets marchands ne sont donc jamais
 * lisibles depuis un simple accès en lecture à la base de données.
 *
 * ⚠️ Changer APP_SECRET rend les secrets déjà chiffrés indéchiffrables : il faut
 * les ressaisir. C'est le compromis assumé pour ne pas introduire un second
 * secret à gérer.
 */
class SecretCipher
{
    private readonly string $key;

    public function __construct(string $appSecret)
    {
        // sodium exige une clé de 32 octets : on dérive celle d'APP_SECRET.
        $this->key = hash('sha256', 'edu_school.secret_cipher.v1|' . $appSecret, true);
    }

    /**
     * Chiffre une valeur. Renvoie null pour une valeur vide, afin que « champ
     * laissé vide » se distingue de « chaîne vide chiffrée ».
     */
    public function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    /**
     * Déchiffre une valeur. Renvoie null si la donnée est absente, corrompue,
     * ou chiffrée avec un autre APP_SECRET — jamais d'exception : un secret
     * illisible doit désactiver la fonctionnalité, pas casser la page.
     */
    public function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }

        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $payload = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($payload, $nonce, $this->key);

        return $plain === false ? null : $plain;
    }

    /**
     * Masque un secret pour l'affichage : « sk_live_abcd…wxyz ».
     */
    public function mask(?string $plain): string
    {
        if ($plain === null || $plain === '') {
            return '—';
        }
        if (strlen($plain) <= 12) {
            return str_repeat('•', strlen($plain));
        }

        return substr($plain, 0, 8) . '…' . substr($plain, -4);
    }
}
