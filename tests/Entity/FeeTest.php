<?php

namespace App\Tests\Entity;

use App\Entity\Fee;
use App\Entity\FeeSchedule;
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
        $this->assertSame('pour_tous', $this->fee->getType());
        $this->assertSame('unique', $this->fee->getFrequency());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->fee->getCreatedAt());
    }

    public function testGetFinalAmountReturnsAmount(): void
    {
        $this->fee->setAmount('50000');

        $this->assertSame(50000.0, $this->fee->getFinalAmount());
    }

    public function testTypeLabel(): void
    {
        $cases = [
            'pour_tous' => 'Pour tous',
            'affecte' => 'Affecté',
            'non_affecte' => 'Non affecté',
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

    public function testSchedulesCollection(): void
    {
        $schedule = new FeeSchedule();
        $schedule->setOrderNumber(1);
        $schedule->setAmount('50000');
        $schedule->setDueDate(new \DateTime('+30 days'));

        $this->fee->addSchedule($schedule);
        $this->assertCount(1, $this->fee->getSchedules());
        $this->assertSame($this->fee, $schedule->getFee());

        $this->fee->addSchedule($schedule);
        $this->assertCount(1, $this->fee->getSchedules());

        $this->fee->removeSchedule($schedule);
        $this->assertCount(0, $this->fee->getSchedules());
    }

    public function testSchedulesTotalAmount(): void
    {
        $s1 = new FeeSchedule();
        $s1->setOrderNumber(1);
        $s1->setAmount('30000');
        $s1->setDueDate(new \DateTime('+30 days'));

        $s2 = new FeeSchedule();
        $s2->setOrderNumber(2);
        $s2->setAmount('20000');
        $s2->setDueDate(new \DateTime('+60 days'));

        $this->fee->addSchedule($s1);
        $this->fee->addSchedule($s2);

        $this->assertSame(50000.0, $this->fee->getSchedulesTotalAmount());
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
