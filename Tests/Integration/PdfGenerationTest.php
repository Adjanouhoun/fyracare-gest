<?php
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