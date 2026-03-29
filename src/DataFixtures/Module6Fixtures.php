<?php

namespace App\DataFixtures;

use App\Entity\Fee;
use App\Entity\Payment;
use App\Entity\Invoice;
use App\Entity\PaymentPlan;
use App\Entity\Scholarship;
use App\Entity\FinancialTransaction;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Entity\Level;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class Module6Fixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer les données existantes
        $schools = $manager->getRepository(School::class)->findAll();
        $students = $manager->getRepository(Student::class)->findAll();
        $users = $manager->getRepository(User::class)->findAll();
        $levels = $manager->getRepository(Level::class)->findAll();

        if (empty($schools) || empty($students) || empty($users)) {
            return; // Pas de données de base disponibles
        }

        // Créer des frais de scolarité
        $this->createFees($manager, $schools, $levels);

        // Créer des paiements
        $this->createPayments($manager, $students, $users);

        // Créer des factures
        $this->createInvoices($manager, $students, $users);

        // Créer des plans de paiement
        $this->createPaymentPlans($manager, $students, $users);

        // Créer des bourses
        $this->createScholarships($manager, $students, $users);

        // Créer des transactions financières
        $this->createFinancialTransactions($manager, $students, $users);

        $manager->flush();
    }

    private function createFees(ObjectManager $manager, array $schools, array $levels): void
    {
        $feeTypes = [
            'Frais de scolarité' => ['obligatoire', 'annuel', 150000],
            'Frais d\'inscription' => ['obligatoire', 'unique', 25000],
            'Frais de transport' => ['optionnel', 'mensuel', 15000],
            'Frais de cantine' => ['optionnel', 'mensuel', 20000],
            'Frais de bibliothèque' => ['obligatoire', 'annuel', 5000],
            'Frais d\'uniforme' => ['obligatoire', 'unique', 30000],
            'Frais de laboratoire' => ['obligatoire', 'trimestriel', 10000],
            'Frais d\'activités sportives' => ['optionnel', 'trimestriel', 8000],
            'Frais de pénalité retard' => ['pénalité', 'unique', 5000],
            'Frais d\'examen' => ['obligatoire', 'unique', 15000],
        ];

        foreach ($schools as $school) {
            foreach ($feeTypes as $name => $data) {
                $fee = new Fee();
                $fee->setName($name);
                $fee->setCode('FRAIS-' . strtoupper(substr($name, 0, 3)) . '-' . mt_rand(100, 999));
                $fee->setSchool($school);
                $fee->setLevel($levels[array_rand($levels)]);
                $fee->setAmount($data[2]);
                $fee->setType($data[0]);
                $fee->setFrequency($data[1]);
                $fee->setDescription("Description pour {$name} - {$school->getName()}");
                
                // Dates aléatoires
                $startDate = new \DateTime();
                $startDate->modify('-' . mt_rand(0, 365) . ' days');
                $fee->setStartDate($startDate);
                
                $endDate = clone $startDate;
                $endDate->modify('+' . mt_rand(30, 365) . ' days');
                $fee->setEndDate($endDate);
                
                $dueDate = clone $startDate;
                $dueDate->modify('+' . mt_rand(1, 30) . ' days');
                $fee->setDueDate($dueDate);
                
                // Remises occasionnelles
                if (mt_rand(1, 4) === 1) {
                    $fee->setDiscountPercentage(mt_rand(5, 20));
                }
                
                $fee->setIsActive(mt_rand(1, 10) > 1); // 90% actifs
                
                $manager->persist($fee);
            }
        }
    }

    private function createPayments(ObjectManager $manager, array $students, array $users): void
    {
        $paymentMethods = ['espèces', 'chèque', 'virement', 'carte', 'mobile_money'];
        $statuses = ['en_attente', 'payé', 'partiellement_payé', 'annulé'];
        
        // Récupérer les frais
        $fees = $manager->getRepository(Fee::class)->findAll();
        
        foreach ($students as $student) {
            $studentFees = array_filter($fees, function($fee) use ($student) {
                return $fee->getSchool() === $student->getSchool();
            });
            
            // Créer 2-5 paiements par élève
            $paymentCount = mt_rand(2, 5);
            
            for ($i = 0; $i < $paymentCount; $i++) {
                $payment = new Payment();
                $payment->setPaymentNumber('PAY-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));
                $payment->setStudent($student);
                $payment->setFee($studentFees[array_rand($studentFees)]);
                $payment->setAmount(mt_rand(10000, 200000));
                $payment->setPaymentDate(new \DateTime('-' . mt_rand(0, 90) . ' days'));
                $payment->setPaymentMethod($paymentMethods[array_rand($paymentMethods)]);
                $payment->setStatus($statuses[array_rand($statuses)]);
                $payment->setRecordedBy($users[array_rand($users)]);
                
                if (mt_rand(1, 3) === 1) {
                    $payment->setReference('REF-' . mt_rand(100000, 999999));
                }
                
                if (mt_rand(1, 4) === 1) {
                    $payment->setNotes('Paiement effectué avec succès');
                }
                
                $manager->persist($payment);
            }
        }
    }

    private function createInvoices(ObjectManager $manager, array $students, array $users): void
    {
        $statuses = ['brouillon', 'envoyée', 'payée', 'partiellement_payée', 'en_retard'];
        
        // Récupérer les frais
        $fees = $manager->getRepository(Fee::class)->findAll();
        
        foreach ($students as $student) {
            $studentFees = array_filter($fees, function($fee) use ($student) {
                return $fee->getSchool() === $student->getSchool();
            });
            
            // Créer 1-3 factures par élève
            $invoiceCount = mt_rand(1, 3);
            
            for ($i = 0; $i < $invoiceCount; $i++) {
                $invoice = new Invoice();
                $invoice->setInvoiceNumber('INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));
                $invoice->setStudent($student);
                $invoice->setFee($studentFees[array_rand($studentFees)]);
                $invoice->setTotalAmount(mt_rand(50000, 300000));
                $invoice->setPaidAmount(mt_rand(0, (int)$invoice->getTotalAmount()));
                $invoice->setRemainingAmount((int)$invoice->getTotalAmount() - (int)$invoice->getPaidAmount());
                $invoice->setIssueDate(new \DateTime('-' . mt_rand(0, 60) . ' days'));
                $invoice->setDueDate(new \DateTime('+' . mt_rand(1, 30) . ' days'));
                $invoice->setStatus($statuses[array_rand($statuses)]);
                $invoice->setCreatedBy($users[array_rand($users)]);
                
                if (mt_rand(1, 3) === 1) {
                    $invoice->setDiscountPercentage(mt_rand(5, 15));
                }
                
                if (mt_rand(1, 4) === 1) {
                    $invoice->setNotes('Facture générée automatiquement');
                }
                
                $manager->persist($invoice);
            }
        }
    }

    private function createPaymentPlans(ObjectManager $manager, array $students, array $users): void
    {
        $frequencies = ['mensuel', 'trimestriel', 'semestriel'];
        $statuses = ['actif', 'suspendu', 'terminé', 'annulé'];
        
        // Récupérer les frais
        $fees = $manager->getRepository(Fee::class)->findAll();
        
        foreach ($students as $student) {
            $studentFees = array_filter($fees, function($fee) use ($student) {
                return $fee->getSchool() === $student->getSchool();
            });
            
            // Créer 0-2 plans de paiement par élève
            $planCount = mt_rand(0, 2);
            
            for ($i = 0; $i < $planCount; $i++) {
                $plan = new PaymentPlan();
                $plan->setName('Plan de paiement ' . ($i + 1) . ' - ' . $student->getFullName());
                $plan->setStudent($student);
                $plan->setFee($studentFees[array_rand($studentFees)]);
                $plan->setTotalAmount(mt_rand(100000, 500000));
                $plan->setPaidAmount(mt_rand(0, (int)$plan->getTotalAmount()));
                $plan->setRemainingAmount((int)$plan->getTotalAmount() - (int)$plan->getPaidAmount());
                $plan->setStartDate(new \DateTime('-' . mt_rand(0, 180) . ' days'));
                $plan->setEndDate(new \DateTime('+' . mt_rand(30, 365) . ' days'));
                $plan->setInstallmentCount(mt_rand(3, 12));
                $plan->setInstallmentAmount($plan->getTotalAmount() / $plan->getInstallmentCount());
                $plan->setFrequency($frequencies[array_rand($frequencies)]);
                $plan->setStatus($statuses[array_rand($statuses)]);
                $plan->setCreatedBy($users[array_rand($users)]);
                
                if (mt_rand(1, 3) === 1) {
                    $plan->setNotes('Plan de paiement étalé sur ' . $plan->getInstallmentCount() . ' échéances');
                }
                
                $manager->persist($plan);
            }
        }
    }

    private function createScholarships(ObjectManager $manager, array $students, array $users): void
    {
        $types = ['montant_fixe', 'pourcentage', 'gratuité_totale'];
        $statuses = ['active', 'suspendue', 'expirée', 'annulée'];
        $scholarshipNames = [
            'Bourse d\'excellence',
            'Bourse sociale',
            'Bourse sportive',
            'Bourse culturelle',
            'Bourse mérite',
            'Bourse orphelin',
            'Bourse handicap',
            'Bourse parrainage',
        ];
        
        foreach ($students as $student) {
            // 30% de chance d'avoir une bourse
            if (mt_rand(1, 10) <= 3) {
                $scholarship = new Scholarship();
                $scholarship->setName($scholarshipNames[array_rand($scholarshipNames)]);
                $scholarship->setStudent($student);
                $scholarship->setType($types[array_rand($types)]);
                $scholarship->setStatus($statuses[array_rand($statuses)]);
                $scholarship->setStartDate(new \DateTime('-' . mt_rand(0, 365) . ' days'));
                $scholarship->setEndDate(new \DateTime('+' . mt_rand(30, 365) . ' days'));
                $scholarship->setGrantedBy($users[array_rand($users)]);
                
                if ($scholarship->getType() === 'montant_fixe') {
                    $scholarship->setAmount(mt_rand(25000, 150000));
                } elseif ($scholarship->getType() === 'pourcentage') {
                    $scholarship->setPercentage(mt_rand(10, 100));
                }
                
                $scholarship->setDescription('Bourse accordée pour ' . $scholarship->getName());
                $scholarship->setSponsor(['Ministère de l\'Éducation', 'ONG Internationale', 'Entreprise locale', 'Fondation privée'][array_rand(['Ministère de l\'Éducation', 'ONG Internationale', 'Entreprise locale', 'Fondation privée'])]);
                
                if (mt_rand(1, 3) === 1) {
                    $scholarship->setConditions('Maintien de la moyenne >= 12/20');
                }
                
                $manager->persist($scholarship);
            }
        }
    }

    private function createFinancialTransactions(ObjectManager $manager, array $students, array $users): void
    {
        $types = ['entrée', 'sortie', 'transfert'];
        $categories = ['paiement', 'remboursement', 'bourse', 'frais', 'autre'];
        $methods = ['espèces', 'chèque', 'virement', 'carte', 'mobile_money'];
        $statuses = ['en_attente', 'confirmé', 'annulé', 'en_erreur'];
        
        // Récupérer les paiements et factures
        $payments = $manager->getRepository(Payment::class)->findAll();
        $invoices = $manager->getRepository(Invoice::class)->findAll();
        $scholarships = $manager->getRepository(Scholarship::class)->findAll();
        
        foreach ($students as $student) {
            // Créer 3-8 transactions par élève
            $transactionCount = mt_rand(3, 8);
            
            for ($i = 0; $i < $transactionCount; $i++) {
                $transaction = new FinancialTransaction();
                $transaction->setTransactionNumber('TXN-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));
                $transaction->setType($types[array_rand($types)]);
                $transaction->setCategory($categories[array_rand($categories)]);
                $transaction->setAmount(mt_rand(5000, 200000));
                $transaction->setTransactionDate(new \DateTime('-' . mt_rand(0, 90) . ' days'));
                $transaction->setPaymentMethod($methods[array_rand($methods)]);
                $transaction->setStatus($statuses[array_rand($statuses)]);
                $transaction->setStudent($student);
                $transaction->setRecordedBy($users[array_rand($users)]);
                
                // Lier à des entités existantes
                if (mt_rand(1, 3) === 1 && !empty($payments)) {
                    $transaction->setPayment($payments[array_rand($payments)]);
                }
                if (mt_rand(1, 4) === 1 && !empty($invoices)) {
                    $transaction->setInvoice($invoices[array_rand($invoices)]);
                }
                if (mt_rand(1, 5) === 1 && !empty($scholarships)) {
                    $transaction->setScholarship($scholarships[array_rand($scholarships)]);
                }
                
                if (mt_rand(1, 3) === 1) {
                    $transaction->setReference('REF-' . mt_rand(100000, 999999));
                }
                
                if (mt_rand(1, 4) === 1) {
                    $transaction->setDescription('Transaction ' . $transaction->getCategoryLabel());
                }
                
                $manager->persist($transaction);
            }
        }
    }

    public function getDependencies(): array
    {
        return [
            Module1Fixtures::class,
            Module2Fixtures::class,
            Module3Fixtures::class,
        ];
    }
}
