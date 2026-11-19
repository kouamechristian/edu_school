<?php

namespace App\Twig;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Lien de téléchargement de l'application mobile EDU-SCHOOL Parent, proposé dans
 * l'espace parent : `parent_app()` renvoie null tant qu'aucune application n'est
 * disponible, sinon ['url' => lien absolu, 'qr' => QR code (data URI PNG)].
 *
 * L'adresse vient de PARENT_APP_ANDROID_URL :
 *  - chemin relatif (défaut « /downloads/edu-school-parent.apk ») : l'APK est
 *    servi par le site et n'est proposé que si le fichier existe dans public/ ;
 *  - URL absolue (Play Store, stockage externe) : toujours proposée.
 */
class ParentAppExtension extends AbstractExtension
{
    /** @var array{url: string, qr: string}|null|false false = pas encore calculé */
    private array|null|false $cache = false;

    public function __construct(
        #[Autowire('%parent_app.android_url%')] private readonly string $androidUrl,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('parent_app', fn (): ?array => $this->resolve()),
        ];
    }

    /**
     * size / updatedAt : renseignés seulement pour un APK servi par le site.
     *
     * @return array{url: string, qr: string, size: ?int, updatedAt: ?\DateTimeImmutable}|null
     */
    private function resolve(): ?array
    {
        if ($this->cache !== false) {
            return $this->cache;
        }

        $url = trim($this->androidUrl);
        if ($url === '') {
            return $this->cache = null;
        }

        $size = null;
        $updatedAt = null;
        if (!preg_match('#^https?://#i', $url)) {
            // Fichier servi par le site : proposé seulement s'il a bien été déposé.
            $path = '/' . ltrim($url, '/');
            $file = $this->projectDir . '/public' . $path;
            if (!is_file($file)) {
                return $this->cache = null;
            }
            $size = filesize($file) ?: null;
            $updatedAt = ($mtime = filemtime($file)) ? (new \DateTimeImmutable())->setTimestamp($mtime) : null;

            $request = $this->requestStack->getCurrentRequest();
            $base = $request ? $request->getSchemeAndHttpHost() . $request->getBasePath() : '';
            // ?v= : un nouvel APK n'est jamais servi depuis un cache (navigateur, proxy).
            $url = $base . $path . ($updatedAt ? '?v=' . $updatedAt->getTimestamp() : '');
        }

        $qr = (new PngWriter())->write(QrCode::create($url)->setSize(220)->setMargin(8))->getDataUri();

        return $this->cache = ['url' => $url, 'qr' => $qr, 'size' => $size, 'updatedAt' => $updatedAt];
    }
}
