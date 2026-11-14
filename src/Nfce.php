<?php

declare(strict_types=1);

namespace Sudrel;

/** NFC-e modelo 65 (`/v1/nfce`). A `Idempotency-Key` é obrigatória na API; sem `idempotencyKey`, o cliente gera uma. */
class Nfce
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /**
     * `POST /v1/nfce`. Devolve a NFC-e em qualquer desfecho da SEFAZ, inclusive
     * rejeitada ou denegada (422 com a nota). Validação, prontidão e referência
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
            return $this->http->requisitar('POST', '/v1/nfce', [
                'corpo' => $corpo,
                'cabecalhos' => $cabecalhos,
                'idempotencyKey' => $opcoes['idempotencyKey'] ?? null,
            ])['corpo'];
        } catch (ErroSudrel $erro) {
            if (is_array($erro->corpo['nfce'] ?? null)) {
                return $erro->corpo['nfce'];
            }
            throw $erro;
        }
    }

    /** @param array<string, mixed> $corpo @return array<string, mixed> */
    public function previa(array $corpo): array
    {
        return $this->http->requisitar('POST', '/v1/nfce/previas', ['corpo' => $corpo])['corpo'];
    }

    /** Por id, `referencia` ou chave de acesso. @return array<string, mixed> */
    public function obter(string $identificador): array
    {
        return $this->http->requisitar('GET', '/v1/nfce/' . rawurlencode($identificador))['corpo'];
    }

    /** @param array<string, scalar> $filtros @return \Generator<int, array<string, mixed>> */
    public function listar(array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/nfce', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'dados');
    }

    public function xml(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfce/' . rawurlencode($id) . '/xml', ['binario' => true])['corpo'];
    }

    /** Cupom de 80 mm em PDF. */
    public function cupom(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfce/' . rawurlencode($id) . '/danfe', ['binario' => true])['corpo'];
    }

    /** O mesmo cupom em 48 colunas, para impressora térmica. */
    public function cupomTexto(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfce/' . rawurlencode($id) . '/danfe', ['query' => ['formato' => 'texto'], 'binario' => true])['corpo'];
    }

    /** @return array<string, mixed> */
    public function descartar(string $id, ?string $motivo = null): array
    {
        return $this->http->requisitar('POST', '/v1/nfce/' . rawurlencode($id) . '/descarte', ['corpo' => $motivo ? ['motivo' => $motivo] : new \stdClass()])['corpo'];
    }

    /** Dentro do prazo da UF (30 minutos na BA). Recusa lança `ErroSudrel`. @return array<string, mixed> */
    public function cancelar(string $id, string $justificativa, ?int $espera = null): array
    {
        return $this->http->requisitar('POST', '/v1/nfce/' . rawurlencode($id) . '/cancelamento', [
            'corpo' => ['justificativa' => $justificativa],
            'cabecalhos' => $espera !== null ? ['Prefer' => "wait={$espera}"] : [],
        ])['corpo'];
    }

    /** Cupom e XML por e-mail ao consumidor (sem `email`, o do consumidor da nota). @return array<string, mixed> */
    public function enviar(string $id, ?string $email = null): array
    {
        return $this->http->requisitar('POST', '/v1/nfce/' . rawurlencode($id) . '/envio', ['corpo' => $email ? ['email' => $email] : new \stdClass()])['corpo'];
    }

    /** @return array<string, mixed> */
    public function prontidao(string $empresaId): array
    {
        return $this->http->requisitar('GET', '/v1/nfce/prontidao/' . rawurlencode($empresaId))['corpo'];
    }

    /** Grava o CSC (só usado no QR Code versão 2). A API nunca devolve o valor. @return array<string, mixed> */
    public function gravarCsc(string $empresaId, string $idCsc, string $csc): array
    {
        return $this->http->requisitar('PUT', '/v1/nfce/csc/' . rawurlencode($empresaId), ['corpo' => ['id_csc' => $idCsc, 'csc' => $csc]])['corpo'];
    }

    /** @return array<int, array<string, mixed>> */
    public function inutilizacoes(?string $empresaId = null): array
    {
        return $this->http->requisitar('GET', '/v1/nfce/inutilizacoes', ['query' => ['empresa_id' => $empresaId]])['corpo']['dados'];
    }
}
