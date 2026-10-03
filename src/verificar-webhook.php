<?php
// Tolerância recomendada: 5 minutos entre o t= e o seu relógio.
const TOLERANCIA_SEGUNDOS = 300;

// $corpoBruto = file_get_contents('php://input');
// $cabecalhoV2 = $_SERVER['HTTP_X_SUDREL_SIGNATURE_V2'] ?? null;
function webhookSudrelValido(string $corpoBruto, ?string $cabecalhoV2, string $segredo, ?int $agora = null): bool
{
    if (!$cabecalhoV2) {
        return false;
    }
    $timestamp = '';
    $assinaturas = [];
    foreach (explode(',', $cabecalhoV2) as $parte) {
        [$chave, $valor] = array_pad(explode('=', trim($parte), 2), 2, '');
        if ($chave === 't') {
            $timestamp = $valor;
        } elseif ($chave === 'v1' && $valor !== '') {
            $assinaturas[] = $valor;
        }
    }
    if (!ctype_digit($timestamp)) {
        return false;
    }
    $agora = $agora ?? time();
    if (abs($agora - (int) $timestamp) > TOLERANCIA_SEGUNDOS) {
        return false;
    }
    $esperada = hash_hmac('sha256', $timestamp . '.' . $corpoBruto, $segredo);
    foreach ($assinaturas as $assinatura) {
        if (hash_equals($esperada, $assinatura)) {
            return true;
        }
    }
    return false;
}
