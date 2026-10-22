<?php

namespace App\Tests\Service;

use App\Entity\Fee;
use App\Entity\Registration;
use App\Entity\StudentFee;
use App\Service\RegistrationDiscountService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RegistrationDiscountServiceTest extends TestCase
{
    private RegistrationDiscountService $service;

    protected function setUp(): void
    {
        $this->service = new RegistrationDiscountService(new NullLogger());
    }

    public function testPercentageIsSpreadProportionallyOnTotal(): void
    {
        [$reg, $lines] = $this->registration([50000, 100000, 25000]);

        $applied = $this->service->grant($reg, Registration::DISCOUNT_PERCENT, 10, 'Fratrie', null);

        $this->assertSame(17500.0, $applied);
        $this->assertSame(17500.0, $reg->getTotalDiscount());
        $this->assertSame(157500.0, $reg->getTotalTuition());
        $this->assertSame(['5000.00', '10000.00', '2500.00'], array_map(fn (StudentFee $l) => $l->getDiscountAmount(), $lines));
        $this->assertSame('10 %', $reg->getDiscountLabel());
        $this->assertSame('Fratrie', $reg->getDiscountReason());
    }

    public function testRoundingRemainderIsFullyDistributed(): void
    {
        [$reg] = $this->registration([33333, 33333, 33334]);

        $this->service->grant($reg, Registration::DISCOUNT_AMOUNT, 1000, 'Geste commercial', null);

        $this->assertSame(1000.0, $reg->getTotalDiscount());
        $this->assertSame(99000.0, $reg->getTotalTuition());
    }

    public function testPaidLineCannotGoBelowWhatWasPaid(): void
    {
        [$reg, $lines] = $this->registration([50000, 100000]);
        $lines[0]->setPaidAmount('50000.00'); // première ligne entièrement réglée

        $this->service->grant($reg, Registration::DISCOUNT_AMOUNT, 30000, 'Enfant du personnel', null);

        $this->assertSame('0.00', $lines[0]->getDiscountAmount());
        $this->assertSame('30000.00', $lines[1]->getDiscountAmount());
        $this->assertSame('paye', $lines[0]->getStatus());
        $this->assertSame(70000.0, $lines[1]->getRemainingAmount());
    }

    public function testDiscountAbovePayableRemainderIsRefusedWithoutSideEffect(): void
    {
        [$reg, $lines] = $this->registration([50000, 100000]);
        $lines[1]->setPaidAmount('90000.00');

        try {
            $this->service->grant($reg, Registration::DISCOUNT_AMOUNT, 70000, 'Trop', null);
            $this->fail('Une remise supérieure au reste à payer doit être refusée.');
        } catch (\DomainException) {
        }

        $this->assertFalse($reg->hasDiscount());
        $this->assertSame(0.0, $reg->getTotalDiscount());
        $this->assertSame(150000.0, $reg->getTotalTuition());
    }

    public function testInvalidInputsAreRefused(): void
    {
        [$reg] = $this->registration([50000]);

        foreach ([
            [Registration::DISCOUNT_PERCENT, 120, 'Motif'],
            [Registration::DISCOUNT_PERCENT, 0, 'Motif'],
            [Registration::DISCOUNT_AMOUNT, 60000, 'Motif'],
            [Registration::DISCOUNT_AMOUNT, 1000, '   '],
            ['autre', 10, 'Motif'],
        ] as [$type, $value, $reason]) {
            try {
                $this->service->grant($reg, $type, $value, $reason, null);
                $this->fail(sprintf('Remise %s %s acceptée à tort.', $value, $type));
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFullExemptionMarksLinesAsSettled(): void
    {
        [$reg, $lines] = $this->registration([50000, 25000]);

        $this->service->grant($reg, Registration::DISCOUNT_PERCENT, 100, 'Bourse complète', null);

        $this->assertSame(0.0, $reg->getTotalTuition());
        $this->assertSame(0.0, $reg->getRemainingTuition());
        foreach ($lines as $line) {
            $this->assertSame('paye', $line->getStatus());
        }
    }

    public function testRevokeRestoresGrossAmounts(): void
    {
        [$reg, $lines] = $this->registration([50000, 100000]);
        $this->service->grant($reg, Registration::DISCOUNT_PERCENT, 20, 'Fratrie', null);

        $this->service->revoke($reg);

        $this->assertFalse($reg->hasDiscount());
        $this->assertSame(150000.0, $reg->getTotalTuition());
        $this->assertSame(['50000.00', '100000.00'], array_map(fn (StudentFee $l) => $l->getAmount(), $lines));
    }

    public function testReapplyRecomputesPercentageAfterNewFee(): void
    {
        [$reg] = $this->registration([100000]);
        $this->service->grant($reg, Registration::DISCOUNT_PERCENT, 10, 'Fratrie', null);

        $reg->addStudentFee($this->line(50000));
        $this->service->reapply($reg);

        $this->assertSame(15000.0, $reg->getTotalDiscount());
        $this->assertSame(135000.0, $reg->getTotalTuition());
    }

    public function testArrearsAreNotDiscounted(): void
    {
        [$reg, $lines] = $this->registration([100000]);
        $arrear = $this->line(40000)->setIsArriereAnterieur(true);
        $reg->addStudentFee($arrear);

        $this->service->grant($reg, Registration::DISCOUNT_PERCENT, 50, 'Fratrie', null);

        $this->assertSame('50000.00', $lines[0]->getDiscountAmount());
        $this->assertSame('0.00', $arrear->getDiscountAmount());
        $this->assertSame('40000.00', $arrear->getAmount());
    }

    /**
     * @param int[] $amounts
     *
     * @return array{0: Registration, 1: StudentFee[]}
     */
    private function registration(array $amounts): array
    {
        $reg = new Registration();
        $lines = [];
        foreach ($amounts as $amount) {
            $lines[] = $line = $this->line($amount);
            $reg->addStudentFee($line);
        }

        return [$reg, $lines];
    }

    private function line(int $amount): StudentFee
    {
        $fee = (new Fee())->setName('Frais ' . $amount)->setAmount((string) $amount);

        return (new StudentFee())->setFee($fee)->setAmount(number_format($amount, 2, '.', ''));
    }
}
