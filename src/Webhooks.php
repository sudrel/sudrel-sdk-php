<?php

declare(strict_types=1);

namespace Sudrel\NfseSdk;

final class Webhooks
{
    /**
     * Verifica a assinatura HMAC-SHA256 (cabeçalho `X-Sudrel-Signature`)
     * de uma entrega de webhook, usando `hash_equals` (comparação em tempo
     * constante) pra evitar ataques de timing.
     *
     * @param string $corpoBruto Corpo bruto (raw) exatamente como recebido
     *   — não o array já decodificado, já que reserializar JSON pode mudar
     *   a ordem/espaçamento e invalidar a assinatura.
     * @param string $assinaturaRecebida Valor do cabeçalho `X-Sudrel-Signature`.
     * @param string $segredo Segredo do webhook, obtido na criação (ou
     *   rotação) dele pelo painel — só é exibido uma vez.
     */
    public static function verificarAssinatura(string $corpoBruto, string $assinaturaRecebida, string $segredo): bool
    {
        $esperada = hash_hmac('sha256', $corpoBruto, $segredo);

        return hash_equals($esperada, $assinaturaRecebida);
    }
}
