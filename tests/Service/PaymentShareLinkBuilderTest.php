<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\Rdv;
use App\Service\PaymentShareLinkBuilder;
use PHPUnit\Framework\TestCase;

final class PaymentShareLinkBuilderTest extends TestCase
{
    public function testBuildsAssistedLinksWithoutSendingAnything(): void
    {
        $payment = $this->payment('+222 36 16 96 87', 'client@example.com');
        $links = (new PaymentShareLinkBuilder())->build($payment);

        self::assertStringStartsWith('https://wa.me/22236169687?text=', (string) $links['whatsapp']);
        self::assertStringStartsWith('mailto:client@example.com?', (string) $links['email']);
        self::assertSame('REC-2026-000000', $links['shortReference']);
        self::assertStringContainsString('Montant%20vers%C3%A9%20%3A%202%20000%20MRU', (string) $links['whatsapp']);
    }

    public function testPrefixesMauritanianCountryCodeForEightDigitLocalNumber(): void
    {
        $links = (new PaymentShareLinkBuilder())->build($this->payment('36169687', null));

        self::assertStringStartsWith('https://wa.me/22236169687?', (string) $links['whatsapp']);
        self::assertNull($links['email']);
    }

    public function testInvalidContactDetailsDisableBothChannels(): void
    {
        $links = (new PaymentShareLinkBuilder())->build($this->payment('123', 'incorrect'));

        self::assertNull($links['whatsapp']);
        self::assertNull($links['email']);
    }

    private function payment(?string $phone, ?string $email): Payment
    {
        $client = (new Client())->setNometprenom('Client Exemple');
        if ($phone !== null) {
            $client->setTelephone($phone);
        }
        if ($email !== null) {
            $client->setEmail($email);
        }
        $rdv = (new Rdv())->setClient($client);

        return (new Payment())
            ->setRdv($rdv)
            ->setAmount(2000)
            ->setMethode(Payment::M_ESPECES)
            ->setPaidAt(new \DateTimeImmutable('2026-07-29 18:11:00'))
            ->setReceiptNumber('PAY-TECHNIQUE');
    }
}
