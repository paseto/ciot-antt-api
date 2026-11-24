<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools, array $config) {
    $ciot = Bootstrap::argOuParam(3, 'ciot', (string) ($config['ciot_16'] ?? ''));
    if (strlen($ciot) !== 16) {
        throw new \InvalidArgumentException('Informe ciot_16 (16 dígitos) em config.json, ?ciot= ou 3º argumento CLI.');
    }

    $motivo = Bootstrap::argOuParam(4, 'motivo', 'Cancelamento de teste em homologação.');

    $resposta = $tools->cancelarOperacao(
        new CancelamentoOperacaoTransporteRequest($ciot, $motivo),
    );

    return [
        'servico' => '04 CancelamentoOperacaoTransporte',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'data_cancelamento' => $resposta->dataCancelamento(),
        'antt' => $resposta->raw(),
    ];
});