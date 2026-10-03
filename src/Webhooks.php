<?php

declare(strict_types=1);

namespace Sudrel;

class Webhooks
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** O `segredo` vem só nesta resposta. @param array<string, mixed> $corpo @return array<string, mixed> */
    public function criar(array $corpo, ?string $idempotencyKey = null): array
    {
        return $this->http->requisitar('POST', '/v1/webhooks', ['corpo' => $corpo, 'idempotencyKey' => $idempotencyKey])['corpo'];
    }

    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        return $this->http->requisitar('GET', '/v1/webhooks')['corpo']['webhooks'];
    }

    /** @param array<string, mixed> $corpo @return array<string, mixed> */
    public function alterar(string $id, array $corpo): array
    {
        return $this->http->requisitar('PATCH', '/v1/webhooks/' . rawurlencode($id), ['corpo' => $corpo])['corpo'];
    }

    public function remover(string $id): void
    {
        $this->http->requisitar('DELETE', '/v1/webhooks/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function rotacionarSegredo(string $id): array
    {
        return $this->http->requisitar('POST', '/v1/webhooks/' . rawurlencode($id) . '/rotacao-segredo')['corpo'];
    }

    /** @return array<string, mixed> */
    public function testar(string $id): array
    {
        return $this->http->requisitar('POST', '/v1/webhooks/' . rawurlencode($id) . '/teste')['corpo'];
    }

    /** @param array<string, scalar> $filtros @return \Generator<int, array<string, mixed>> */
    public function entregas(string $id, array $filtros = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->http->requisitar('GET', '/v1/webhooks/' . rawurlencode($id) . '/entregas', ['query' => $filtros + ['cursor' => $cursor]])['corpo'], 'entregas');
    }

    /** Um de: ['evento_id' => ...], ['desde' => ..., 'ate' => ...] ou ['lote_id' => ...]. @param array<string, string> $pedido @return array<string, mixed> */
    public function reenviar(string $id, array $pedido): array
    {
        return $this->http->requisitar('POST', '/v1/webhooks/' . rawurlencode($id) . '/reenvio', ['corpo' => $pedido])['corpo'];
    }

    /** Confere o `X-Sudrel-Signature-V2` (tolerância de 5 minutos). */
    public static function verificar(string $corpoBruto, ?string $cabecalhoV2, string $segredo, ?int $agora = null): bool
    {
        return \webhookSudrelValido($corpoBruto, $cabecalhoV2, $segredo, $agora);
    }
}
