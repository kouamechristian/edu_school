<?php

namespace App\Tests\Controller;

use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Invitation « Téléchargez l'application » de l'espace parent (base de test, transaction annulée).
 */
class ParentAppPromoTest extends WebTestCase
{
    private const APK = '/public/downloads/edu-school-parent.apk';

    private function apkPath(): string
    {
        return dirname(__DIR__, 2) . self::APK;
    }

    /**
     * @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: EntityManagerInterface}
     */
    private function loggedParent(): array
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->beginTransaction();

        $school = (new School())->setName('École Promo Test')->setType('PRESCOLAIRE-PRIMAIRE');
        $em->persist($school);
        $parent = (new User())
            ->setUsername('jean.promo')
            ->setEmail('parent.jean.promo@auto.edu-school.local')
            ->setFirstName('Jean')
            ->setLastName('PROMO')
            ->setUserType('parent')
            ->setRoles(['ROLE_PARENT'])
            ->setPassword('x');
        $parent->addSchool($school);
        $em->persist($parent);
        $em->flush();
        $client->loginUser($parent);

        return [$client, $em];
    }

    public function testPromoShownWithQrWhenApkIsAvailable(): void
    {
        [$client, $em] = $this->loggedParent();
        $created = !is_file($this->apkPath());
        if ($created) {
            file_put_contents($this->apkPath(), 'apk de test');
        }

        try {
            $crawler = $client->request('GET', '/parent');
            $this->assertResponseIsSuccessful();
            $this->assertCount(1, $crawler->filter('#pp-app-promo'));
            $this->assertStringEndsWith('/downloads/edu-school-parent.apk', $crawler->filter('[data-pp-app-android]')->attr('href'));
            $this->assertStringStartsWith('data:image/png;base64,', $crawler->filter('.pp-app-qr img')->attr('src'));
            // Lien permanent dans le menu utilisateur.
            $this->assertCount(1, $crawler->filter('.pp-dropdown-menu a:contains("Application mobile")'));
            // E-mail technique remplacé par l'identifiant.
            $this->assertSelectorTextContains('.pp-dd-head', 'Identifiant : jean.promo');
        } finally {
            if ($created) {
                unlink($this->apkPath());
            }
            $em->getConnection()->rollBack();
        }
    }

    public function testPromoHiddenWhenNoApk(): void
    {
        if (is_file($this->apkPath())) {
            $this->markTestSkipped('Un APK réel est présent dans public/downloads.');
        }
        [$client, $em] = $this->loggedParent();

        try {
            $crawler = $client->request('GET', '/parent');
            $this->assertResponseIsSuccessful();
            $this->assertCount(0, $crawler->filter('#pp-app-promo'));
            $this->assertCount(0, $crawler->filter('.pp-dropdown-menu a:contains("Application mobile")'));
        } finally {
            $em->getConnection()->rollBack();
        }
    }
}
