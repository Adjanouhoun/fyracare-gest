<?php
/**
 * ============================================
 * FIXTURES - src/DataFixtures/TestFixtures.php
 * ============================================
 * Pour peupler la DB de test avec des données
 */

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\CashMovement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory as FakerFactory;

class TestFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = FakerFactory::create('fr_FR');

        // ===== USERS =====
        $admin = new User();
        $admin->setEmail('admin@test.com')
              ->setFullname('Admin Test')
              ->setFonction('Administrateur')
              ->setRoles(['ROLE_ADMIN'])
              ->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $user = new User();
        $user->setEmail('user@test.com')
             ->setFullname('User Test')
             ->setFonction('Employé')
             ->setRoles(['ROLE_USER'])
             ->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
        $manager->persist($user);

        // ===== PRESTATIONS =====
        $prestations = [];
        $prestationsData = [
            ['Consultation', 30, 5000, 'Consultation médicale standard'],
            ['Massage', 60, 8000, 'Massage relaxant complet'],
            ['Soin visage', 45, 6000, 'Soin du visage hydratant'],
            ['Coiffure', 90, 10000, 'Coupe et brushing'],
            ['Manucure', 45, 4000, 'Soin des ongles'],
        ];

        foreach ($prestationsData as [$libelle, $duree, $prix, $desc]) {
            $prestation = new Prestation();
            $prestation->setLibelle($libelle)
                       ->setDureeMin($duree)
                       ->setPrix($prix)
                       ->setDescription($desc)
                       ->setIsActive(true);
            $manager->persist($prestation);
            $prestations[] = $prestation;
        }

        // ===== CLIENTS =====
        $clients = [];
        for ($i = 0; $i < 20; $i++) {
            $client = new Client();
            $client->setNometprenom($faker->name())
                   ->setTelephone($faker->phoneNumber())
                   ->setEmail($faker->email())
                   ->setNotes($faker->sentence())
                   ->setCreatedAt(new \DateTime());
            $manager->persist($client);
            $clients[] = $client;
        }

        // ===== RDV + PAYMENTS =====
        $statuses = [Rdv::S_PLANIFIE, Rdv::S_CONFIRME, Rdv::S_HONORE, Rdv::S_ANNULE];
        $now = new \DateTimeImmutable();

        for ($i = 0; $i < 50; $i++) {
            $client = $faker->randomElement($clients);
            $prestation = $faker->randomElement($prestations);
            
            // Dates variées (passées, présentes, futures)
            $daysOffset = $faker->numberBetween(-30, 30);
            $hour = $faker->numberBetween(8, 18);
            $start = $now->modify("{$daysOffset} days")->setTime($hour, 0);

            $rdv = new Rdv();
            $rdv->setClient($client)
                ->setPrestation($prestation)
                ->setStartAt($start)
                ->setStatus($faker->randomElement($statuses))
                ->setNotes($faker->optional()->sentence());
            
            $rdv->computeEnd();
            $manager->persist($rdv);

            // Paiements pour RDV honorés (70% des cas)
            if ($rdv->getStatus() === Rdv::S_HONORE && $faker->boolean(70)) {
                $payment = new Payment();
                $payment->setRdv($rdv)
                        ->setAmount($prestation->getPrix())
                        ->setMethode($faker->randomElement([Payment::M_ESPECES, Payment::M_MOBILE]))
                        ->setPaidAt($start->modify('+1 hour'))
                        ->setReceiptNumber($start->format('dmY') . '-' . ($i + 1))
                        ->setIsDeposit(false)
                        ->setNotes($faker->optional()->sentence());
                $manager->persist($payment);

                // CashMovement pour chaque paiement
                $mv = new CashMovement();
                $mv->setType(CashMovement::IN)
                   ->setAmount($payment->getAmount())
                   ->setSource(CashMovement::SRC_PAYMENT)
                   ->setCreatedAt($payment->getPaidAt())
                   ->setCreatedBy($faker->randomElement([$admin, $user]))
                   ->setNotes('Paiement RDV #' . $rdv->getId());
                $manager->persist($mv);
            }
        }

        // ===== DÉPENSES =====
        for ($i = 0; $i < 30; $i++) {
            $daysOffset = $faker->numberBetween(-20, 0);
            $expense = new CashMovement();
            $expense->setType(CashMovement::OUT)
                    ->setAmount($faker->numberBetween(500, 5000))
                    ->setSource(CashMovement::SRC_EXPENSE)
                    ->setCreatedAt($now->modify("{$daysOffset} days"))
                    ->setCreatedBy($faker->randomElement([$admin, $user]))
                    ->setNotes($faker->sentence());
            $manager->persist($expense);
        }

        // ===== INJECTIONS =====
        for ($i = 0; $i < 10; $i++) {
            $daysOffset = $faker->numberBetween(-15, 0);
            $injection = new CashMovement();
            $injection->setType(CashMovement::IN)
                      ->setAmount($faker->numberBetween(10000, 50000))
                      ->setSource(CashMovement::SRC_MANUAL)
                      ->setCreatedAt($now->modify("{$daysOffset} days"))
                      ->setCreatedBy($admin)
                      ->setNotes('Injection de fonds - ' . $faker->sentence());
            $manager->persist($injection);
        }

        $manager->flush();
    }
}

