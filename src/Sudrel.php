<?php

declare(strict_types=1);

namespace Sudrel;

/**
 * Cliente da API de emissão da Sudrel.
 *
 * ```php
 * $sudrel = new \Sudrel\Sudrel(['chave' => getenv('SUDREL_API_KEY')]);
 * $nota = $sudrel->nfse->emitir(['empresa_id' => $empresa, 'referencia' => 'PED-1', 'tomador' => ['documento' => $doc], 'valor' => '150.00', 'servico' => ['descricao' => 'Consultoria']]);
 * $final = $sudrel->nfse->esperar($nota['id']);
 * ```
 */
class Sudrel
{
    public readonly ClienteHttp $http;
    public readonly Nfse $nfse;
    public readonly Lotes $lotes;
    public readonly Empresas $empresas;
    public readonly Webhooks $webhooks;
    public readonly Eventos $eventos;

    /** @param array{chave?: string, baseUrl?: string, retentativas?: int, esperaBaseMs?: int, transporte?: callable} $opcoes */
    public function __construct(array $opcoes = [])
    {
        $this->http = new ClienteHttp($opcoes);
        $this->nfse = new Nfse($this->http);
        $this->lotes = new Lotes($this->http);
        $this->empresas = new Empresas($this->http);
        $this->webhooks = new Webhooks($this->http);
        $this->eventos = new Eventos($this->http);
    }

    /**
     * Percorre uma lista paginada por cursor, uma página por vez.
     *
     * @param callable(?string): array<string, mixed> $buscar
     * @return \Generator<int, array<string, mixed>>
     */
    public static function paginar(callable $buscar, string $campoItens, bool $fimNaPaginaVazia = false): \Generator
    {
        $cursor = null;
        while (true) {
            $pagina = $buscar($cursor);
            $itens = $pagina[$campoItens] ?? [];
            foreach ($itens as $item) {
                yield $item;
            }
            $proximo = $pagina['proximo_cursor'] ?? null;
            if ($fimNaPaginaVazia ? count($itens) === 0 : !$proximo) {
                return;
            }
            $cursor = $proximo;
        }
    }
}
