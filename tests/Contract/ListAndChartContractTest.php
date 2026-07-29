<?php

namespace App\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class ListAndChartContractTest extends TestCase
{
    public function testBusinessListsShowNewestEntriesFirst(): void
    {
        $cash = file_get_contents(__DIR__.'/../../src/Controller/CashController.php');
        $rdv = file_get_contents(__DIR__.'/../../src/Controller/RdvController.php');
        $users = file_get_contents(__DIR__.'/../../src/Controller/UserController.php');
        $categories = file_get_contents(__DIR__.'/../../src/Controller/ExpenseCategoryController.php');
        $prestations = file_get_contents(__DIR__.'/../../src/Repository/PrestationRepository.php');

        self::assertIsString($cash);
        self::assertIsString($rdv);
        self::assertIsString($users);
        self::assertIsString($categories);
        self::assertIsString($prestations);
        self::assertStringNotContainsString("->orderBy('m.createdAt', 'ASC')", $cash);
        self::assertStringNotContainsString("->orderBy('c.createdAt', 'ASC')", $cash);
        self::assertStringNotContainsString("->orderBy('p.paidAt', 'ASC')", $cash);
        self::assertStringContainsString("\$qb->orderBy('r.startAt', 'DESC');", $rdv);
        self::assertStringContainsString("'users' => \$repo->findBy([], ['id' => 'DESC'])", $users);
        self::assertStringContainsString("'categories' => \$repo->findBy([], ['id' => 'DESC'])", $categories);
        self::assertStringContainsString("->orderBy('p.id', 'DESC')", $prestations);
    }

    public function testInjectionChartsAggregateTheWholePeriod(): void
    {
        $cash = file_get_contents(__DIR__.'/../../src/Controller/CashController.php');

        self::assertIsString($cash);
        self::assertSame(2, substr_count($cash, '$allInjections = (clone $qb)->getQuery()->getResult();'));
        self::assertSame(2, substr_count($cash, 'foreach ($allInjections as $inj)'));
    }

    public function testHistoricalPaymentListsExcludeCancelledPayments(): void
    {
        $cash = file_get_contents(__DIR__.'/../../src/Controller/CashController.php');

        self::assertIsString($cash);
        self::assertGreaterThanOrEqual(2, substr_count($cash, "->andWhere('p.cancelledAt IS NULL')"));
    }

    public function testCashSeparatesExpensesFromPaymentReversals(): void
    {
        $cash = file_get_contents(__DIR__.'/../../src/Controller/CashController.php');
        $template = file_get_contents(__DIR__.'/../../templates/cash/index_v2.html.twig');

        self::assertIsString($cash);
        self::assertIsString($template);
        self::assertStringContainsString('CashMovement::SRC_EXPENSE', $cash);
        self::assertStringContainsString('CashMovement::SRC_PAYMENT_REVERSAL', $cash);
        self::assertStringContainsString("'journal'         => \$journal", $cash);
        self::assertStringContainsString("render('cash/index_v2.html.twig'", $cash);
        self::assertStringContainsString('Journal des mouvements', $template);
        self::assertStringContainsString('totalOutRefunds', $template);
        self::assertStringContainsString('Mobile valide', $template);
    }

    public function testCashJournalAttributesActionsAndProvidesUnifiedReports(): void
    {
        $cash = file_get_contents(__DIR__.'/../../src/Controller/CashController.php');
        $journal = file_get_contents(__DIR__.'/../../templates/cash/index_v2.html.twig');
        $reportController = file_get_contents(__DIR__.'/../../src/Controller/CashReportController.php');
        $reports = file_get_contents(__DIR__.'/../../templates/cash/reports.html.twig');

        self::assertIsString($cash);
        self::assertIsString($journal);
        self::assertIsString($reportController);
        self::assertIsString($reports);
        self::assertStringContainsString('$mv->setCreatedBy($author);', $cash);
        self::assertStringContainsString('$payment->getReceivedBy()?->getFullname()', $cash);
        self::assertStringContainsString("['day', 'month', 'year']", $reportController);
        self::assertStringContainsString("['all', 'entries', 'injections', 'expenses', 'refunds']", $reportController);
        self::assertStringContainsString('Rapport journalier', $journal);
        self::assertStringContainsString('Rapport mensuel', $journal);
        self::assertStringContainsString('Rapport annuel', $journal);
        self::assertStringContainsString('Utilisateur', $reports);
        self::assertStringContainsString("m.category = :category", $reportController);
        self::assertStringContainsString('Catégorie de dépense', $reports);
        self::assertStringContainsString('Toutes les catégories', $reports);
        self::assertStringContainsString("['all', Payment::M_ESPECES, Payment::M_MOBILE]", $reportController);
        self::assertStringContainsString("p.cancelledAt IS NULL", $reportController);
        self::assertStringContainsString('Mode d’encaissement', $reports);
        self::assertStringContainsString('Tous les encaissements', $reports);
        self::assertStringContainsString('Paiements mobiles annulés', $reports);
        self::assertStringContainsString('jamais au solde de la caisse physique', $reports);
        self::assertStringContainsString('Encaissements théoriques', $journal);
        self::assertStringContainsString('Espèces valides', $journal);
        self::assertStringContainsString('Mobile valide', $journal);
        self::assertStringContainsString("'cash': 'Espèces'", $journal);
        self::assertStringContainsString("'mobile': 'Mobile'", $journal);
        self::assertStringContainsString('Impact caisse', $journal);
        self::assertStringContainsString('Encaissement', $journal);
        self::assertStringContainsString('Entrées espèces brutes', $journal);
        self::assertStringContainsString('Espèces nettes de la journée', $journal);
        self::assertStringContainsString('netCashMovements', $cash);

        $depositType = file_get_contents(__DIR__.'/../../src/Form/DepositType.php');
        self::assertIsString($depositType);
        self::assertStringContainsString('Fond de caisse en espèces', $depositType);
        self::assertStringNotContainsString("'Mobile Money'", $depositType);
        self::assertStringNotContainsString("'Banque'", $depositType);
    }
}
