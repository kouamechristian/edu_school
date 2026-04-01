<?php

namespace App\Tests\Entity;

use App\Entity\Fee;
use App\Entity\FeeSchedule;
use PHPUnit\Framework\TestCase;

class FeeScheduleTest extends TestCase
{
    private FeeSchedule $schedule;

    protected function setUp(): void
    {
        $this->schedule = new FeeSchedule();
    }

    public function testSettersAndGetters(): void
    {
        $fee = new Fee();

        $this->schedule->setFee($fee);
        $this->schedule->setOrderNumber(1);
        $this->schedule->setAmount('50000');
        $this->schedule->setDueDate(new \DateTime('2026-06-15'));

        $this->assertSame($fee, $this->schedule->getFee());
        $this->assertSame(1, $this->schedule->getOrderNumber());
        $this->assertSame('50000', $this->schedule->getAmount());
        $this->assertSame('2026-06-15', $this->schedule->getDueDate()->format('Y-m-d'));
    }

    public function testIsOverdue(): void
    {
        $this->schedule->setDueDate(new \DateTime('-1 day'));
        $this->assertTrue($this->schedule->isOverdue());

        $this->schedule->setDueDate(new \DateTime('+30 days'));
        $this->assertFalse($this->schedule->isOverdue());
    }

    public function testIsDueSoon(): void
    {
        $this->schedule->setDueDate(new \DateTime('+3 days'));
        $this->assertTrue($this->schedule->isDueSoon(7));

        $this->schedule->setDueDate(new \DateTime('+30 days'));
        $this->assertFalse($this->schedule->isDueSoon(7));
    }

    public function testToString(): void
    {
        $this->schedule->setOrderNumber(2);
        $this->schedule->setAmount('75000');

        $this->assertStringContainsString('#2', (string) $this->schedule);
        $this->assertStringContainsString('75 000', (string) $this->schedule);
    }
}
