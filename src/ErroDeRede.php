<?php

declare(strict_types=1);

namespace Sudrel;

/** A rede caiu e as retentativas acabaram. A mesma `Idempotency-Key` serve pra tentar de novo depois. */
class ErroDeRede extends \RuntimeException
{
    public function __construct(string $detalhe, public readonly ?string $idempotencyKey)
    {
        parent::__construct("Falha de rede falando com a Sudrel: {$detalhe}");
    }
}
