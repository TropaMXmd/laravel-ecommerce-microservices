<?php
namespace App\Exceptions;

use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        string $orderUuid,
        string $fromStatus,
        string $toStatus,
        array  $allowedTransitions,
    ) {
        $allowed = implode(', ', $allowedTransitions);

        parent::__construct(
            "Cannot transition order {$orderUuid} from '{$fromStatus}' to '{$toStatus}'. "
            . "Allowed: [{$allowed}]."
        );
    }
}