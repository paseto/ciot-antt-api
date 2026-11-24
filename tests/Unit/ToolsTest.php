<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Tests\Unit;

use Paseto\CiotAntt\Common\Config;
use Paseto\CiotAntt\Requests\ConsultarExcecaoRequest;
use Paseto\CiotAntt\Tests\Support\FakePefTransport;
use Paseto\CiotAntt\Tests\TestCase;
use Paseto\CiotAntt\Tools;

final class ToolsTest extends TestCase
{
    public function test_consultar_excecao_via_fake_transport(): void
    {
        $fake = new FakePefTransport();
        $fake->programar('ConsultarExcecao', ['Codigo' => '111', 'Mensagem' => 'OK']);

        $config = Config::fromArray(['ambiente' => 'homologacao']);

        $tools = new Tools($config, null, $fake);
        $resposta = $tools->consultarExcecao(new ConsultarExcecaoRequest(self::CPF_VALIDO));

        $this->assertTrue($resposta->sucesso());
        $this->assertSame('ConsultarExcecao', $fake->ultimaChamada()['servico'] ?? null);
    }
}