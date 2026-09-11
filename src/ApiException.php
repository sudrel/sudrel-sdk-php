<?php

declare(strict_types=1);

namespace Sudrel\NfseSdk;

/**
 * Erro devolvido pela API pública da Sudrel — sempre no formato
 * `{ statusCode, message, error }`, onde `message` pode ser uma string
 * única ou uma lista (um item por campo inválido, em erros 400).
 */
final class ApiException extends \RuntimeException
{
    /** @var string[] */
    private readonly array $mensagens;

    /**
     * @param string|string[] $mensagem
     */
    public function __construct(
        private readonly int $statusCode,
        string|array $mensagem,
        private readonly ?string $erroTipo = null,
    ) {
        $this->mensagens = is_array($mensagem) ? $mensagem : [$mensagem];
        parent::__construct(implode(' ', $this->mensagens), $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return string[] */
    public function getMensagens(): array
    {
        return $this->mensagens;
    }

    public function getErroTipo(): ?string
    {
        return $this->erroTipo;
    }

    /** 429 — limite de 120 requisições/minuto por chave excedido. */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /** 401 — chave ausente, inválida ou revogada. */
    public function isNaoAutenticado(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * 404 — cobre tanto "não existe" quanto "existe, mas no outro
     * ambiente (sandbox/produção)" da mesma conta.
     */
    public function isNaoEncontrado(): bool
    {
        return $this->statusCode === 404;
    }
}
