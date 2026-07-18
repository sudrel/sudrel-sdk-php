<?php

declare(strict_types=1);

namespace IntegroBR\NfseSdk\Tests;

use IntegroBR\NfseSdk\ApiException;
use IntegroBR\NfseSdk\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testExigeApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('');
    }

    public function testObterContaFazGetComBearerCorreto(): void
    {
        $chamadas = [];
        $client = new Client(
            apiKey: 'ibr_live_abc',
            transporte: function (string $metodo, string $url, array $headers, mixed $corpo) use (&$chamadas): array {
                $chamadas[] = [$metodo, $url, $headers, $corpo];

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'id' => '123',
                        'nome' => 'Empresa X',
                        'status' => 'ATIVA',
                        'ambiente' => 'PRODUCAO',
                        'criadaEm' => '2026-01-01T00:00:00.000Z',
                    ], JSON_THROW_ON_ERROR),
                ];
            },
        );

        $conta = $client->obterConta();

        self::assertSame('Empresa X', $conta['nome']);
        self::assertCount(1, $chamadas);
        [$metodo, $url, $headers] = $chamadas[0];
        self::assertSame('GET', $metodo);
        self::assertStringEndsWith('/v1/account', $url);
        self::assertContains('Authorization: Bearer ibr_live_abc', $headers);
    }

    public function testListarEmpresasDevolveArrayDireto(): void
    {
        $client = new Client(
            apiKey: 'ibr_test_abc',
            transporte: fn (): array => [
                'status' => 200,
                'body' => json_encode([['id' => '1'], ['id' => '2']], JSON_THROW_ON_ERROR),
            ],
        );

        $empresas = $client->listarEmpresas();

        self::assertCount(2, $empresas);
    }

    public function testLancaApiExceptionComStatusCodeEMensagem(): void
    {
        $client = new Client(
            apiKey: 'ibr_test_abc',
            transporte: fn (): array => [
                'status' => 404,
                'body' => json_encode([
                    'statusCode' => 404,
                    'message' => 'Empresa não encontrada nesta conta.',
                    'error' => 'Not Found',
                ], JSON_THROW_ON_ERROR),
            ],
        );

        try {
            $client->obterEmpresa('inexistente');
            self::fail('Esperava ApiException');
        } catch (ApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertTrue($e->isNaoEncontrado());
            self::assertSame(['Empresa não encontrada nesta conta.'], $e->getMensagens());
        }
    }

    public function testListarDocumentosMontaQueryStringComFiltros(): void
    {
        $urlCapturada = null;
        $client = new Client(
            apiKey: 'ibr_test_abc',
            transporte: function (string $metodo, string $url) use (&$urlCapturada): array {
                $urlCapturada = $url;

                return ['status' => 200, 'body' => json_encode(['itens' => [], 'proximoCursor' => null], JSON_THROW_ON_ERROR)];
            },
        );

        $client->listarDocumentos(['situacao' => 'AUTORIZADA', 'limite' => 50, 'cnpjs' => ['111', '222']]);

        self::assertStringContainsString('situacao=AUTORIZADA', $urlCapturada);
        self::assertStringContainsString('limite=50', $urlCapturada);
        self::assertStringContainsString('cnpjs=111%2C222', $urlCapturada);
    }

    public function testPaginarDocumentosSegueProximoCursorAteVirNull(): void
    {
        $chamada = 0;
        $client = new Client(
            apiKey: 'ibr_test_abc',
            transporte: function () use (&$chamada): array {
                $chamada++;
                if ($chamada === 1) {
                    return [
                        'status' => 200,
                        'body' => json_encode(['itens' => [['id' => 'a'], ['id' => 'b']], 'proximoCursor' => 'b'], JSON_THROW_ON_ERROR),
                    ];
                }

                return [
                    'status' => 200,
                    'body' => json_encode(['itens' => [['id' => 'c']], 'proximoCursor' => null], JSON_THROW_ON_ERROR),
                ];
            },
        );

        $ids = [];
        foreach ($client->paginarDocumentos() as $doc) {
            $ids[] = $doc['id'];
        }

        self::assertSame(['a', 'b', 'c'], $ids);
        self::assertSame(2, $chamada);
    }
}
