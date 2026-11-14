<?php

declare(strict_types=1);

namespace Sudrel;

/** Estado da SEFAZ de cada UF para a NF-e (`/v1/sefaz/status`), no ambiente da chave. */
class Sefaz
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** @return array<string, mixed> */
    public function status(?string $uf = null): array
    {
        return $this->http->requisitar('GET', '/v1/sefaz/status', ['query' => ['uf' => $uf]])['corpo'];
    }
}
