<?php

namespace App\Tests\Entity;

use App\Entity\Fee;
use App\Entity\Invoice;
use App\Entity\Level;
use App\Entity\Payment;
use App\Entity\School;
use PHPUnit\Framework\TestCase;

class FeeTest extends TestCase
{
    private Fee $fee;

    protected function setUp(): void
    {
        $this->fee = new Fee();
    }

    public function testNewFeeHasDefaults(): void
    {
        $this->assertNull($this->fee->getId());
        $this->assertTrue($this->fee->isActive());
        $this->assertSame('obligatoire', $this->fee->getType());
        $this->assertSame('unique', $this->fee->getFrequency());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->fee->getCreatedAt());
    }

    public function testGetFinalAmountWithNoDiscount(): void
    {
        $this->fee->setAmount('50000');

        $this->assertSame(50000.0, $this->fee->getFinalAmount());
    }

    public function testGetFinalAmountWithPercentageDiscount(): void
    {
        $this->fee->setAmount('100000');
        $this->fee->setDiscountPercentage('10');

        $this->assertSame(90000.0, $this->fee->getFinalAmount());
    }

    public function testGetFinalAmountWithFixedDiscount(): void
    {
        $this->fee->setAmount('100000');
        $this->fee->setDiscountAmount('25000');

        $this->assertSame(75000.0, $this->fee->getFinalAmount());
    }

    public function testGetFinalAmountWithBothDiscounts(): void
    {
        $this->fee->setAmount('100000');
        $this->fee->setDiscountPercentage('10');  // -10 000
        $this->fee->setDiscountAmount('5000');    // -5 000

        $this->assertSame(85000.0, $this->fee->getFinalAmount());
    }

    public function testGetFinalAmountNeverNegative(): void
    {
        $this->fee->setAmount('10000');
        $this->fee->setDiscountAmount('50000');

        $this->assertSame(0.0, $this->fee->getFinalAmount());
    }

    public function testGetFinalAmountWith100PercentDiscount(): void
    {
        $this->fee->setAmount('50000');
        $this->fee->setDiscountPercentage('100');

        $this->assertSame(0.0, $this->fee->getFinalAmount());
    }

    public function testTypeLabel(): void
    {
        $cases = [
            'obligatoire' => 'Obligatoire',
            'optionnel' => 'Optionnel',
            'pénalité' => 'Pénalité',
        ];

        foreach ($cases as $type => $expected) {
            $this->fee->setType($type);
            $this->assertSame($expected, $this->fee->getTypeLabel());
        }
    }

    public function testFrequencyLabel(): void
    {
        $cases = [
            'unique' => 'Unique',
            'mensuel' => 'Mensuel',
            'trimestriel' => 'Trimestriel',
            'annuel' => 'Annuel',
        ];

        foreach ($cases as $frequency => $expected) {
            $this->fee->setFrequency($frequency);
            $this->assertSame($expected, $this->fee->getFrequencyLabel());
        }
    }

    public function testPaymentsCollection(): void
    {
        $payment = new Payment();

        $this->fee->addPayment($payment);
        $this->assertCount(1, $this->fee->getPayments());
        $this->assertSame($this->fee, $payment->getFee());

        $this->fee->addPayment($payment);
        $this->assertCount(1, $this->fee->getPayments());

        $this->fee->removePayment($payment);
        $this->assertCount(0, $this->fee->getPayments());
    }

    public function testInvoicesCollection(): void
    {
        $invoice = new Invoice();

        $this->fee->addInvoice($invoice);
        $this->assertCount(1, $this->fee->getInvoices());
        $this->assertSame($this->fee, $invoice->getFee());

        $this->fee->removeInvoice($invoice);
        $this->assertCount(0, $this->fee->getInvoices());
    }

    public function testSetSchoolAndLevel(): void
    {
        $school = new School();
        $level = new Level();

        $this->fee->setSchool($school);
        $this->fee->setLevel($level);

        $this->assertSame($school, $this->fee->getSchool());
        $this->assertSame($level, $this->fee->getLevel());
    }

    public function testToStringReturnsName(): void
    {
        $this->fee->setName('Frais de scolarité');
        $this->assertSame('Frais de scolarité', (string) $this->fee);
    }

    public function testToStringReturnsEmptyWhenNoName(): void
    {
        $this->assertSame('', (string) $this->fee);
    }
}
