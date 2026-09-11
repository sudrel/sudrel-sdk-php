# sudrel/nfse-sdk (PHP)

Cliente oficial PHP para a [API pública da Sudrel NFS-e Recebidas](https://sudrel.com.br/docs) — consulte e gerencie, de forma programática, as NFS-e (notas de serviço) monitoradas pela sua conta Sudrel.

- Documentação completa da API: **https://sudrel.com.br/docs**
- Requer PHP **8.1+** com as extensões `curl` e `json` (praticamente universais — sem dependências externas de runtime).

## Instalação

```bash
composer require sudrel/nfse-sdk
```

## Uso rápido

```php
<?php

require 'vendor/autoload.php';

use Sudrel\NfseSdk\Client;

$client = new Client($_ENV['SUDREL_API_KEY']);

$conta = $client->obterConta();
echo $conta['nome'], ' ', $conta['ambiente'], PHP_EOL; // "Empresa Exemplo LTDA PRODUCAO"

$empresas = $client->listarEmpresas();
$notas = $client->listarDocumentos(['situacao' => 'AUTORIZADA', 'limite' => 50]);
```

Gere uma chave em **Painel → Chaves de API** (`/painel/chaves-api`). Ela só é exibida uma vez — se perder, revogue e crie outra. Existem dois ambientes de chave, que nunca se misturam:

| Prefixo | Ambiente |
|---|---|
| `sdr_test_...` | Sandbox — dados de teste, nunca reais, nunca geram cobrança. |
| `sdr_live_...` | Produção — dados fiscais reais da sua conta. |

## Empresas (CNPJs monitorados)

```php
// Listar (GET /companies não é paginado)
$empresas = $client->listarEmpresas();

// Cadastrar um CNPJ novo
$empresa = $client->criarEmpresa(['cnpj' => '12345678000195', 'nomeExibicao' => 'Filial São Paulo']);

// Enviar o certificado A1 (.pfx/.p12)
$client->enviarCertificado($empresa['id'], '/caminho/para/certificado.pfx', 'senha-do-certificado');

// Pausar / retomar
$client->pausarEmpresa($empresa['id']);
$client->retomarEmpresa($empresa['id']);

// Solicitar remoção (primeiro passo — a confirmação final é feita pelo painel)
$client->removerEmpresa($empresa['id']);
```

## Documentos (notas fiscais)

`GET /documents` usa paginação por cursor — passe `proximoCursor` de volta em `cursor` na próxima chamada:

```php
$cursor = null;
do {
    $pagina = $client->listarDocumentos(['cursor' => $cursor, 'limite' => 100]);
    foreach ($pagina['itens'] as $nota) {
        echo $nota['numero'], ' ', $nota['valorServicos'], ' ', $nota['situacao'], PHP_EOL;
    }
    $cursor = $pagina['proximoCursor'];
} while ($cursor !== null);
```

Ou use o gerador `paginarDocumentos`, que faz esse loop por você:

```php
foreach ($client->paginarDocumentos(['situacao' => 'AUTORIZADA']) as $nota) {
    echo $nota['numero'], PHP_EOL;
}
```

Detalhe de uma nota (inclui XML original e linha do tempo de eventos):

```php
$detalhe = $client->obterDocumento($nota['id']);
print_r($detalhe['eventos']);
```

## Consumo do ciclo atual

```php
$consumo = $client->obterConsumo();
if ($consumo['temCicloAtivo']) {
    echo "{$consumo['eventosIncluidos']}/{$consumo['franquiaEventos']} eventos usados neste ciclo", PHP_EOL;
}
```

## Tratamento de erros

Toda chamada que falha lança `Sudrel\NfseSdk\ApiException`, com `getStatusCode()`, `getMensagens()` (sempre um array, mesmo quando a API devolve uma string única) e helpers pros casos mais comuns:

```php
use Sudrel\NfseSdk\ApiException;

try {
    $client->obterEmpresa('id-que-nao-existe');
} catch (ApiException $e) {
    if ($e->isNaoEncontrado()) {
        // 404 — não existe nesta conta, ou existe só no outro ambiente (sandbox/produção)
    }
    if ($e->isRateLimited()) {
        // 429 — 120 requisições/minuto por chave; espere e tente de novo
    }
    error_log("{$e->getStatusCode()}: " . implode(' ', $e->getMensagens()));
}
```

## Webhooks

Configure webhooks pelo painel (**Painel → Webhooks**) pra ser avisado em tempo real (`NOTA_RECEBIDA`, `EVENTO_FISCAL_RECEBIDO`) em vez de ficar consultando `GET /documents`. Cada entrega assina o corpo com HMAC-SHA256 no cabeçalho `X-Sudrel-Signature` — **sempre verifique antes de confiar no payload**:

```php
use Sudrel\NfseSdk\Webhooks;

$corpoBruto = file_get_contents('php://input'); // precisa do corpo BRUTO, não decodificado
$assinatura = $_SERVER['HTTP_X_SUDREL_SIGNATURE'] ?? '';

if (!Webhooks::verificarAssinatura($corpoBruto, $assinatura, $_ENV['SUDREL_WEBHOOK_SECRET'])) {
    http_response_code(401);
    exit('assinatura inválida');
}

$payload = json_decode($corpoBruto, true);
error_log($payload['tipo']);
http_response_code(200);
```

O segredo do webhook só é exibido uma vez, na criação (ou ao rotacionar) — guarde com o mesmo cuidado de uma senha.

## Limite de requisições

120 requisições por minuto, por chave de API (janela fixa de 60s). Passar do limite devolve `429`, exposto como `$e->isRateLimited()`.

## Desenvolvimento

```bash
composer install
composer test
```

## Licença

MIT — veja [LICENSE](./LICENSE).
