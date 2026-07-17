<?php

namespace App\Tests\EventSubscriber;

use App\Entity\MultiSchoolOwnedInterface;
use App\Entity\School;
use App\Entity\SchoolOwnedInterface;
use App\Entity\User;
use App\EventSubscriber\SchoolOwnershipSubscriber;
use App\Service\SchoolContextService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SchoolOwnershipSubscriberTest extends TestCase
{
    private function makeEvent(array $arguments, string $path = '/admin/students/1'): ControllerArgumentsEvent
    {
        return new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => new Response(),
            $arguments,
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function subscriber(?User $user, bool $schoolAllowed): SchoolOwnershipSubscriber
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $context = $this->createMock(SchoolContextService::class);
        $context->method('isSchoolAllowed')->willReturn($schoolAllowed);

        return new SchoolOwnershipSubscriber($context, $security);
    }

    private function staffUser(array $roles = ['ROLE_ADMIN']): User
    {
        $user = new User();
        $user->setRoles($roles);

        return $user;
    }

    private function ownedEntity(?School $school): SchoolOwnedInterface
    {
        return new class($school) implements SchoolOwnedInterface {
            public function __construct(private ?School $school) {}
            public function getSchool(): ?School { return $this->school; }
        };
    }

    private function multiOwnedEntity(array $schools): MultiSchoolOwnedInterface
    {
        return new class($schools) implements MultiSchoolOwnedInterface {
            public function __construct(private array $schools) {}
            public function getSchools(): iterable { return $this->schools; }
        };
    }

    public function testForeignSchoolThrows404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $event = $this->makeEvent([$this->ownedEntity(new School())]);
        $this->subscriber($this->staffUser(), schoolAllowed: false)->onControllerArguments($event);
    }

    public function testAllowedSchoolPasses(): void
    {
        $event = $this->makeEvent([$this->ownedEntity(new School())]);
        $this->subscriber($this->staffUser(), schoolAllowed: true)->onControllerArguments($event);
        $this->addToAssertionCount(1); // no exception
    }

    public function testNullSchoolIsNotFiltered(): void
    {
        // getSchool() === null => entité non rattachée : jamais bloquée, même si aucune école n'est autorisée.
        $event = $this->makeEvent([$this->ownedEntity(null)]);
        $this->subscriber($this->staffUser(), schoolAllowed: false)->onControllerArguments($event);
        $this->addToAssertionCount(1);
    }

    public function testRealSuperAdminBypasses(): void
    {
        $event = $this->makeEvent([$this->ownedEntity(new School())]);
        $this->subscriber($this->staffUser(['ROLE_SUPER_ADMIN']), schoolAllowed: false)->onControllerArguments($event);
        $this->addToAssertionCount(1);
    }

    public function testUnauthenticatedIsSkipped(): void
    {
        $event = $this->makeEvent([$this->ownedEntity(new School())]);
        $this->subscriber(null, schoolAllowed: false)->onControllerArguments($event);
        $this->addToAssertionCount(1);
    }

    public function testParentPortalIsSkipped(): void
    {
        $event = $this->makeEvent([$this->ownedEntity(new School())], '/parent/enfant/1');
        $this->subscriber($this->staffUser(['ROLE_PARENT']), schoolAllowed: false)->onControllerArguments($event);
        $this->addToAssertionCount(1);
    }

    public function testMultiSchoolForeignThrows404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $event = $this->makeEvent([$this->multiOwnedEntity([new School()])]);
        $this->subscriber($this->staffUser(), schoolAllowed: false)->onControllerArguments($event);
    }

    public function testMultiSchoolWithNoAttachmentIsNotFiltered(): void
    {
        $event = $this->makeEvent([$this->multiOwnedEntity([])]);
        $this->subscriber($this->staffUser(), schoolAllowed: false)->onControllerArguments($event);
        $this->addToAssertionCount(1);
    }

    public function testNonOwnedArgumentIsIgnored(): void
    {
        $event = $this->makeEvent(['a string', 42, new \stdClass()]);
        $this->subscriber($this->staffUser(), schoolAllowed: false)->onControllerArguments($event);
        $this->addToAssertionCount(1);
    }
}
