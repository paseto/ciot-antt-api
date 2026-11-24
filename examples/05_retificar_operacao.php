<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\Components\DadosCarga;
use Paseto\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools, array $config) {
    $ciot = Bootstrap::argOuParam(3, 'ciot', (string) ($config['ciot_16'] ?? ''));
    if (strlen($ciot) !== 16) {
        throw new \InvalidArgumentException('Informe ciot_16 (16 dígitos). Carga lotação (tipo 1) não pode ser retificada (B121).');
    }

    $peso = (float) Bootstrap::param('peso', '26000');
    $natureza = (int) Bootstrap::param('natureza_carga', '2401');

    $resposta = $tools->retificarOperacao(
        (new RetificacaoOperacaoTransporteRequest($ciot))
            ->comValorFrete((float) Bootstrap::param('valor_frete', '1600'))
            ->comDadosCarga(new DadosCarga($natureza, $peso)),
    );

    return [
        'servico' => '05 RetificacaoOperacaoTransporte',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'data_retificacao' => $resposta->dataRetificacao(),
        'antt' => $resposta->raw(),
    ];
});