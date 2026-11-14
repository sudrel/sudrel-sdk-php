<?php
declare(strict_types=1);

// Confere o arquivo distribuído pelo Composer, sem carregar o fonte do monorepo.
require $argv[1];

function exige(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$chamadas = [];
$falhar = false;
$transporte = function ($metodo, $url, $cabecalhos, $corpo) use (&$chamadas, &$falhar) {
    $chamadas[] = compact('metodo', 'url', 'cabecalhos', 'corpo');
    if ($falhar) {
        $falhar = false;
        throw new RuntimeException('Falha de rede fictícia.');
    }
    return ['status' => 200, 'cabecalhos' => [], 'corpo' => json_encode(['id' => 'nota-ficticia', 'status' => 'autorizada'])];
};
$sdk = new Sudrel\Sudrel(['chave' => 'sdr_test_ficticia', 'transporte' => $transporte, 'esperaBaseMs' => 1]);
$cenarios = 0;
foreach (['nfse', 'nfe', 'nfce', 'dce'] as $recurso) {
    exige($sdk->$recurso->obter('referencia/com espaço')['id'] === 'nota-ficticia', "Falta {$recurso} no pacote.");
    exige(str_ends_with($chamadas[array_key_last($chamadas)]['url'], "/{$recurso}/referencia%2Fcom%20espa%C3%A7o"), 'Identificador não escapado.');
    $cenarios++;
}
$antes = count($chamadas);
$falhar = true;
$sdk->dce->emitir(['empresa_id' => 'empresa-ficticia']);
$tentativas = array_slice($chamadas, $antes);
exige(count($tentativas) === 2, 'A falha de rede não foi repetida.');
exige($tentativas[0]['cabecalhos']['Idempotency-Key'] === $tentativas[1]['cabecalhos']['Idempotency-Key'], 'A retentativa mudou a chave.');
exige($tentativas[0]['cabecalhos']['User-Agent'] === 'sudrel/sdk-php 0.2.0', 'Versão incorreta no transporte.');
$cenarios++;
$rejeitado = new Sudrel\Sudrel([
    'chave' => 'sdr_test_ficticia',
    'transporte' => fn () => ['status' => 422, 'cabecalhos' => [], 'corpo' => '{"codigo":"rejeitada","dce":{"id":"recusada","status":"rejeitada"}}'],
]);
exige($rejeitado->dce->emitir([])['status'] === 'rejeitada', 'A rejeição fiscal perdeu a declaração.');
$cenarios++;
echo "{$cenarios} cenários do SDK PHP distribuído aprovados, sem emissão real.\n";
