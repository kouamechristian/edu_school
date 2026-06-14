<?php

namespace App\Controller;

use App\Entity\CashDeposit;
use App\Entity\CashRegister;
use App\Entity\FinancialTransaction;
use App\Repository\CashDepositRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\PaymentRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/cash-register', name: 'admin_cash_register_')]
#[IsGranted('ROLE_CAISSE')]
class CashRegisterController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        PaymentRepository $paymentRepository,
        CashDepositRepository $cashDepositRepository
    ): Response {
        $school = $contextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$school || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $cashRegister = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        $paymentsTotal = 0.0;
        $depositsTotal = 0.0;
        $deposits = [];
        if ($cashRegister) {
            $paymentsTotal = $paymentRepository->getTotalAmountByCashRegister($cashRegister->getId());
            $depositsTotal = $cashDepositRepository->getTotalByCashRegister($cashRegister->getId());
            $deposits = $cashDepositRepository->findByCashRegister($cashRegister->getId());
        }

        // Solde actuel = ouverture + encaissements - versements
        $currentBalance = $cashRegister
            ? (float) $cashRegister->getOpeningBalance() + $paymentsTotal - $depositsTotal
            : 0.0;

        // Caisse en ligne de l'établissement (paiements mobile/passerelle).
        $onlineCashRegister = $cashRegisterRepository->findOnlineForSchool($school);
        $onlineTotal = 0.0;
        $onlineCount = 0;
        if ($onlineCashRegister) {
            $onlineTotal = $paymentRepository->getTotalAmountByCashRegister($onlineCashRegister->getId());
            $onlineCount = $paymentRepository->count(['cashRegister' => $onlineCashRegister]);
        }

        return $this->render('cash_register/index.html.twig', [
            'current_school' => $school,
            'cash_register' => $cashRegister,
            'payments_total' => $paymentsTotal,
            'deposits_total' => $depositsTotal,
            'deposits' => $deposits,
            'current_balance' => $currentBalance,
            'online_cash_register' => $onlineCashRegister,
            'online_total' => $onlineTotal,
            'online_count' => $onlineCount,
        ]);
    }

    #[Route('/deposit', name: 'deposit', methods: ['GET', 'POST'])]
    public function deposit(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        PaymentRepository $paymentRepository,
        CashDepositRepository $cashDepositRepository,
        \App\Repository\TransactionTypeRepository $transactionTypeRepository,
        \App\Service\NotificationService $notificationService
    ): Response {
        $school = $contextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$school || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $cashRegister = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        if (!$cashRegister) {
            $this->addFlash('warning', 'Vous devez ouvrir votre caisse avant d\'effectuer un versement.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        if (!$cashRegister->isValidated()) {
            $this->addFlash('warning', 'Votre caisse n’a pas encore été validée par le fondateur. Vous ne pouvez pas effectuer de versement tant qu’elle n’est pas validée.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        // Solde disponible
        $paymentsTotal = $paymentRepository->getTotalAmountByCashRegister($cashRegister->getId());
        $depositsTotal = $cashDepositRepository->getTotalByCashRegister($cashRegister->getId());
        $currentBalance = (float) $cashRegister->getOpeningBalance() + $paymentsTotal - $depositsTotal;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('deposit', $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('admin_cash_register_deposit');
            }

            $reference = trim((string) $request->request->get('reference', ''));
            $amount = (float) $request->request->get('amount', 0);

            if ($reference === '') {
                $this->addFlash('error', 'La référence du bordereau de versement est obligatoire.');
            } elseif ($amount <= 0) {
                $this->addFlash('error', 'Le montant versé doit être supérieur à zéro.');
            } elseif ($amount > $currentBalance) {
                $this->addFlash('error', sprintf(
                    'Le montant versé (%s F) dépasse le solde disponible en caisse (%s F).',
                    number_format($amount, 0, ',', ' '),
                    number_format($currentBalance, 0, ',', ' ')
                ));
            } else {
                $formattedAmount = (string) number_format($amount, 2, '.', '');

                $deposit = (new CashDeposit())
                    ->setCashRegister($cashRegister)
                    ->setReference($reference)
                    ->setAmount($formattedAmount)
                    ->setRecordedBy($cashier)
                    ->setNotes(trim((string) $request->request->get('notes', '')) ?: null);

                $entityManager->persist($deposit);

                // Trace dans les transactions financières (sortie de caisse vers la banque).
                $transaction = (new FinancialTransaction())
                    ->setTransactionNumber($this->generateTransactionNumber($entityManager))
                    ->setSchool($cashRegister->getSchool())
                    ->setType('transfert')
                    ->setTransactionType($transactionTypeRepository->findOneBy(['name' => 'Versement bancaire']))
                    ->setCategory('autre')
                    ->setAmount($formattedAmount)
                    ->setTransactionDate(new \DateTime())
                    ->setPaymentMethod('espèces')
                    ->setStatus('confirmé')
                    ->setReference($reference)
                    ->setDescription(sprintf(
                        'Versement en banque depuis la caisse de %s (bordereau %s)',
                        $cashier->getFullName(),
                        $reference
                    ))
                    ->setRecordedBy($cashier);

                $entityManager->persist($transaction);

                // Notifier les fondateurs qu'un versement attend leur approbation.
                $notificationService->notifyRole(
                    'ROLE_FONDATEUR',
                    'Nouveau versement à approuver',
                    sprintf(
                        'Un versement de %s F (bordereau %s) effectué par %s attend votre approbation.',
                        number_format($amount, 0, ',', ' '),
                        $reference,
                        $cashier->getFullName()
                    ),
                    $this->generateUrl('fondateur_versements'),
                    'fa-money-bill-transfer'
                );

                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Versement de %s F enregistré (bordereau %s). Nouveau solde : %s F.',
                    number_format($amount, 0, ',', ' '),
                    $reference,
                    number_format($currentBalance - $amount, 0, ',', ' ')
                ));

                return $this->redirectToRoute('admin_cash_register_index');
            }
        }

        return $this->render('cash_register/deposit.html.twig', [
            'current_school' => $school,
            'cash_register' => $cashRegister,
            'current_balance' => $currentBalance,
        ]);
    }

    #[Route('/open', name: 'open', methods: ['GET', 'POST'])]
    public function open(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        \App\Service\NotificationService $notificationService
    ): Response {
        $school = $contextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$school || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $existing = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        if ($existing) {
            $this->addFlash('info', 'Votre caisse est déjà ouverte.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        if ($request->isMethod('POST')) {
            $openingBalance = (float) $request->request->get('opening_balance', 0);
            if ($openingBalance < 0) {
                $this->addFlash('error', 'Le solde d\'ouverture doit être positif ou zéro.');
                return $this->redirectToRoute('admin_cash_register_open');
            }

            $cashRegister = (new CashRegister())
                ->setSchool($school)
                ->setCashier($cashier)
                ->setOpeningBalance((string) number_format($openingBalance, 2, '.', ''));

            $entityManager->persist($cashRegister);
            $entityManager->flush();

            // Notifier les fondateurs qu'une nouvelle caisse attend leur validation.
            $notificationService->notifyRole(
                'ROLE_FONDATEUR',
                'Nouvelle caisse à valider',
                sprintf(
                    'La caisse de %s (%s) vient d\'être ouverte et attend votre validation.',
                    $cashier->getFullName(),
                    $school->getName()
                ),
                $this->generateUrl('fondateur_validations'),
                'fa-cash-register'
            );
            $entityManager->flush();

            $this->addFlash('success', 'Caisse ouverte avec succès. Le fondateur a été notifié pour la validation.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        return $this->render('cash_register/open.html.twig', [
            'current_school' => $school,
        ]);
    }

    /**
     * Génère un numéro de transaction unique pour un versement (VST-AAAAMMJJ-NNNN).
     */
    private function generateTransactionNumber(EntityManagerInterface $entityManager): string
    {
        $prefix = 'VST-' . date('Ymd') . '-';

        $count = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(FinancialTransaction::class, 't')
            ->where('t.transactionNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}

