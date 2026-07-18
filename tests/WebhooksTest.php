<?php

declare(strict_types=1);

namespace IntegroBR\NfseSdk\Tests;

use IntegroBR\NfseSdk\Webhooks;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private string $segredo = 'segredo-do-webhook';
    private string $corpo;

    protected function setUp(): void
    {
        $this->corpo = json_encode(['tipo' => 'NOTA_RECEBIDA', 'dados' => ['id' => '1']], JSON_THROW_ON_ERROR);
    }

    public function testAceitaAssinaturaValida(): void
    {
        $assinatura = hash_hmac('sha256', $this->corpo, $this->segredo);
        self::assertTrue(Webhooks::verificarAssinatura($this->corpo, $assinatura, $this->segredo));
    }

    public function testRejeitaAssinaturaDeOutroSegredo(): void
    {
        $assinatura = hash_hmac('sha256', $this->corpo, 'segredo-errado');
        self::assertFalse(Webhooks::verificarAssinatura($this->corpo, $assinatura, $this->segredo));
    }

    public function testRejeitaAssinaturaDeCorpoAdulterado(): void
    {
        $assinatura = hash_hmac('sha256', $this->corpo, $this->segredo);
        $corpoAdulterado = json_encode(['tipo' => 'NOTA_RECEBIDA', 'dados' => ['id' => '2']], JSON_THROW_ON_ERROR);
        self::assertFalse(Webhooks::verificarAssinatura($corpoAdulterado, $assinatura, $this->segredo));
    }

    public function testRejeitaAssinaturaComTamanhoDiferenteSemLancarErro(): void
    {
        self::assertFalse(Webhooks::verificarAssinatura($this->corpo, 'abc123', $this->segredo));
    }
}
