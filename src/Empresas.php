<?php

declare(strict_types=1);

namespace Sudrel;

class Empresas
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        return $this->http->requisitar('GET', '/v1/companies')['corpo'];
    }

    /** @return array<string, mixed> */
    public function prontidao(string $id): array
    {
        return $this->http->requisitar('GET', '/v1/companies/' . rawurlencode($id) . '/prontidao')['corpo'];
    }
}
