<?php
namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\CashMovement;
use App\Entity\ExpenseCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory as FakerFactory;

class TestFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $faker = FakerFactory::create('fr_FR');

        // ===== USERS =====
        $admin = (new User())
            ->setEmail('admin@test.com')
            ->setFullname('Admin Test')
            ->setFonction('Administrateur')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $user = (new User())
            ->setEmail('user@test.com')
            ->setFullname('User Test')
            ->setFonction('Employé')
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
        $manager->persist($user);

        // ===== CATEGORIES DE DEPENSE =====
        $expenseCategories = []; // <-- tableau distinct et cohérent
        $expenseCategoriesData = [
            ['Salaire','Salaire des employés',true],
            ['Internet','Redevance internet wifi',true],
            ['Loyer du centre','Loyer du centre de cité plage',true],
            ['Autres','Autres type de dépense',true],
        ];
        foreach ($expenseCategoriesData as [$name, $desc, $active]) {
            $cat = (new ExpenseCategory())
                ->setName($name)
                ->setDescription($desc)
                ->setActive($active);
            $manager->persist($cat);
            $expenseCategories[] = $cat; // <-- on empile bien dans le tableau
        }

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
            $prestation = (new Prestation())
                ->setLibelle($libelle)
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
            $client = (new Client())
                ->setNometprenom($faker->name())
                ->setTelephone($faker->numerify('22########'))
                ->setEmail($faker->unique()->safeEmail())
                ->setNotes($faker->sentence())
                ->setCreatedAt(new \DateTime());
            $manager->persist($client);
            $clients[] = $client;
        }

        // ===== RDV + PAYMENTS =====
        $statuses = [Rdv::S_PLANIFIE, Rdv::S_CONFIRME, Rdv::S_HONORE, Rdv::S_ANNULE];
        $now = new \DateTimeImmutable();

        for ($i = 0; $i < 50; $i++) {
            $client     = $faker->randomElement($clients);
            $prestation = $faker->randomElement($prestations);

            $daysOffset = $faker->numberBetween(-30, 30);
            $hour       = $faker->numberBetween(8, 18);
            $start      = $now->modify("{$daysOffset} days")->setTime($hour, 0);

            $rdv = (new Rdv())
                ->setClient($client)
                ->setPrestation($prestation)
                ->setStartAt($start)
                ->setStatus($faker->randomElement($statuses))
                ->setNotes($faker->sentence());
            // calcule la fin selon ta logique d'entité
            if (method_exists($rdv, 'computeEnd')) {
                $rdv->computeEnd();
            } else {
                $rdv->setEndAt($start->modify('+'.$prestation->getDureeMin().' minutes'));
            }
            $manager->persist($rdv);

            // Paiements pour RDV honorés (70%)
            if ($rdv->getStatus() === Rdv::S_HONORE && $faker->boolean(70)) {
                $payment = (new Payment())
                    ->setRdv($rdv)
                    ->setAmount($prestation->getPrix())
                    ->setMethode($faker->randomElement([Payment::M_ESPECES, Payment::M_MOBILE]))
                    ->setPaidAt($start->modify('+1 hour'))
                    ->setReceiptNumber($start->format('dmY') . '-' . ($i + 1))
                    ->setIsDeposit(false)
                    ->setNotes($faker->sentence());
                $manager->persist($payment);

                // Mouvement de caisse lié au paiement
                $mv = (new CashMovement())
                    ->setType(CashMovement::IN)
                    ->setAmount($payment->getAmount())
                    ->setSource(CashMovement::SRC_PAYMENT)
                    ->setCreatedAt($payment->getPaidAt())
                    ->setNotes('Paiement RDV (sera référencé après flush)');
                // si tu as bien ajouté une relation createdBy(User) :
                if (method_exists($mv, 'setCreatedBy')) {
                    $mv->setCreatedBy($faker->randomElement([$admin, $user]));
                }
                $manager->persist($mv);
            }
        }

        // ===== DÉPENSES =====
        for ($i = 0; $i < 30; $i++) {
            $category   = $faker->randomElement($expenseCategories); // <-- on pioche dans le tableau
            $daysOffset = $faker->numberBetween(-20, 0);

            $expense = (new CashMovement())
                ->setType(CashMovement::OUT)
                ->setAmount($faker->numberBetween(500, 5000))
                ->setSource(CashMovement::SRC_EXPENSE)
                ->setCreatedAt($now->modify("{$daysOffset} days"))
                ->setNotes($faker->sentence())
                ->setCategory($category);

            if (method_exists($expense, 'setCreatedBy')) {
                $expense->setCreatedBy($faker->randomElement([$admin, $user]));
            }
            $manager->persist($expense);
        }

        // ===== INJECTIONS (fonds manuels) =====
        for ($i = 0; $i < 10; $i++) {
            $daysOffset = $faker->numberBetween(-15, 0);
            $injection = (new CashMovement())
                ->setType(CashMovement::IN)
                ->setAmount($faker->numberBetween(10000, 50000))
                ->setSource(CashMovement::SRC_MANUAL)
                ->setCreatedAt($now->modify("{$daysOffset} days"))
                ->setNotes('Injection de fonds - ' . $faker->sentence());
            if (method_exists($injection, 'setCreatedBy')) {
                $injection->setCreatedBy($admin);
            }
            $manager->persist($injection);
        }

        $manager->flush();
    }
}
