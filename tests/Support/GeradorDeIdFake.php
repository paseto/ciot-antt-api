<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Tests\Support;

use Paseto\CiotAntt\Contracts\OperationIdGenerator;
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;

/**
 * Implementação fake do gerador de IdOperacaoTransporte para testes do
 * container/ServiceProvider.
 */
final class GeradorDeIdFake implements OperationIdGenerator
{
    public function gerar(DeclaracaoOperacaoTransporteRequest $request): string
    {
        return '111122223333';
    }
}
