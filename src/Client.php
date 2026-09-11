<?php

declare(strict_types=1);

namespace Sudrel\NfseSdk;

/**
 * Cliente oficial da API pública da Sudrel NFS-e Recebidas.
 *
 * ```php
 * $client = new Client($_ENV['SUDREL_API_KEY']);
 * $empresas = $client->listarEmpresas();
 * ```
 */
final class Client
{
    private const BASE_URL_PADRAO = 'https://api.sudrel.com.br/api';

    /** @var callable(string,string,array<int,string>,mixed):array{status:int,body:string} */
    private $transporte;

    /**
     * @param string $apiKey Chave de API — `sdr_live_...` (produção) ou `sdr_test_...` (sandbox).
     * @param string $baseUrl Sobrescreve a URL base — usado só em testes/desenvolvimento.
     * @param int $timeoutSegundos Timeout por requisição.
     * @param callable|null $transporte Função de transporte HTTP customizada — usada em testes.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = self::BASE_URL_PADRAO,
        private readonly int $timeoutSegundos = 30,
        ?callable $transporte = null,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Client: $apiKey é obrigatório.');
        }
        $this->transporte = $transporte ?? $this->transporteCurl(...);
    }

    /**
     * @param array<int,string> $headers
     * @return array{status:int,body:string}
     */
    private function transporteCurl(string $metodo, string $url, array $headers, mixed $corpoParaEnviar): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Não foi possível iniciar a requisição cURL.');
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSegundos);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($corpoParaEnviar !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpoParaEnviar);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $erro = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Falha de conexão com a API do Sudrel: {$erro}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $body];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $corpo
     * @param array<string,mixed>|null $multipart
     */
    private function requisitar(string $metodo, string $caminho, array $query = [], ?array $corpo = null, ?array $multipart = null): mixed
    {
        $query = array_filter($query, static fn (mixed $v): bool => $v !== null);
        $url = $this->baseUrl . $caminho;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Authorization: Bearer ' . $this->apiKey];
        $corpoParaEnviar = null;
        if ($multipart !== null) {
            $corpoParaEnviar = $multipart;
        } elseif ($corpo !== null) {
            $headers[] = 'Content-Type: application/json';
            $corpoParaEnviar = json_encode($corpo, JSON_THROW_ON_ERROR);
        }

        $resultado = ($this->transporte)($metodo, $url, $headers, $corpoParaEnviar);
        $status = $resultado['status'];
        $body = $resultado['body'];

        if ($status === 204 || $body === '') {
            return null;
        }

        $dados = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $mensagem = $dados['message'] ?? 'Erro desconhecido.';
            throw new ApiException($status, $mensagem, $dados['error'] ?? null);
        }

        return $dados;
    }

    /** `GET /v1/account` — identifica a conta dona da chave de API usada. */
    public function obterConta(): array
    {
        return $this->requisitar('GET', '/v1/account');
    }

    /** `GET /v1/usage` — franquia, consumo e excedente do ciclo em andamento. */
    public function obterConsumo(): array
    {
        return $this->requisitar('GET', '/v1/usage');
    }

    /** `GET /v1/companies` — todas as empresas do ambiente da chave usada. Não é paginado. */
    public function listarEmpresas(): array
    {
        return $this->requisitar('GET', '/v1/companies');
    }

    /** `GET /v1/companies/{id}` */
    public function obterEmpresa(string $id): array
    {
        return $this->requisitar('GET', '/v1/companies/' . rawurlencode($id));
    }

    /**
     * `POST /v1/companies` — cadastra um CNPJ para monitoramento.
     *
     * @param array{cnpj:string,nomeExibicao?:string,nsuInicial?:int} $dados
     */
    public function criarEmpresa(array $dados): array
    {
        return $this->requisitar('POST', '/v1/companies', [], $dados);
    }

    /**
     * `POST /v1/companies/{id}/certificate` — envia (ou troca) o
     * certificado A1 (.pfx/.p12, até 10MB).
     */
    public function enviarCertificado(string $id, string $caminhoArquivo, string $senha): array
    {
        if (!is_readable($caminhoArquivo)) {
            throw new \InvalidArgumentException("Arquivo de certificado não encontrado ou sem permissão de leitura: {$caminhoArquivo}");
        }

        $multipart = [
            'certificado' => new \CURLFile($caminhoArquivo),
            'senha' => $senha,
        ];

        return $this->requisitar('POST', '/v1/companies/' . rawurlencode($id) . '/certificate', [], null, $multipart);
    }

    /** `POST /v1/companies/{id}/pause` */
    public function pausarEmpresa(string $id): array
    {
        return $this->requisitar('POST', '/v1/companies/' . rawurlencode($id) . '/pause');
    }

    /** `POST /v1/companies/{id}/resume` */
    public function retomarEmpresa(string $id): array
    {
        return $this->requisitar('POST', '/v1/companies/' . rawurlencode($id) . '/resume');
    }

    /** `DELETE /v1/companies/{id}` — solicita a remoção (primeiro passo; a confirmação é feita pelo painel). */
    public function removerEmpresa(string $id): void
    {
        $this->requisitar('DELETE', '/v1/companies/' . rawurlencode($id));
    }

    /**
     * `GET /v1/documents` — uma página de notas. Use `paginarDocumentos`
     * pra percorrer tudo.
     *
     * @param array<string,mixed> $filtros cnpjs (string|string[]), situacao,
     *   papel, prestador, numero, chaveAcesso, valorMinCentavos,
     *   valorMaxCentavos, dataInicio, dataFim, cursor, limite.
     */
    public function listarDocumentos(array $filtros = []): array
    {
        if (isset($filtros['cnpjs']) && is_array($filtros['cnpjs'])) {
            $filtros['cnpjs'] = implode(',', $filtros['cnpjs']);
        }

        return $this->requisitar('GET', '/v1/documents', $filtros);
    }

    /** `GET /v1/documents/{id}` — inclui XML original e linha do tempo de eventos. */
    public function obterDocumento(string $id): array
    {
        return $this->requisitar('GET', '/v1/documents/' . rawurlencode($id));
    }

    /**
     * Percorre todas as páginas automaticamente, seguindo `proximoCursor`
     * até ele vir vazio/null. Útil pra sincronizações completas — pra
     * volumes grandes, prefira filtrar por `dataInicio`/`dataFim` e usar
     * webhooks pra novidades em tempo real, em vez de repetir isso com
     * frequência.
     *
     * @param array<string,mixed> $filtros
     * @return \Generator<int,array<string,mixed>>
     */
    public function paginarDocumentos(array $filtros = []): \Generator
    {
        $cursor = $filtros['cursor'] ?? null;
        while (true) {
            $pagina = $this->listarDocumentos([...$filtros, 'cursor' => $cursor]);
            foreach ($pagina['itens'] as $item) {
                yield $item;
            }
            if (empty($pagina['proximoCursor'])) {
                return;
            }
            $cursor = $pagina['proximoCursor'];
        }
    }
}
