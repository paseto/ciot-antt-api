<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Common;

use Paseto\CiotAntt\Enums\Ambiente;
use Paseto\CiotAntt\Exceptions\CiotConfigurationException;

/**
 * URLs oficiais do PEF/ANTT e do Gerador CIOT v3 por ambiente (DCS PEF).
 */
final class Environment
{
    public static function urlPef(Ambiente|string $ambiente): string
    {
        $valor = $ambiente instanceof Ambiente ? $ambiente->value : $ambiente;

        return match ($valor) {
            Ambiente::Producao->value => 'https://appservices.antt.gov.br/pefServices',
            Ambiente::Homologacao->value => 'https://appservices-hml.antt.gov.br/pefServices',
            default => throw CiotConfigurationException::ambienteInvalido($valor),
        };
    }

    public static function urlGerador(Ambiente|string $ambiente): string
    {
        $valor = $ambiente instanceof Ambiente ? $ambiente->value : $ambiente;

        return match ($valor) {
            Ambiente::Producao->value => 'https://mtcuybq605.execute-api.sa-east-1.amazonaws.com/api-ciot-prd/GeradorCIOT',
            Ambiente::Homologacao->value => 'https://appservices-hml.antt.gov.br/pefServices',
            default => throw CiotConfigurationException::ambienteInvalido($valor),
        };
    }

    public static function resolveAmbiente(string $ambiente): Ambiente
    {
        return Ambiente::tryFrom($ambiente)
            ?? throw CiotConfigurationException::ambienteInvalido($ambiente);
    }
}