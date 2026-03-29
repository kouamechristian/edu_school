<?php

namespace App\Tests\Entity;

use App\Entity\Fee;
use App\Entity\Payment;
use App\Entity\Student;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private Payment $payment;

    protected function setUp(): void
    {
        $this->payment = new Payment();
    }

    public function testNewPaymentHasDefaults(): void
    {
        $this->assertSame('en_attente', $this->payment->getStatus());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->payment->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->payment->getPaymentDate());
    }

    public function testIsPaid(): void
    {
        $this->assertFalse($this->payment->isPaid());

        $this->payment->setStatus('payé');
        $this->assertTrue($this->payment->isPaid());
    }

    public function testIsPending(): void
    {
        $this->assertTrue($this->payment->isPending());

        $this->payment->setStatus('payé');
        $this->assertFalse($this->payment->isPending());
    }

    public function testIsCancelled(): void
    {
        $this->assertFalse($this->payment->isCancelled());

        $this->payment->setStatus('annulé');
        $this->assertTrue($this->payment->isCancelled());
    }

    public function testStatusLabel(): void
    {
        $cases = [
            'en_attente' => 'En attente',
            'payé' => 'Payé',
            'partiellement_payé' => 'Partiellement payé',
            'annulé' => 'Annulé',
            'remboursé' => 'Remboursé',
        ];

        foreach ($cases as $status => $expected) {
            $this->payment->setStatus($status);
            $this->assertSame($expected, $this->payment->getStatusLabel());
        }
    }

    public function testStatusColor(): void
    {
        $this->payment->setStatus('payé');
        $this->assertSame('success', $this->payment->getStatusColor());

        $this->payment->setStatus('en_attente');
        $this->assertSame('warning', $this->payment->getStatusColor());

        $this->payment->setStatus('annulé');
        $this->assertSame('danger', $this->payment->getStatusColor());
    }

    public function testPaymentMethodLabel(): void
    {
        $cases = [
            'espèces' => 'Espèces',
            'chèque' => 'Chèque',
            'virement' => 'Virement',
            'carte' => 'Carte bancaire',
            'mobile_money' => 'Mobile Money',
        ];

        foreach ($cases as $method => $expected) {
            $this->payment->setPaymentMethod($method);
            $this->assertSame($expected, $this->payment->getPaymentMethodLabel());
        }
    }

    public function testRelations(): void
    {
        $student = new Student();
        $fee = new Fee();
        $user = new User();

        $this->payment->setStudent($student);
        $this->payment->setFee($fee);
        $this->payment->setRecordedBy($user);

        $this->assertSame($student, $this->payment->getStudent());
        $this->assertSame($fee, $this->payment->getFee());
        $this->assertSame($user, $this->payment->getRecordedBy());
    }

    public function testToStringReturnsPaymentNumber(): void
    {
        $this->payment->setPaymentNumber('PAY-2026-001');
        $this->assertSame('PAY-2026-001', (string) $this->payment);
    }
}
