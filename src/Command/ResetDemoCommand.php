<?php

namespace App\Command;

use App\Entity\CashClosure;
use App\Entity\CashMovement;
use App\Entity\Client;
use App\Entity\CompanySettings;
use App\Entity\ExpenseCategory;
use App\Entity\Payment;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\User;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:demo:reset',
    description: 'Réinitialise exclusivement la base SQLite de démonstration avec des données fictives.',
)]
final class ResetDemoCommand extends Command
{
    private const DEMO_DATABASE = 'var/demo/fyracare-demo.sqlite';
    private const DEMO_PASSWORD = 'Demo-2026!';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KernelInterface $kernel,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->kernel->getEnvironment() !== 'demo') {
            $output->writeln('<error>Refus de réinitialiser : APP_ENV doit être strictement égal à demo.</error>');
            return Command::FAILURE;
        }

        $databaseDirectory = dirname($this->kernel->getProjectDir().'/'.self::DEMO_DATABASE);
        if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0770, true) && !is_dir($databaseDirectory)) {
            $output->writeln('<error>Impossible de créer le répertoire privé de la base de démonstration.</error>');
            return Command::FAILURE;
        }

        if (!$this->isSafeDemoConnection()) {
            $output->writeln('<error>Refus de réinitialiser : cette commande exige APP_ENV=demo et la base SQLite dédiée.</error>');
            return Command::FAILURE;
        }

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->loadDemoData();

        $output->writeln('<info>Base de démonstration réinitialisée avec des données exclusivement fictives.</info>');
        $output->writeln('Comptes : reception.caisse@demo.fyracare.test, manager@demo.fyracare.test, admin@demo.fyracare.test');
        $output->writeln('Mot de passe temporaire commun : '.self::DEMO_PASSWORD);

        return Command::SUCCESS;
    }

    private function isSafeDemoConnection(): bool
    {
        $connection = $this->em->getConnection();
        if (!$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return false;
        }

        $expected = $this->normalizePath($this->kernel->getProjectDir().'/'.self::DEMO_DATABASE);
        $configured = $this->normalizePath((string) ($connection->getParams()['path'] ?? ''));

        return $configured === $expected;
    }

    private function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }

    private function loadDemoData(): void
    {
        $reception = $this->createUser(
            'reception.caisse@demo.fyracare.test',
            'Aïcha Démonstration',
            'Réception et caisse',
            ['ROLE_RECEPTION_CAISSE'],
        );
        $manager = $this->createUser(
            'manager@demo.fyracare.test',
            'Moussa Manager',
            'Manager',
            ['ROLE_MANAGER'],
        );
        $this->createUser(
            'admin@demo.fyracare.test',
            'Administrateur Démonstration',
            'Administrateur',
            ['ROLE_ADMIN'],
        );

        $settings = (new CompanySettings())
            ->setPhone('+222 45 00 00 00')
            ->setEmail('contact@demo.fyracare.test')
            ->setAddress('Centre de démonstration — données fictives');
        $this->em->persist($settings);

        $categories = [];
        foreach ([
            ['Fournitures', 'Consommables et petit matériel'],
            ['Entretien', 'Nettoyage et maintenance'],
            ['Transport', 'Déplacements professionnels'],
        ] as [$name, $description]) {
            $category = (new ExpenseCategory())
                ->setName($name)
                ->setDescription($description)
                ->setActive(true);
            $this->em->persist($category);
            $categories[] = $category;
        }

        $prestations = [];
        foreach ([
            ['Consultation découverte', 30, 800],
            ['Soin classique', 60, 1800],
            ['Soin premium', 90, 3200],
            ['Suivi personnalisé', 45, 1200],
        ] as [$label, $duration, $price]) {
            $prestation = (new Prestation())
                ->setLibelle($label)
                ->setDureeMin($duration)
                ->setPrix($price)
                ->setIsActive(true)
                ->setDescription('Prestation fictive destinée à la démonstration.');
            $this->em->persist($prestation);
            $prestations[] = $prestation;
        }

        $clients = [];
        foreach ([
            ['Mariem Exemple', '22000001', 'mariem@example.test'],
            ['Ahmed Démo', '22000002', 'ahmed@example.test'],
            ['Fatimetou Test', '22000003', 'fatimetou@example.test'],
            ['Mohamed Exemple', '22000004', 'mohamed@example.test'],
            ['Aminetou Démo', '22000005', 'aminetou@example.test'],
            ['Cheikh Test', '22000006', 'cheikh@example.test'],
        ] as [$name, $phone, $email]) {
            $client = (new Client())
                ->setNometprenom($name)
                ->setTelephone($phone)
                ->setEmail($email)
                ->setNotes('Fiche fictive — aucune donnée personnelle réelle.')
                ->setCreatedAt(new \DateTime('-30 days'));
            $this->em->persist($client);
            $clients[] = $client;
        }

        $this->em->flush();

        $appointments = [];
        $appointmentDefinitions = [
            [-2, 9, 0, 0, Rdv::S_HONORE],
            [-1, 14, 1, 1, Rdv::S_HONORE],
            [0, 9, 2, 2, Rdv::S_CONFIRME],
            [0, 11, 3, 0, Rdv::S_PLANIFIE],
            [1, 10, 4, 3, Rdv::S_CONFIRME],
            [2, 15, 5, 1, Rdv::S_PLANIFIE],
        ];

        foreach ($appointmentDefinitions as [$dayOffset, $hour, $clientIndex, $prestationIndex, $status]) {
            $start = (new \DateTimeImmutable('today'))
                ->modify(sprintf('%+d days', $dayOffset))
                ->setTime($hour, 0);
            $prestation = $prestations[$prestationIndex];
            $appointment = (new Rdv())
                ->setClient($clients[$clientIndex])
                ->setPrestation($prestation)
                ->setPriceAtBooking((int) $prestation->getPrix())
                ->setStartAt($start)
                ->setEndAt($start->modify('+'.$prestation->getDureeMin().' minutes'))
                ->setStatus($status)
                ->setPaymentStatus(Rdv::PS_NON_PAYE)
                ->setNotes('Rendez-vous fictif de démonstration.');
            $this->em->persist($appointment);
            $appointments[] = $appointment;
        }

        $this->em->flush();

        $this->createPaymentWithMovement($appointments[0], 800, Payment::M_ESPECES, $reception, 'DEMO-0001');
        $this->createPaymentWithMovement($appointments[1], 900, Payment::M_MOBILE, $reception, 'DEMO-0002');

        $expense = (new CashMovement())
            ->setType(CashMovement::OUT)
            ->setAmount(300)
            ->setSource(CashMovement::SRC_EXPENSE)
            ->setNotes('Achat fictif de fournitures')
            ->setCategory($categories[0])
            ->setCreatedBy($reception)
            ->setCreatedAt(new \DateTimeImmutable('today 12:00'));
        $this->em->persist($expense);

        $deposit = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount(2000)
            ->setSource(CashMovement::SRC_MANUAL)
            ->setNotes('Fonds de caisse fictif')
            ->setCreatedBy($manager)
            ->setCreatedAt(new \DateTimeImmutable('today 08:00'));
        $this->em->persist($deposit);

        $closure = (new CashClosure())
            ->setClosedAt(new \DateTimeImmutable('yesterday 18:00'))
            ->setClosureDate(new \DateTimeImmutable('yesterday'))
            ->setTotalIn(1700)
            ->setTotalOut(250)
            ->setBalance(1450)
            ->setCountedBalance(1450)
            ->setVariance(0)
            ->setClosedBy($manager)
            ->setNotes('Clôture fictive de démonstration.');
        $this->em->persist($closure);

        $this->em->flush();
    }

    private function createUser(string $email, string $name, string $function, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFullname($name)
            ->setFonction($function)
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD));
        $this->em->persist($user);

        return $user;
    }

    private function createPaymentWithMovement(
        Rdv $appointment,
        int $amount,
        string $method,
        User $author,
        string $receiptNumber,
    ): void {
        $payment = (new Payment())
            ->setRdv($appointment)
            ->setAmount($amount)
            ->setMethode($method)
            ->setPaidAt(new \DateTimeImmutable())
            ->setReceiptNumber($receiptNumber)
            ->setIsDeposit($amount < $appointment->getTotalAmount())
            ->setNotes('Paiement fictif de démonstration.');
        $appointment->addPayment($payment);
        $appointment->updatePaymentStatus();
        $this->em->persist($payment);

        $movement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount($amount)
            ->setSource(CashMovement::SRC_PAYMENT)
            ->setPayment($payment)
            ->setNotes('Paiement fictif '.$receiptNumber)
            ->setCreatedBy($author);
        $this->em->persist($movement);
    }
}
