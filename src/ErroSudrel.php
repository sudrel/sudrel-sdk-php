<?php

declare(strict_types=1);

namespace Sudrel;

/**
 * Erro devolvido pela API da Sudrel, tipado pelo `codigo` estável.
 * `requisicaoId` é o que você procura no painel (Integrações › Log da API).
 */
class ErroSudrel extends \RuntimeException
{
    public readonly int $status;
    public readonly string $codigo;
    public readonly ?string $codigoOficial;
    public readonly ?string $campo;
    public readonly ?string $correcao;
    public readonly bool $retentavel;
    public readonly ?string $requisicaoId;
    /** @var array<int, array<string, mixed>> */
    public readonly array $erros;
    /** @var array<string, mixed> O corpo inteiro (por exemplo, `nfse` no 422 de nota rejeitada). */
    public readonly array $corpo;

    /** @param array<string, mixed> $corpo */
    public function __construct(int $status, array $corpo)
    {
        $mensagem = is_string($corpo['mensagem'] ?? null) ? $corpo['mensagem']
            : (is_string($corpo['message'] ?? null) ? $corpo['message'] : "A API respondeu {$status}.");
        parent::__construct($mensagem, $status);
        $this->status = $status;
        $this->codigo = is_string($corpo['codigo'] ?? null) ? $corpo['codigo'] : "http_{$status}";
        $this->codigoOficial = is_string($corpo['codigo_oficial'] ?? null) ? $corpo['codigo_oficial'] : null;
        $this->campo = is_string($corpo['campo'] ?? null) ? $corpo['campo'] : null;
        $this->correcao = is_string($corpo['correcao'] ?? null) ? $corpo['correcao'] : null;
        $this->retentavel = ($corpo['retentavel'] ?? false) === true;
        $this->requisicaoId = is_string($corpo['requisicao_id'] ?? null) ? $corpo['requisicao_id'] : null;
        $this->erros = is_array($corpo['erros'] ?? null) ? $corpo['erros'] : [];
        $this->corpo = $corpo;
    }
}
