<?php

declare(strict_types=1);

namespace Sudrel;

class Lotes
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** Lote em JSON: cada item é o corpo de `POST /v1/nfse` (mais `simular`, no sandbox). @param array<string, mixed> $corpo @return array<string, mixed> */
    public function criar(array $corpo, ?string $idempotencyKey = null): array
    {
        return $this->http->requisitar('POST', '/v1/lotes', ['corpo' => $corpo, 'idempotencyKey' => $idempotencyKey])['corpo'];
    }

    /** Lote pela planilha do painel (.xlsx ou .csv). @param array<string, string> $campos @return array<string, mixed> */
    public function enviarPlanilha(string $caminhoArquivo, array $campos = [], ?string $idempotencyKey = null): array
    {
        $formulario = $campos + ['planilha' => new \CURLFile($caminhoArquivo, 'application/octet-stream', basename($caminhoArquivo))];
        return $this->http->requisitar('POST', '/v1/lotes', ['formulario' => $formulario, 'idempotencyKey' => $idempotencyKey])['corpo'];
    }

    /** @return array<string, mixed> */
    public function obter(string $id): array
    {
        return $this->http->requisitar('GET', '/v1/lotes/' . rawurlencode($id))['corpo'];
    }

    /** @param array<string, scalar> $filtros @return \Generator<int, array<string, mixed>> */
    public function itens(string $id, array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/lotes/' . rawurlencode($id) . '/itens', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'dados');
    }

    /** Lote `aguardar`: emite 'todas' as linhas válidas ou só as da lista. @param 'todas'|array<int, int> $itens @return array<string, mixed> */
    public function emitir(string $id, string|array $itens = 'todas'): array
    {
        return $this->http->requisitar('POST', '/v1/lotes/' . rawurlencode($id) . '/emissao', ['corpo' => ['itens' => $itens]])['corpo'];
    }

    /** @param array<int, array{linha: int, campos?: array<string, mixed>}> $itens @return array<string, mixed> */
    public function reprocessar(string $id, array $itens): array
    {
        return $this->http->requisitar('POST', '/v1/lotes/' . rawurlencode($id) . '/reprocessamento', ['corpo' => ['itens' => $itens]])['corpo'];
    }
}