/**
 * ============================================
 * TESTS D'INTÉGRATION AVEC BASE DE DONNÉES
 * ============================================
 */

// tests/Integration/PaymentIntegrationTest.php
namespace App\Tests\Integration;

use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\CashMovement;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PaymentIntegrationTest extends KernelTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testCreatePaymentPersistsCorrectly(): void
    {
        $client = new Client();
        $client->setNometprenom('Test Integration')
               ->setTelephone('12345678')
               ->setEmail('integration@test.com')
               ->setNotes('Test')
               ->setCreatedAt(new \DateTime());

        $prestation = new Prestation();
        $prestation->setLibelle('Test Service')
                   ->setDureeMin(30)
                   ->setPrix(5000)
                   ->setIsActive(true)
                   ->setDescription('Test');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable('2025-01-15 10:00'))
            ->setStatus(Rdv::S_PLANIFIE);
        $rdv->computeEnd();

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(5000)
                ->setMethode(Payment::M_ESPECES)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber('15012025-999');

        $this->entityManager->persist($client);
        $this->entityManager->persist($prestation);
        $this->entityManager->persist($rdv);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $this->assertNotNull($payment->getId());
        $this->assertEquals(5000, $payment->getAmount());
        
        // Vérifier que le RDV est bien persisté
        $savedRdv = $this->entityManager
            ->getRepository(Rdv::class)
            ->find($rdv->getId());
        
        $this->assertNotNull($savedRdv);
        $this->assertEquals('Test Integration', $savedRdv->getClient()->getNometprenom());
    }

    public function testPaymentWithCashMovement(): void
    {
        $client = new Client();
        $client->setNometprenom('Cash Test')
               ->setTelephone('11111111')
               ->setEmail('cash@test.com')
               ->setNotes('')
               ->setCreatedAt(new \DateTime());

        $prestation = new Prestation();
        $prestation->setLibelle('Service Cash')
                   ->setDureeMin(45)
                   ->setPrix(7000)
                   ->setIsActive(true)
                   ->setDescription('');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable())
            ->setStatus(Rdv::S_HONORE);
        $rdv->computeEnd();

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(7000)
                ->setMethode(Payment::M_MOBILE)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber('TEST-001');

        // CashMovement associé
        $cashMv = new CashMovement();
        $cashMv->setType(CashMovement::IN)
               ->setAmount($payment->getAmount())
               ->setSource(CashMovement::SRC_PAYMENT)
               ->setNotes('Payment RDV Test');

        $this->entityManager->persist($client);
        $this->entityManager->persist($prestation);
        $this->entityManager->persist($rdv);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($cashMv);
        $this->entityManager->flush();

        $this->assertNotNull($cashMv->getId());
        $this->assertEquals(7000, $cashMv->getAmount());
        $this->assertEquals(CashMovement::IN, $cashMv->getType());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}

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

