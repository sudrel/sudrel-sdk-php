<?php

declare(strict_types=1);

namespace Sudrel;

/** NF-e modelo 55, modo informado (`/v1/nfe`). */
class Nfe
{
    private const FINAIS = ['autorizada', 'rejeitada', 'denegada', 'cancelada', 'descartada'];

    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /**
     * `POST /v1/nfe`. Devolve a NF-e em qualquer desfecho da SEFAZ, inclusive
     * rejeitada ou denegada (422 com a nota). Validação, prontidão e referência
     * repetida lançam `ErroSudrel`. Sem `idempotencyKey`, o cliente gera uma e
     * a reaproveita nas retentativas.
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
            return $this->http->requisitar('POST', '/v1/nfe', [
                'corpo' => $corpo,
                'cabecalhos' => $cabecalhos,
                'idempotencyKey' => $opcoes['idempotencyKey'] ?? null,
            ])['corpo'];
        } catch (ErroSudrel $erro) {
            if (is_array($erro->corpo['nfe'] ?? null)) {
                return $erro->corpo['nfe'];
            }
            throw $erro;
        }
    }

    /** Valida e devolve o XML que seria enviado, sem reservar número. @param array<string, mixed> $corpo @return array<string, mixed> */
    public function previa(array $corpo): array
    {
        return $this->http->requisitar('POST', '/v1/nfe/previas', ['corpo' => $corpo])['corpo'];
    }

    /** Por id, `referencia` ou chave de acesso. @return array<string, mixed> */
    public function obter(string $identificador): array
    {
        return $this->http->requisitar('GET', '/v1/nfe/' . rawurlencode($identificador))['corpo'];
    }

    /** @param array<string, scalar> $filtros @return \Generator<int, array<string, mixed>> */
    public function listar(array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/nfe', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'dados');
    }

    public function xml(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfe/' . rawurlencode($id) . '/xml', ['binario' => true])['corpo'];
    }

    public function danfe(string $id): string
    {
        return $this->http->requisitar('GET', '/v1/nfe/' . rawurlencode($id) . '/danfe', ['binario' => true])['corpo'];
    }

    /**
     * Rejeitada: refaz com o mesmo número e reenvia. Devolve a nota também
     * quando a SEFAZ rejeita de novo.
     *
     * @param array<string, mixed> $corpo
     * @return array<string, mixed>
     */
    public function corrigir(string $id, array $corpo, ?int $espera = null): array
    {
        try {
            return $this->http->requisitar('POST', '/v1/nfe/' . rawurlencode($id) . '/correcao', [
                'corpo' => $corpo,
                'cabecalhos' => $espera !== null ? ['Prefer' => "wait={$espera}"] : [],
            ])['corpo'];
        } catch (ErroSudrel $erro) {
            if (is_array($erro->corpo['nfe'] ?? null)) {
                return $erro->corpo['nfe'];
            }
            throw $erro;
        }
    }

    /** @return array<string, mixed> */
    public function descartar(string $id, ?string $motivo = null): array
    {
        return $this->http->requisitar('POST', '/v1/nfe/' . rawurlencode($id) . '/descarte', ['corpo' => $motivo ? ['motivo' => $motivo] : new \stdClass()])['corpo'];
    }

    /** Até 24 horas depois da autorização. Recusa da SEFAZ lança `ErroSudrel` (`cancelamento_recusado`). @return array<string, mixed> */
    public function cancelar(string $id, string $justificativa): array
    {
        return $this->http->requisitar('POST', '/v1/nfe/' . rawurlencode($id) . '/cancelamento', ['corpo' => ['justificativa' => $justificativa]])['corpo'];
    }

    /** Carta de correção (até 20 por nota). A resposta traz `aviso` com o que a carta não pode corrigir. @return array<string, mixed> */
    public function registrarCartaCorrecao(string $id, string $correcao): array
    {
        return $this->http->requisitar('POST', '/v1/nfe/' . rawurlencode($id) . '/cartas-correcao', ['corpo' => ['correcao' => $correcao]])['corpo'];
    }

    /** @return array<string, mixed> */
    public function prontidao(string $empresaId): array
    {
        return $this->http->requisitar('GET', '/v1/nfe/prontidao/' . rawurlencode($empresaId))['corpo'];
    }

    /** Perfil fiscal da empresa para o modo calcular. @return array<string, mixed> */
    public function perfilFiscal(string $empresaId): array
    {
        return $this->http->requisitar('GET', '/v1/nfe/perfil-fiscal/' . rawurlencode($empresaId))['corpo'];
    }

    /** @param array<string, mixed> $perfil @return array<string, mixed> */
    public function gravarPerfilFiscal(string $empresaId, array $perfil): array
    {
        return $this->http->requisitar('PUT', '/v1/nfe/perfil-fiscal/' . rawurlencode($empresaId), ['corpo' => $perfil])['corpo'];
    }

    /** @return array<string, mixed> */
    public function aprovarPerfilFiscal(string $empresaId, string $nome, string $email): array
    {
        return $this->http->requisitar('POST', '/v1/nfe/perfil-fiscal/' . rawurlencode($empresaId) . '/aprovacao', ['corpo' => ['aprovado_por' => ['nome' => $nome, 'email' => $email]]])['corpo'];
    }

    /** @return array<int, array<string, mixed>> */
    public function inutilizacoes(?string $empresaId = null): array
    {
        return $this->http->requisitar('GET', '/v1/nfe/inutilizacoes', ['query' => ['empresa_id' => $empresaId]])['corpo']['dados'];
    }

    /**
     * Acompanha a NF-e até um desfecho (1 s, 2 s, 4 s... até 30 s entre
     * consultas), até o prazo.
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
