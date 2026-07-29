<?php

namespace App\Doctrine;

use App\Entity\CashMovement;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class CashMovementActiveFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getName() !== CashMovement::class) {
            return '';
        }

        return sprintf(
            "%1\$s.deleted_at IS NULL AND (
                %1\$s.source NOT IN ('PAYMENT', 'PAYMENT_REVERSAL')
                OR EXISTS (
                    SELECT 1 FROM payment cash_payment
                    WHERE cash_payment.id = %1\$s.payment_id
                    AND cash_payment.methode = 'ESPECES'
                )
                OR (
                    %1\$s.payment_id IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM payment legacy_mobile_payment
                        WHERE %1\$s.source = 'PAYMENT'
                        AND legacy_mobile_payment.methode = 'MOBILE'
                        AND legacy_mobile_payment.amount = %1\$s.amount
                        AND legacy_mobile_payment.paid_at = %1\$s.created_at
                    )
                )
            )",
            $targetTableAlias,
        );
    }
}
