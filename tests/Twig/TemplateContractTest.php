<?php

namespace App\Tests\Twig;

use App\Entity\CompanySettings;
use PHPUnit\Framework\TestCase;

final class TemplateContractTest extends TestCase
{
    public function testAppointmentEditTemplateUsesOnlyItsControllerContext(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/rdv/edit.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("form: form", $template);
        self::assertStringContainsString("rdv.startAt", $template);
        self::assertStringNotContainsString('pagination', $template);
        self::assertStringNotContainsString('today', $template);
    }

    public function testReceiptTemplateOnlyUsesExistingCompanySettingsProperties(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/payment/receipt_pdf.html.twig');

        self::assertIsString($template);
        preg_match_all('/settings\\.([A-Za-z_][A-Za-z0-9_]*)/', $template, $matches);

        foreach (array_unique($matches[1]) as $property) {
            $getter = 'get'.ucfirst($property);
            self::assertTrue(
                method_exists(CompanySettings::class, $getter),
                sprintf('La propriété Twig settings.%s ne possède pas de méthode %s().', $property, $getter),
            );
        }
    }

    public function testAppointmentCreationProvidesSafeClientAutocomplete(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2).'/templates/rdv/_form.html.twig');
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/RdvController.php');

        self::assertIsString($form);
        self::assertIsString($controller);
        self::assertStringContainsString("path('api_autocomplete_clients')", $form);
        self::assertStringContainsString("encodeURIComponent(query)", $form);
        self::assertStringContainsString("option.textContent = item.text", $form);
        self::assertStringContainsString("Sélectionnez un client dans les résultats proposés.", $controller);
        self::assertStringContainsString('$rdv->setClient($selectedClient);', $controller);
    }

    public function testAppointmentListDisplaysWeekdaysInFrench(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/rdv/index.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("1: 'Lundi'", $template);
        self::assertStringContainsString("7: 'Dimanche'", $template);
        self::assertStringContainsString('_self.weekday(rdv.startAt)', $template);
        self::assertStringNotContainsString("rdv.startAt|date('l')", $template);
    }

    public function testProfileMenuUsesThePersonalReadOnlyPage(): void
    {
        $topbar = file_get_contents(dirname(__DIR__, 2).'/templates/partials/topbar.html.twig');
        $profile = file_get_contents(dirname(__DIR__, 2).'/templates/profile/index.html.twig');

        self::assertIsString($topbar);
        self::assertIsString($profile);
        self::assertStringContainsString("path('account_profile')", $topbar);
        self::assertStringNotContainsString("path('app_user_show'", $topbar);
        self::assertStringContainsString('Ces informations sont en lecture seule.', $profile);
        self::assertStringNotContainsString('form_start', $profile);
    }

    public function testDashboardActionsAndStatisticsUseTheOperationalRules(): void
    {
        $dashboard = file_get_contents(dirname(__DIR__, 2).'/templates/dashboard/index.html.twig');
        $stats = file_get_contents(dirname(__DIR__, 2).'/templates/stats/index.html.twig');
        $appointment = file_get_contents(dirname(__DIR__, 2).'/templates/rdv/show.html.twig');

        self::assertIsString($dashboard);
        self::assertIsString($stats);
        self::assertIsString($appointment);
        self::assertStringContainsString("path('app_client_new')", $dashboard);
        self::assertStringContainsString("path('cash_close'", $dashboard);
        self::assertStringContainsString('kpis.honores / totalFinal', $stats);
        self::assertStringContainsString('rendez-vous finalisés', $stats);
        self::assertStringContainsString('Ce choix sera comptabilisé comme une absence.', $appointment);
        self::assertStringContainsString('Ce choix sera comptabilisé comme honoré.', $appointment);
    }

    public function testPaymentAndCashFormsExplainIrreversibleActions(): void
    {
        $payment = file_get_contents(dirname(__DIR__, 2).'/templates/payment/new.html.twig');
        $closure = file_get_contents(dirname(__DIR__, 2).'/templates/cash/close.html.twig');
        $expense = file_get_contents(dirname(__DIR__, 2).'/templates/cash/new_expense.html.twig');
        $cashIndex = file_get_contents(dirname(__DIR__, 2).'/templates/cash/index_v2.html.twig');
        $paymentType = file_get_contents(dirname(__DIR__, 2).'/src/Form/PaymentType.php');

        self::assertIsString($payment);
        self::assertIsString($closure);
        self::assertIsString($expense);
        self::assertIsString($cashIndex);
        self::assertIsString($paymentType);
        self::assertStringNotContainsString('console.log', $payment);
        self::assertStringContainsString('data-amount="{{ rdv.remainingAmount }}"', $payment);
        self::assertStringContainsString('amount > remaining', $payment);
        self::assertStringContainsString('Confirmer l’encaissement', $payment);
        self::assertStringContainsString('toute correction devra être tracée par une annulation comptable', $payment);
        self::assertStringContainsString('hors solde physique', $payment);
        self::assertStringContainsString("'required' => false", $paymentType);
        self::assertStringContainsString('clôture définitive', $closure);
        self::assertStringContainsString('cash-expense-form', $expense);
        self::assertStringContainsString('Enregistrer la dépense', $expense);
        self::assertStringContainsString('data-bs-target="#cashExpenseModal"', $cashIndex);
        self::assertStringContainsString('cash-expense-modal-form', $cashIndex);
        self::assertStringContainsString('Enregistrer une sortie d’argent', $cashIndex);
        self::assertStringContainsString('data-bs-target="#cashDepositModal"', $cashIndex);
        self::assertStringContainsString('cash-deposit-modal-form', $cashIndex);
        self::assertStringContainsString('Enregistrer un apport de fonds', $cashIndex);
    }

    public function testGlobalNavigationIsConsistentAndMobileFriendly(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 2).'/templates/layouts/main.html.twig');
        $sidebar = file_get_contents(dirname(__DIR__, 2).'/templates/partials/sidebar.html.twig');
        $topbar = file_get_contents(dirname(__DIR__, 2).'/templates/partials/topbar.html.twig');

        self::assertIsString($layout);
        self::assertIsString($sidebar);
        self::assertIsString($topbar);
        self::assertStringContainsString('<html lang="fr"', $layout);
        self::assertStringContainsString('{% block title %}', $layout);
        self::assertStringContainsString('.table-responsive > .table', $layout);
        self::assertSame(1, substr_count($sidebar, 'id="navbar-nav"'));
        self::assertStringContainsString('Tableau de bord', $sidebar);
        self::assertStringContainsString('Mon profil', $sidebar);
        self::assertStringContainsString('Bienvenue,', $topbar);
        self::assertStringContainsString('Se déconnecter', $topbar);
        self::assertStringNotContainsString('Profile</span>', $topbar);
    }
}
