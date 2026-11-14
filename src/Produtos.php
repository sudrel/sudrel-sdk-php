<?php

declare(strict_types=1);

namespace Sudrel;

/** Produtos da NF-e (`/v1/produtos`). A chave de sandbox grava num cadastro só dela. */
class Produtos
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** @param array<string, mixed> $produto @return array<string, mixed> */
    public function criar(array $produto): array
    {
        return $this->http->requisitar('POST', '/v1/produtos', ['corpo' => $produto])['corpo'];
    }

    /** @return array<string, mixed> */
    public function obter(string $codigo, ?string $empresaId = null): array
    {
        return $this->http->requisitar('GET', '/v1/produtos/' . rawurlencode($codigo), ['query' => ['empresa_id' => $empresaId]])['corpo'];
    }

    /** @param array<string, scalar> $filtros @return \Generator<int, array<string, mixed>> */
    public function listar(array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/produtos', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'dados');
    }

    /** Mudar campo fiscal apaga a aprovação do perfil. @param array<string, mixed> $campos @return array<string, mixed> */
    public function alterar(string $codigo, array $campos, ?string $empresaId = null): array
    {
        return $this->http->requisitar('PATCH', '/v1/produtos/' . rawurlencode($codigo), ['query' => ['empresa_id' => $empresaId], 'corpo' => $campos])['corpo'];
    }

    /** @return array<string, mixed> */
    public function aprovarPerfil(string $codigo, string $nome, string $email, ?string $empresaId = null): array
    {
        return $this->http->requisitar('POST', '/v1/produtos/' . rawurlencode($codigo) . '/aprovacao-perfil', [
            'query' => ['empresa_id' => $empresaId],
            'corpo' => ['aprovado_por' => ['nome' => $nome, 'email' => $email]],
        ])['corpo'];
    }

    /** Vínculos com as compras (ST retida na revenda); `quantidade` calcula a prévia `st_retido_previsto`. @return array<string, mixed> */
    public function listarVinculos(string $codigo, ?string $empresaId = null, ?float $quantidade = null): array
    {
        return $this->http->requisitar('GET', '/v1/produtos/' . rawurlencode($codigo) . '/vinculos-entrada', ['query' => ['empresa_id' => $empresaId, 'quantidade' => $quantidade]])['corpo'];
    }

    /** `criterio`: gtin, codigo_fornecedor ou manual. @param array<string, mixed> $vinculo @return array<string, mixed> */
    public function vincularEntrada(string $codigo, array $vinculo, ?string $empresaId = null): array
    {
        return $this->http->requisitar('POST', '/v1/produtos/' . rawurlencode($codigo) . '/vinculos-entrada', ['query' => ['empresa_id' => $empresaId], 'corpo' => $vinculo])['corpo'];
    }

    public function removerVinculo(string $codigo, string $vinculoId, ?string $empresaId = null): void
    {
        $this->http->requisitar('DELETE', '/v1/produtos/' . rawurlencode($codigo) . '/vinculos-entrada/' . rawurlencode($vinculoId), ['query' => ['empresa_id' => $empresaId]]);
    }
}