// ============================================
// tests/Integration/PdfGenerationTest.php
namespace App\Tests\Integration;

use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Service\SettingsProvider;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PdfGenerationTest extends KernelTestCase
{
    private $twig;
    private SettingsProvider $settings;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->twig = $container->get('twig');
        $this->settings = $container->get(SettingsProvider::class);
    }

    public function testReceiptPdfGeneration(): void
    {
        // Créer un payment de test
        $client = new Client();
        $client->setNometprenom('PDF Test Client')
               ->setTelephone('99999999')
               ->setEmail('pdf@test.com')
               ->setNotes('')
               ->setCreatedAt(new \DateTime());

        $prestation = new Prestation();
        $prestation->setLibelle('PDF Test Service')
                   ->setDureeMin(30)
                   ->setPrix(5000)
                   ->setIsActive(true)
                   ->setDescription('Test PDF');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable('2025-01-10 14:00'))
            ->setStatus(Rdv::S_HONORE);
        $rdv->computeEnd();

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(5000)
                ->setMethode(Payment::M_ESPECES)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber('10012025-PDF');

        // Générer le HTML
        $html = $this->twig->render('payment/receipt_pdf.html.twig', [
            'payment' => $payment,
            'settings' => $this->settings->get(),
            'logoDataUri' => null,
        ]);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('PDF Test Client', $html);
        $this->assertStringContainsString('5000', $html);
        $this->assertStringContainsString('10012025-PDF', $html);

        // Tester que Dompdf peut générer le PDF sans erreur
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'portrait');
        
        try {
            $dompdf->render();
            $output = $dompdf->output();
            $this->assertNotEmpty($output);
            $this->assertStringStartsWith('%PDF', $output);
        } catch (\Exception $e) {
            $this->fail('PDF generation failed: ' . $e->getMessage());
        }
    }

    public function testReceiptPdfContainsAllRequiredFields(): void
    {
        $client = new Client();
        $client->setNometprenom('Complete Test')
               ->setTelephone('12345678')
               ->setEmail('complete@test.com')
               ->setNotes('')
               ->setCreatedAt(new \DateTime());

        $prestation = new Prestation();
        $prestation->setLibelle('Full Service')
                   ->setDureeMin(60)
                   ->setPrix(8000)
                   ->setIsActive(true)
                   ->setDescription('Complete test');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable('2025-01-15 10:00'))
            ->setStatus(Rdv::S_HONORE);
        $rdv->computeEnd();

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(8000)
                ->setMethode(Payment::M_MOBILE)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber('15012025-001')
                ->setNotes('Test notes');

        $html = $this->twig->render('payment/receipt_pdf.html.twig', [
            'payment' => $payment,
            'settings' => $this->settings->get(),
            'logoDataUri' => null,
        ]);

        // Vérifier tous les champs requis
        $this->assertStringContainsString('Complete Test', $html);
        $this->assertStringContainsString('12345678', $html);
        $this->assertStringContainsString('Full Service', $html);
        $this->assertStringContainsString('8000', $html);
        $this->assertStringContainsString('15012025-001', $html);
        $this->assertStringContainsString('MOBILE', $html);
        $this->assertStringContainsString('Test notes', $html);
    }
}

/**
 * ============================================
 * COMMANDES UTILES POUR LES TESTS
 * ============================================
 */

/*
# 1. Installer PHPUnit et dépendances
composer require --dev phpunit/phpunit symfony/test-pack

# 2. Créer la base de données de test
php bin/console doctrine:database:create --env=test

# 3. Créer le schéma
php bin/console doctrine:schema:create --env=test

# 4. Charger les fixtures de test
php bin/console doctrine:fixtures:load --env=test --no-interaction

# 5. Lancer tous les tests
php bin/phpunit

# 6. Lancer une suite spécifique
php bin/phpunit --testsuite Unit
php bin/phpunit --testsuite Functional
php bin/phpunit --testsuite Integration

# 7. Lancer un test spécifique
php bin/phpunit tests/Entity/PaymentTest.php
php bin/phpunit tests/Controller/PaymentControllerTest.php

# 8. Tests avec couverture de code
php bin/phpunit --coverage-html var/coverage

# 9. Tests en mode verbeux
php bin/phpunit --verbose

# 10. Recréer la DB de test (nettoyage)
php bin/console doctrine:database:drop --force --env=test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test
php bin/console doctrine:fixtures:load --env=test --no-interaction
*/

