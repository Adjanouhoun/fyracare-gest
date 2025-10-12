<?php
namespace App\Tests\E2E;

use Symfony\Component\Panther\PantherTestCase;

/**
 * @group e2e
 */
final class RdvToPaymentPdfTest extends PantherTestCase
{
    public function testFullRdvToPaymentFlow(): void
    {
        // Si tu veux vraiment le lancer plus tard avec Chrome :
        // $client = static::createPantherClient(['browser' => static::CHROME]);
        // ...
    }
}
