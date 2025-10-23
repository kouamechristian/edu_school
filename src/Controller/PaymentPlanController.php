<?php

namespace App\Controller;

use App\Entity\PaymentPlan;
use App\Repository\PaymentPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/payment-plan')]
class PaymentPlanController extends AbstractController
{
    #[Route('/', name: 'admin_payment_plan_index', methods: ['GET'])]
    public function index(PaymentPlanRepository $paymentPlanRepository): Response
    {
        return $this->render('payment_plan/index.html.twig', [
            'payment_plans' => $paymentPlanRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_payment_plan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $paymentPlan = new PaymentPlan();
        $form = $this->createFormBuilder($paymentPlan)
            ->add('name')
            ->add('description')
            ->add('totalAmount')
            ->add('installmentCount')
            ->add('installmentAmount')
            ->add('startDate')
            ->add('endDate')
            ->add('isActive')
            ->getForm();
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($paymentPlan);
            $entityManager->flush();

            return $this->redirectToRoute('admin_payment_plan_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment_plan/new.html.twig', [
            'payment_plan' => $paymentPlan,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_payment_plan_show', methods: ['GET'])]
    public function show(PaymentPlan $paymentPlan): Response
    {
        return $this->render('payment_plan/show.html.twig', [
            'payment_plan' => $paymentPlan,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_payment_plan_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PaymentPlan $paymentPlan, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createFormBuilder($paymentPlan)
            ->add('name')
            ->add('description')
            ->add('totalAmount')
            ->add('installmentCount')
            ->add('installmentAmount')
            ->add('startDate')
            ->add('endDate')
            ->add('isActive')
            ->getForm();
            
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_payment_plan_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment_plan/edit.html.twig', [
            'payment_plan' => $paymentPlan,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_payment_plan_delete', methods: ['POST'])]
    public function delete(Request $request, PaymentPlan $paymentPlan, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paymentPlan->getId(), $request->request->get('_token'))) {
            $entityManager->remove($paymentPlan);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_payment_plan_index', [], Response::HTTP_SEE_OTHER);
    }
}
