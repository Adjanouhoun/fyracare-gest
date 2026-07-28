<?php

namespace App\Tests\Service;

use App\Entity\CashMovement;
use App\Entity\User;
use App\Service\ActivityLogReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActivityLogReaderTest extends KernelTestCase
{
    public function testItIncludesCancelledMovementsAndRestoresTheActiveFilter(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $admin = (new User())
            ->setEmail('audit-admin@example.test')
            ->setFullname('Administrateur audit')
            ->setFonction('Administrateur')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('test');
        $movement = (new CashMovement())
            ->setType(CashMovement::OUT)
            ->setSource(CashMovement::SRC_EXPENSE)
            ->setAmount(500)
            ->setCreatedBy($admin)
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($admin)
            ->setDeletionReason('Erreur de saisie');
        $em->persist($admin);
        $em->persist($movement);
        $em->flush();

        $events = $container->get(ActivityLogReader::class)->recent();

        self::assertTrue($em->getFilters()->isEnabled('active_cash_movements'));
        self::assertCount(2, $events);
        self::assertSame(
            ['Annulation d’un mouvement de caisse', 'Dépense de caisse'],
            array_column($events, 'title'),
        );
        self::assertSame('Administrateur audit', $events[0]['actor']);
    }
}
