<?php

namespace App\Tests\Controller;

use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Bouton « Générer les comptes parents » de /admin/users/parents (base de test, transaction annulée).
 */
class ParentAccountsGenerationTest extends WebTestCase
{
    public function testButtonGeneratesAccountsAndShowsCredentialsOnce(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->beginTransaction();

        try {
            $school = (new School())->setName('École Bouton Test')->setType('PRESCOLAIRE-PRIMAIRE');
            $em->persist($school);

            $admin = (new User())
                ->setUsername('admin.generation.test')
                ->setEmail('admin.generation.test@example.test')
                ->setFirstName('Admin')
                ->setLastName('TEST')
                ->setUserType('admin')
                ->setRoles(['ROLE_ADMIN'])
                ->setPassword('x');
            $admin->addSchool($school);
            $em->persist($admin);

            $child = (new Student())
                ->setLastName('KOUASSI')->setFirstName('Awa')->setSchool($school)
                ->setParentalAuthority('mother')
                ->setMotherLastName('TOURE')->setMotherFirstName('Mariam')->setMotherPhone('0707070707');
            $em->persist($child);
            $em->flush();

            $childId = $child->getId();
            $client->loginUser($admin);

            $crawler = $client->request('GET', '/admin/users/parents');
            $this->assertResponseIsSuccessful();
            $button = $crawler->filter('form[action$="/admin/users/parents/generate"] button');
            $this->assertCount(1, $button);
            $this->assertStringContainsString('1', $button->filter('.badge')->text());

            $client->submit($button->form());
            $this->assertResponseIsSuccessful();
            $this->assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
            $this->assertSelectorTextContains('body', 'mariam.toure');
            $this->assertSelectorTextContains('body', 'KOUASSI Awa');

            $child = static::getContainer()->get(EntityManagerInterface::class)->find(Student::class, $childId);
            $this->assertNotNull($child->getParentUser());
            $this->assertSame('TOURE', $child->getParentUser()->getLastName());

            // Bouton « Imprimer les accès » : PDF non mis en cache.
            $crawler = $client->request('GET', '/admin/users/parents');
            $print = $crawler->filter('form[action$="/admin/users/parents/fiche-acces"] button');
            $this->assertCount(1, $print);
            $client->submit($print->form());
            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
            $this->assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
            $this->assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());
        } finally {
            $em->getConnection()->rollBack();
        }
    }
}