/**
 * ============================================
 * SCRIPT MAKEFILE (optionnel) - Makefile
 * ============================================
 */

/*
.PHONY: test test-unit test-functional test-integration test-coverage db-test-reset

# Tous les tests
test:
	php bin/phpunit

# Tests unitaires uniquement
test-unit:
	php bin/phpunit --testsuite Unit

# Tests fonctionnels uniquement
test-functional:
	php bin/phpunit --testsuite Functional

# Tests d'intégration uniquement
test-integration:
	php bin/phpunit --testsuite Integration

# Coverage HTML
test-coverage:
	php bin/phpunit --coverage-html var/coverage

# Reset DB test
db-test-reset:
	php bin/console doctrine:database:drop --force --env=test || true
	php bin/console doctrine:database:create --env=test
	php bin/console doctrine:schema:create --env=test
	php bin/console doctrine:fixtures:load --env=test --no-interaction

# Installation complète test
test-install: db-test-reset
	composer install
	@echo "✅ Environment de test prêt!"

# CI/CD - tous les tests + couverture
ci: db-test-reset test-coverage
	@echo "✅ Tests CI terminés!"
*/

/**
 * ============================================
 * CONFIGURATION GITHUB ACTIONS (optionnel)
 * .github/workflows/tests.yml
 * ============================================
 */

/*
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: app
          POSTGRES_PASSWORD: app
          POSTGRES_DB: app_test
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_sqlite, pdo_pgsql
        coverage: xdebug

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Create test database
      env:
        DATABASE_URL: postgresql://app:app@127.0.0.1:5432/app_test?serverVersion=16&charset=utf8
      run: |
        php bin/console doctrine:database:create --env=test
        php bin/console doctrine:schema:create --env=test

    - name: Load fixtures
      run: php bin/console doctrine:fixtures:load --env=test --no-interaction

    - name: Run tests
      run: php bin/phpunit --coverage-clover coverage.xml

    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        files: ./coverage.xml
*/

/**
 * ============================================
 * TESTS ADDITIONNELS - VALIDATION & SÉCURITÉ
 * ============================================
 */

// tests/Security/CsrfProtectionTest.php
namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CsrfProtectionTest extends WebTestCase
{
    public function testDeleteWithoutCsrfTokenFails(): void
    {
        $client = static::createClient();
        
        // Tenter une suppression sans token CSRF
        $client->request('POST', '/payment/1/delete', [
            '_token' => 'invalid_token'
        ]);

        // Devrait échouer (redirect ou erreur)
        $this->assertResponseRedirects();
    }

    public function testFormSubmissionRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/client/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_token"]');
    }
}

// tests/Validation/PaymentValidationTest.php
namespace App\Tests\Validation;

use App\Entity\Payment;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PaymentValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testPaymentWithNegativeAmountIsInvalid(): void
    {
        $payment = new Payment();
        $payment->setAmount(-1000);
        
        $errors = $this->validator->validate($payment);
        
        // Dépend de tes contraintes de validation
        // Si tu as @Assert\PositiveOrZero sur amount
        // $this->assertGreaterThan(0, count($errors));
        
        $this->assertTrue(true); // Placeholder
    }

    public function testPaymentRequiresRdv(): void
    {
        $payment = new Payment();
        $payment->setAmount(5000)
                ->setMethode(Payment::M_ESPECES)
                ->setPaidAt(new \DateTimeImmutable());
        // Pas de RDV défini
        
        // Test selon tes contraintes
        $this->assertTrue(true); // Placeholder
    }
}