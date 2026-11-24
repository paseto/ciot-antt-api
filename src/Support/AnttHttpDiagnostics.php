<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Support;

/**
 * Traduz respostas HTML/JSON da camada de gateway da ANTT (IIS / API) em texto útil.
 */
final class AnttHttpDiagnostics
{
    public static function mensagemParaExcecao(int $status, string $corpo, string $servico): string
    {
        $json = json_decode($corpo, true);

        if (is_array($json) && isset($json['message'], $json['error'])) {
            return sprintf(
                'A ANTT retornou HTTP %d ao chamar %s: %s (%s).',
                $status,
                $servico,
                (string) $json['message'],
                (string) $json['error'],
            );
        }

        if ($status === 403 && str_contains($corpo, '403.17')) {
            return 'O servidor da ANTT recusou o certificado digital (IIS 403.17 — vencido, '
                . 'ainda não válido ou não aceito neste ambiente). Confirme se o A1 está vigente, '
                . 'se o CNPJ do certificado está credenciado para CIOT/PEF em homologação e se o '
                . 'ambiente em config.json corresponde ao credenciamento (homologacao vs producao). '
                . "Serviço: {$servico}.";
        }

        if ($status === 403 && str_contains($corpo, '403.16')) {
            return 'O servidor da ANTT não confia no certificado digital (IIS 403.16). '
                . 'Verifique se o certificado é ICP-Brasil A1/A3 e se a cadeia está completa. '
                . "Serviço: {$servico}.";
        }

        if ($status === 403 && (str_contains($corpo, '403.7') || str_contains($corpo, 'certificate required'))) {
            return 'A ANTT exige certificado cliente (mTLS), mas ele não foi aceito na conexão (HTTP 403). '
                . "Serviço: {$servico}.";
        }

        if ($status === 401) {
            return 'A ANTT exige certificado digital (mTLS) válido e habilitado (HTTP 401). '
                . "Serviço: {$servico}.";
        }

        return "Resposta não-JSON da ANTT (HTTP {$status}) ao chamar {$servico}.";
    }
}