<?php

declare(strict_types=1);

namespace Sudrel;

/**
 * Núcleo do SDK: autenticação, retentativa segura e erro tipado.
 *
 * Retentativa só onde repetir não muda o resultado: erro de REDE, 429
 * (respeitando `Retry-After`) e 5xx. Todo POST leva uma `Idempotency-Key`
 * gerada aqui UMA vez e reaproveitada em cada tentativa: se a conexão cai
 * depois de a Sudrel receber a nota, a repetição devolve a mesma nota.
 * 4xx nunca é repetido.
 */
class ClienteHttp
{
    public readonly string $baseUrl;
    private readonly string $chave;
    private readonly int $retentativas;
    private readonly int $esperaBaseMs;
    /** @var callable(string, string, array<string, string>, string|array<string, mixed>|null): array{status: int, cabecalhos: array<string, string>, corpo: string} */
    private $transporte;

    /**
     * @param array{chave?: string, baseUrl?: string, retentativas?: int, esperaBaseMs?: int, transporte?: callable} $opcoes
     */
    public function __construct(array $opcoes = [])
    {
        $chave = $opcoes['chave'] ?? (getenv('SUDREL_API_KEY') ?: null);
        if (!$chave) {
            throw new \InvalidArgumentException('Informe a chave de API (opção "chave" ou a variável SUDREL_API_KEY).');
        }
        $this->chave = $chave;
        $this->baseUrl = rtrim($opcoes['baseUrl'] ?? (getenv('SUDREL_API_URL') ?: 'https://api.sudrel.com.br/api'), '/');
        $this->retentativas = $opcoes['retentativas'] ?? 3;
        $this->esperaBaseMs = $opcoes['esperaBaseMs'] ?? 500;
        $this->transporte = $opcoes['transporte'] ?? [self::class, 'transporteCurl'];
    }

    /**
     * @param array{query?: array<string, scalar|null>, corpo?: mixed, formulario?: array<string, mixed>, cabecalhos?: array<string, string>, idempotencyKey?: string, binario?: bool} $opcoes
     * @return array{status: int, corpo: mixed, cabecalhos: array<string, string>}
     */
    public function requisitar(string $metodo, string $caminho, array $opcoes = []): array
    {
        $query = array_filter($opcoes['query'] ?? [], fn ($v) => $v !== null && $v !== '');
        $url = $this->baseUrl . $caminho . ($query ? '?' . http_build_query($query) : '');
        $idempotencyKey = $metodo === 'POST' ? ($opcoes['idempotencyKey'] ?? self::uuid()) : null;
        $cabecalhos = [
            'Authorization' => "Bearer {$this->chave}",
            'Accept' => 'application/json',
            'User-Agent' => 'sudrel/sdk-php 0.1.0',
        ];
        if ($idempotencyKey) {
            $cabecalhos['Idempotency-Key'] = $idempotencyKey;
        }
        $corpo = null;
        if (isset($opcoes['formulario'])) {
            $corpo = $opcoes['formulario'];
        } elseif (array_key_exists('corpo', $opcoes)) {
            $corpo = json_encode($opcoes['corpo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $cabecalhos['Content-Type'] = 'application/json';
        }
        $cabecalhos = array_merge($cabecalhos, $opcoes['cabecalhos'] ?? []);

        $ultimaFalha = null;
        for ($tentativa = 0; $tentativa <= $this->retentativas; $tentativa++) {
            if ($tentativa > 0) {
                usleep($this->esperaBaseMs * 1000 * (2 ** ($tentativa - 1)));
            }
            try {
                $resposta = ($this->transporte)($metodo, $url, $cabecalhos, $corpo);
            } catch (\RuntimeException $erro) {
                $ultimaFalha = $erro;
                continue; // rede: repete com a MESMA Idempotency-Key
            }
            $status = $resposta['status'];
            if ($status === 429 || $status >= 500) {
                $ultimaFalha = $this->erroDa($status, $resposta['corpo']);
                if ($tentativa < $this->retentativas) {
                    $retryAfter = (int) ($resposta['cabecalhos']['retry-after'] ?? 0);
                    if ($status === 429 && $retryAfter > 0) {
                        sleep($retryAfter);
                    }
                    continue;
                }
                throw $ultimaFalha;
            }
            if ($status >= 400) {
                throw $this->erroDa($status, $resposta['corpo']);
            }
            if ($status === 204) {
                return ['status' => 204, 'corpo' => null, 'cabecalhos' => $resposta['cabecalhos']];
            }
            $conteudo = ($opcoes['binario'] ?? false) ? $resposta['corpo'] : json_decode($resposta['corpo'], true);
            return ['status' => $status, 'corpo' => $conteudo, 'cabecalhos' => $resposta['cabecalhos']];
        }
        if ($ultimaFalha instanceof ErroSudrel) {
            throw $ultimaFalha;
        }
        throw new ErroDeRede($ultimaFalha ? $ultimaFalha->getMessage() : 'sem resposta', $idempotencyKey);
    }

    private function erroDa(int $status, string $corpo): ErroSudrel
    {
        $lido = json_decode($corpo, true);
        return new ErroSudrel($status, is_array($lido) ? $lido : []);
    }

    /**
     * Transporte padrão, por cURL. Falha de rede vira `\RuntimeException`.
     *
     * @param array<string, string> $cabecalhos
     * @param string|array<string, mixed>|null $corpo
     * @return array{status: int, cabecalhos: array<string, string>, corpo: string}
     */
    public static function transporteCurl(string $metodo, string $url, array $cabecalhos, string|array|null $corpo): array
    {
        $ch = curl_init($url);
        $recebidos = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array_map(fn ($n, $v) => "{$n}: {$v}", array_keys($cabecalhos), $cabecalhos),
            CURLOPT_HEADERFUNCTION => function ($_ch, string $linha) use (&$recebidos) {
                $partes = explode(':', $linha, 2);
                if (count($partes) === 2) {
                    $recebidos[strtolower(trim($partes[0]))] = trim($partes[1]);
                }
                return strlen($linha);
            },
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
        }
        $saida = curl_exec($ch);
        if ($saida === false) {
            $erro = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($erro ?: 'falha de rede');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'cabecalhos' => $recebidos, 'corpo' => (string) $saida];
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
