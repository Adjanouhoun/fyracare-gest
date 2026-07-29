<?php

namespace App\Service;

use App\Entity\Payment;

final class PaymentShareLinkBuilder
{
    /**
     * @return array{whatsapp: ?string, email: ?string, shortReference: string}
     */
    public function build(Payment $payment): array
    {
        $rdv = $payment->getRdv();
        $client = $rdv?->getClient();
        $paidAt = $payment->getPaidAt();
        $shortReference = sprintf(
            'REC-%s-%06d',
            $paidAt?->format('Y') ?? date('Y'),
            $payment->getId() ?? 0,
        );

        $clientName = trim((string) $client?->getNometprenom()) ?: 'cher client';
        $method = $payment->getMethode() === Payment::M_MOBILE ? 'paiement mobile' : 'espèces';
        $amount = number_format((int) $payment->getAmount(), 0, ',', ' ');
        $message = sprintf(
            "Bonjour %s,\n\nVoici les informations de votre reçu Fyracare :\n"
            ."Référence : %s\nMontant versé : %s MRU\nMode : %s\nDate : %s\n\n"
            ."Merci pour votre confiance.",
            $clientName,
            $shortReference,
            $amount,
            $method,
            $paidAt?->format('d/m/Y à H:i') ?? '—',
        );

        $phone = $this->normalizePhone($client?->getTelephone());
        $email = filter_var($client?->getEmail(), FILTER_VALIDATE_EMAIL) ?: null;

        return [
            'whatsapp' => $phone
                ? 'https://wa.me/'.$phone.'?'.http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986)
                : null,
            'email' => $email
                ? 'mailto:'.$email.'?'.http_build_query([
                    'subject' => 'Votre reçu Fyracare '.$shortReference,
                    'body' => $message,
                ], '', '&', PHP_QUERY_RFC3986)
                : null,
            'shortReference' => $shortReference,
        ];
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 8) {
            $digits = '222'.$digits;
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }
}
