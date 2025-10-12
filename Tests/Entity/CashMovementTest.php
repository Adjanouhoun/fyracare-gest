<?php
namespace App\Tests\Entity;

use App\Entity\CashMovement;
use PHPUnit\Framework\TestCase;

class CashMovementTest extends TestCase
{
    public function testTypes(): void
    {
        $this->assertEquals('IN', CashMovement::IN);
        $this->assertEquals('OUT', CashMovement::OUT);
    }

    public function testSources(): void
    {
        $this->assertEquals('PAYMENT', CashMovement::SRC_PAYMENT);
        $this->assertEquals('MANUAL', CashMovement::SRC_MANUAL);
        $this->assertEquals('CLOSURE', CashMovement::SRC_CLOSURE);
        $this->assertEquals('EXPENSE', CashMovement::SRC_EXPENSE);
    }

    public function testCreatedAtAutoInit(): void
    {
        $movement = new CashMovement();
        $this->assertInstanceOf(\DateTimeImmutable::class, $movement->getCreatedAt());
    }

    public function testSoftDelete(): void
    {
        $movement = new CashMovement();
        $this->assertFalse($movement->isDeleted());
        
        $movement->setDeletedAt(new \DateTimeImmutable());
        $this->assertTrue($movement->isDeleted());
    }
}