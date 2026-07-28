<?php

namespace App\Tests\Service;

use App\Entity\AppointmentStatusHistory;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\User;
use App\Service\AppointmentStatusManager;
use App\Repository\AppointmentStatusHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AppointmentStatusManagerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AppointmentStatusManager $manager;
    private Rdv $rdv;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->manager = static::getContainer()->get(AppointmentStatusManager::class);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->user = (new User())->setEmail('reception@example.test')->setFullname('Réception')
            ->setFonction('Réception')->setRoles(['ROLE_RECEPTION_CAISSE'])->setPassword('test');
        $client = (new Client())->setNometprenom('Client')->setTelephone('22000002')
            ->setEmail('status@example.test')->setNotes('Test')->setCreatedAt(new \DateTime());
        $prestation = (new Prestation())->setLibelle('Soin')->setDureeMin(60)->setPrix(1000)
            ->setIsActive(true)->setDescription('Test');
        $start = new \DateTimeImmutable('yesterday 10:00');
        $this->rdv = (new Rdv())->setClient($client)->setPrestation($prestation)
            ->setStartAt($start)->setEndAt($start->modify('+60 minutes'))
            ->setStatus(Rdv::S_PLANIFIE)->setPaymentStatus(Rdv::PS_NON_PAYE);

        foreach ([$this->user, $client, $prestation, $this->rdv] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testValidTransitionsAreRecorded(): void
    {
        $this->manager->transition($this->rdv, Rdv::S_CONFIRME, $this->user);
        $this->manager->transition($this->rdv, Rdv::S_HONORE, $this->user, 'Client reçu');

        self::assertSame(Rdv::S_HONORE, $this->rdv->getStatus());
        $history = $this->em->getRepository(AppointmentStatusHistory::class)->findBy(
            ['rdv' => $this->rdv],
            ['id' => 'ASC'],
        );
        self::assertCount(2, $history);
        self::assertSame(Rdv::S_PLANIFIE, $history[0]->getOldStatus());
        self::assertSame(Rdv::S_CONFIRME, $history[0]->getNewStatus());
        self::assertSame('Client reçu', $history[1]->getNote());
        self::assertSame($this->user, $history[1]->getChangedBy());

        $today = new \DateTimeImmutable('today');
        $counts = static::getContainer()
            ->get(AppointmentStatusHistoryRepository::class)
            ->countNewStatusesBetween($today, $today->setTime(23, 59, 59));
        self::assertSame(1, $counts[Rdv::S_CONFIRME]);
        self::assertSame(1, $counts[Rdv::S_HONORE]);
    }

    public function testTerminalStatusCannotBeChanged(): void
    {
        $this->manager->transition($this->rdv, Rdv::S_ABSENT, $this->user);

        $this->expectException(\DomainException::class);
        $this->manager->transition($this->rdv, Rdv::S_HONORE, $this->user);
    }
}
