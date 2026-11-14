<?php

declare(strict_types=1);

namespace Sudrel;

/** Caixas de NFC-e (`/v1/caixas`), cada um com a sua série. */
class Caixas
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listar(?string $empresaId = null): array
    {
        return $this->http->requisitar('GET', '/v1/caixas', ['query' => ['empresa_id' => $empresaId]])['corpo']['dados'];
    }

    /** Por id ou identificador. @return array<string, mixed> */
    public function obter(string $idOuIdentificador): array
    {
        return $this->http->requisitar('GET', '/v1/caixas/' . rawurlencode($idOuIdentificador))['corpo'];
    }

    /** @param array{empresa_id: string, identificador: string, serie: int, descricao?: string} $caixa @return array<string, mixed> */
    public function criar(array $caixa): array
    {
        return $this->http->requisitar('POST', '/v1/caixas', ['corpo' => $caixa])['corpo'];
    }

    /** @param array{identificador?: string, descricao?: string, serie?: int, situacao?: string} $mudancas @return array<string, mixed> */
    public function alterar(string $id, array $mudancas): array
    {
        return $this->http->requisitar('PATCH', '/v1/caixas/' . rawurlencode($id), ['corpo' => $mudancas])['corpo'];
    }
}
