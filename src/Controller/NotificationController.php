<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications', name: 'notification_')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(NotificationRepository $repository, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        return $this->render('notification/index.html.twig', [
            'notifications' => $paginator->paginate($repository->findForUser($this->getUser()), $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/{id}/lire', name: 'read', methods: ['GET'])]
    public function read(Notification $notification, EntityManagerInterface $entityManager): Response
    {
        if ($notification->getRecipient() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        return $this->redirect($notification->getLink() ?: $this->generateUrl('notification_index'));
    }

    #[Route('/tout-lire', name: 'read_all', methods: ['POST'])]
    public function readAll(Request $request, NotificationRepository $repository, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('read_all', $request->request->get('_token'))) {
            foreach ($repository->findForUser($this->getUser()) as $notification) {
                $notification->setIsRead(true);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');
        }

        return $this->redirectToRoute('notification_index');
    }
}
