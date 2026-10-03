<?php

declare(strict_types=1);

namespace Sudrel;

class Nfse
{
    private const FINAIS = ['autorizada', 'rejeitada', 'cancelada', 'falha_transmissao', 'descartada', 'aguardando_confirmacao'];

    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /**
     * `POST /v1/nfse`. Devolve a nota em qualquer desfecho da Sefin, inclusive
     * rejeitada (422 com a nota). Validação, prontidão e referência repetida
     * lançam `ErroSudrel`.
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
            return $this->http->requisitar('POST', '/v1/nfse', [
                'corpo' => $corpo,
                'cabecalhos' => $cabecalhos,
                'idempotencyKey' => $opcoes['idempotencyKey'] ?? null,
            ])['corpo'];
        } catch (ErroSudrel $erro) {
            if (is_array($erro->corpo['nfse'] ?? null)) {
                return $erro->corpo['nfse'];
            }
            throw $erro;
        }
    }

    /** @param array<string, mixed> $corpo @return array<string, mixed> */
    public function validar(array $corpo): array
    {
        return $this->http->requisitar('POST', '/v1/nfse/validacoes', ['corpo' => $corpo])['corpo'];
    }

    /** Por id, `referencia` ou chave de acesso. @return array<string, mixed> */
    public function obter(string $identificador): array
    {
        return $this->http->requisitar('GET', '/v1/nfse/' . rawurlencode($identificador))['corpo'];
    }

    /** @param array<string, scalar> $filtros @return \Generator<int, array<string, mixed>> */
    public function listar(array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/nfse', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'dados');
    }

    /** @return array<string, mixed> */
    public function cancelar(string $id, string $motivo, string $justificativa): array
    {
        return $this->http->requisitar('POST', '/v1/nfse/' . rawurlencode($id) . '/cancelamento', ['corpo' => ['motivo' => $motivo, 'justificativa' => $justificativa]])['corpo'];
    }

    /** Trava de competência: confirma (e, se quiser, troca a competência AAAA-MM). @return array<string, mixed> */
    public function confirmar(string $id, ?string $competencia = null): array
    {
        return $this->http->requisitar('POST', '/v1/nfse/' . rawurlencode($id) . '/confirmacao', ['corpo' => $competencia ? ['competencia' => $competencia] : new \stdClass()])['corpo'];
    }

    public function pdf(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfse/' . rawurlencode($id) . '/pdf', ['binario' => true])['corpo'];
    }

    public function xml(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfse/' . rawurlencode($id) . '/xml', ['binario' => true])['corpo'];
    }

    /**
     * Acompanha a nota até sair de `transmitindo`, sem polling agressivo
     * (1 s, 2 s, 4 s... até 30 s entre consultas), até o prazo.
     *
     * @return array<string, mixed>
     */
    public function esperar(string $id, int $prazoSegundos = 600, int $intervaloInicialMs = 1000): array
    {
        $limite = microtime(true) + $prazoSegundos;
        $intervalo = $intervaloInicialMs;
        while (true) {
            $nota = $this->obter($id);
            if (in_array($nota['status'] ?? '', self::FINAIS, true) || microtime(true) + $intervalo / 1000 > $limite) {
                return $nota;
            }
            usleep($intervalo * 1000);
            $intervalo = min($intervalo * 2, 30000);
        }
    }
}
