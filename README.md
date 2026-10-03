# sudrel/sdk

SDK oficial em PHP da API de emissão de NFS-e da [Sudrel](https://sudrel.com.br).
PHP 8.1 ou mais novo, com as extensões `curl` e `json`. Sem outras dependências.

```bash
composer require sudrel/sdk
```

## Primeira nota

```php
$sudrel = new \Sudrel\Sudrel(['chave' => getenv('SUDREL_API_KEY')]); // sdr_test_... no sandbox

$nota = $sudrel->nfse->emitir([
    'empresa_id' => '0b7e6a52-3c1d-4f8e-9a20-5d4c3b2a1f00',
    'referencia' => 'PED-1001', // mande sempre: é o que torna seguro repetir
    'tomador' => ['documento' => '11222333000181'],
    'valor' => '1500.00',
    'servico' => ['descricao' => 'Consultoria em sistemas'],
]);

$final = $sudrel->nfse->esperar($nota['id']); // autorizada, rejeitada, falha_transmissao...
```

`emitir` devolve a nota em qualquer desfecho da Sefin, inclusive `rejeitada` (com
`rejeicao.explicacao`). Erro do pedido (validação, empresa não pronta, referência repetida) lança
`\Sudrel\ErroSudrel`.

## O que o SDK faz por você

- **Retentativa segura.** Todo `POST` leva uma `Idempotency-Key` gerada uma vez e reaproveitada em cada
  tentativa: se a resposta se perder no caminho, a repetição devolve a mesma nota, nunca uma segunda.
  Repete só erro de rede, 429 (respeitando `Retry-After`) e 5xx.
- **Erros tipados.** `ErroSudrel` traz `status`, `codigo` (estável), `campo`, `correcao`, `erros` e
  `requisicaoId`, que você procura no painel em **Integrações › Log da API**.
- **Paginação.** Listas são geradores: `foreach ($sudrel->nfse->listar(['status' => 'autorizada']) as $nota)`.
- **Acompanhar sem martelar a API.** `$sudrel->nfse->esperar($id)` consulta em 1 s, 2 s, 4 s, até 30 s
  entre consultas. Pra muitas notas, use webhook.
- **Webhook.**

  ```php
  $corpo = file_get_contents('php://input');
  if (!\Sudrel\Webhooks::verificar($corpo, $_SERVER['HTTP_X_SUDREL_SIGNATURE_V2'] ?? null, $segredo)) {
      http_response_code(401);
      exit;
  }
  // Deduplique por $_SERVER['HTTP_X_SUDREL_EVENT_ID'] e responda 2xx rápido.
  ```

## Recursos

| | |
|---|---|
| `$sudrel->nfse` | `emitir`, `validar`, `obter`, `listar`, `cancelar`, `confirmar`, `esperar`, `xml`, `pdf` |
| `$sudrel->lotes` | `criar` (JSON), `enviarPlanilha`, `obter`, `itens`, `emitir`, `reprocessar` |
| `$sudrel->empresas` | `listar`, `prontidao` |
| `$sudrel->webhooks` | `criar`, `listar`, `alterar`, `remover`, `rotacionarSegredo`, `testar`, `entregas`, `reenviar`, `verificar` |
| `$sudrel->eventos` | `pagina`, `listar` (o log da conta, por cursor) |

## Simulador de falhas (sandbox)

```php
$sudrel->nfse->emitir($corpo, ['simular' => 'timeout_depois_autorizada']);
```

Cenários: `autorizada_lenta`, `rejeicao:<codigo>`, `timeout_depois_autorizada`, `sefin_fora`,
`processamento_prolongado`, `falha_transmissao_nao_enviada`, `falha_transmissao_desconhecido`,
`aguardando_confirmacao`. Detalhes na documentação, seção "Simulador de falhas".

## Documentação

https://sudrel.com.br/docs
