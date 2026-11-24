<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Tests\Unit\Support;

use Paseto\CiotAntt\Support\AnttHttpDiagnostics;
use Paseto\CiotAntt\Tests\TestCase;

final class AnttHttpDiagnosticsTest extends TestCase
{
    public function test_detecta_iis_403_17(): void
    {
        $html = '<title>IIS 8.5 Detailed Error - 403.17 - Forbidden</title>';

        $msg = AnttHttpDiagnostics::mensagemParaExcecao(403, $html, 'ConsultarSituacaoTransportador');

        $this->assertStringContainsString('403.17', $msg);
        $this->assertStringContainsString('credenciado', $msg);
    }

    public function test_json_de_erro_da_api(): void
    {
        $corpo = json_encode([
            'error' => 'CERTIFICADO_NAO_FORNECIDO',
            'message' => 'Certificado digital não fornecido',
        ], JSON_THROW_ON_ERROR);

        $msg = AnttHttpDiagnostics::mensagemParaExcecao(401, $corpo, 'X');

        $this->assertStringContainsString('CERTIFICADO_NAO_FORNECIDO', $msg);
    }
}