<?php
// ============================================
// tests/Integration/CashFlowIntegrationTest.php
namespace App\Tests\Integration;

use App\Entity\CashMovement;
use App\Repository\CashMovementRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CashFlowIntegrationTest extends KernelTestCase
{
    private $entityManager;
    private CashMovementRepository $cashRepo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->cashRepo = $this->entityManager->getRepository(CashMovement::class);
    }

    public function testCashBalance(): void
    {
        // Créer des mouvements IN
        $in1 = new CashMovement();
        $in1->setType(CashMovement::IN)
            ->setAmount(10000)
            ->setSource(CashMovement::SRC_PAYMENT)
            ->setNotes('Test IN 1');

        $in2 = new CashMovement();
        $in2->setType(CashMovement::IN)
            ->setAmount(5000)
            ->setSource(CashMovement::SRC_MANUAL)
            ->setNotes('Test IN 2');

        // Créer des mouvements OUT
        $out1 = new CashMovement();
        $out1->setType(CashMovement::OUT)
             ->setAmount(3000)
             ->setSource(CashMovement::SRC_EXPENSE)
             ->setNotes('Test OUT 1');

        $this->entityManager->persist($in1);
        $this->entityManager->persist($in2);
        $this->entityManager->persist($out1);
        $this->entityManager->flush();

        // Vérifier le solde (10000 + 5000 - 3000 = 12000)
        $totalIn = $this->cashRepo->createQueryBuilder('c')
            ->select('SUM(c.amount)')
            ->where('c.type = :in')
            ->setParameter('in', CashMovement::IN)
            ->getQuery()
            ->getSingleScalarResult();

        $totalOut = $this->cashRepo->createQueryBuilder('c')
            ->select('SUM(c.amount)')
            ->where('c.type = :out')
            ->setParameter('out', CashMovement::OUT)
            ->getQuery()
            ->getSingleScalarResult();

        $balance = ($totalIn ?? 0) - ($totalOut ?? 0);
        
        $this->assertEquals(15000, $totalIn);
        $this->assertEquals(3000, $totalOut);
        $this->assertEquals(12000, $balance);
    }

    public function testSoftDeleteMovement(): void
    {
        $movement = new CashMovement();
        $movement->setType(CashMovement::OUT)
                 ->setAmount(1000)
                 ->setSource(CashMovement::SRC_EXPENSE)
                 ->setNotes('To be deleted');

        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        $id = $movement->getId();
        $this->assertNotNull($id);

        // Soft delete
        $movement->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Vérifier que c'est marqué deleted
        $found = $this->cashRepo->find($id);
        $this->assertTrue($found->isDeleted());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}