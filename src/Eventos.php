<?php

declare(strict_types=1);

namespace Sudrel;

class Eventos
{
    public function __construct(private readonly ClienteHttp $http)
    {
    }

    /** Uma página do log, depois do cursor. Guarde `proximo_cursor`. @param array<int, string> $tipos @return array<string, mixed> */
    public function pagina(?string $apos = null, array $tipos = [], ?int $limite = null): array
    {
        return $this->http->requisitar('GET', '/v1/eventos', ['query' => ['apos' => $apos, 'tipos' => $tipos ? implode(',', $tipos) : null, 'limite' => $limite]])['corpo'];
    }

    /** Todos os eventos depois do cursor, até o fim do log neste momento. @param array<int, string> $tipos @return \Generator<int, array<string, mixed>> */
    public function listar(?string $apos = null, array $tipos = []): \Generator
    {
        return Sudrel::paginar(fn (?string $cursor) => $this->pagina($cursor ?? $apos, $tipos), 'eventos', true);
    }
}
