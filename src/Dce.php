<?php

declare(strict_types=1);

namespace Sudrel;

/** DC-e, a Declaração de Conteúdo eletrônica (`/v1/dce`), emissão própria. A `Idempotency-Key` é obrigatória na API; sem `idempotencyKey`, o cliente gera uma. */
class Dce
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /**
     * `POST /v1/dce`. Devolve a DC-e em qualquer desfecho da SEFAZ, inclusive
     * rejeitada (422 com a declaração). Validação, prontidão e referência
     * repetida lançam `ErroSudrel`.
     *
     * @param array<string, mixed> $corpo
     * @param array{espera?: int, simular?: string, idempotencyKey?: string} $opcoes
     * @return array<string, mixed>
     */
    public function emitir(array $corpo, array $opcoes = []): array
    {
        $cabecalhos = [];
        if (isset($opcoes['espera'])) {
            $cabecalhos['Prefer'] = "wait={$opcoes['espera']}";
        }
        if (isset($opcoes['simular'])) {
            $cabecalhos['X-Sudrel-Simular'] = $opcoes['simular'];
        }
        try {
            return $this->http->requisitar('POST', '/v1/dce', [
                'corpo' => $corpo,
                'cabecalhos' => $cabecalhos,
                'idempotencyKey' => $opcoes['idempotencyKey'] ?? null,
            ])['corpo'];
        } catch (ErroSudrel $erro) {
            if (is_array($erro->corpo['dce'] ?? null)) {
                return $erro->corpo['dce'];
            }
            throw $erro;
        }
    }

    /** @return array<string, mixed> */
    public function obter(string $identificador): array
    {
        return $this->http->requisitar('GET', '/v1/dce/' . rawurlencode($identificador))['corpo'];
    }

    /** @param array<string, string|int> $filtros @return \Generator<int, array<string, mixed>> */
    public function listar(array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/dce', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'dados');
    }

    public function xml(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/dce/' . rawurlencode($id) . '/xml', ['binario' => true])['corpo'];
    }

    /** DACE em PDF: `a4` (padrão) ou `etiqueta` (10 x 15, para colar na caixa). */
    public function dace(string $id, string $formato = 'a4'): string
    {
        return $this->http->requisitar('GET', '/v1/dce/' . rawurlencode($id) . '/dace', ['query' => ['formato' => $formato], 'binario' => true])['corpo'];
    }

    /** @return array<string, mixed> */
    public function descartar(string $id, ?string $motivo = null): array
    {
        return $this->http->requisitar('POST', '/v1/dce/' . rawurlencode($id) . '/descarte', ['corpo' => $motivo ? ['motivo' => $motivo] : new \stdClass()])['corpo'];
    }

    /**
     * Em até 24 horas da autorização e só com o transporte não iniciado: o cliente manda `transporte_iniciado: false`.
     *
     * @return array<string, mixed>
     */
    public function cancelar(string $id, string $justificativa): array
    {
        return $this->http->requisitar('POST', '/v1/dce/' . rawurlencode($id) . '/cancelamento', [
            'corpo' => ['justificativa' => $justificativa, 'transporte_iniciado' => false],
        ])['corpo'];
    }

    /** Modelo da planilha (uma aba; `basica` com as 8 colunas, ou `completa`). Envie depois em `lotes->enviarPlanilha` com `tipo` = `dce`. */
    public function planilhaModelo(string $layout = 'basica'): string
    {
        return $this->http->requisitar('GET', '/v1/dce/planilha-modelo', ['query' => ['layout' => $layout], 'binario' => true])['corpo'];
    }

    /** @return array<string, mixed> */
    public function prontidao(string $empresaId): array
    {
        return $this->http->requisitar('GET', '/v1/dce/prontidao/' . rawurlencode($empresaId))['corpo'];
    }
}
