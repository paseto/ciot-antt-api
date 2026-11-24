<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools, array $config) {
    $ciot = Bootstrap::argOuParam(3, 'ciot', (string) ($config['ciot_16'] ?? ''));
    if (strlen($ciot) !== 16) {
        throw new \InvalidArgumentException('Informe ciot_16 (16 dígitos) em config.json, ?ciot= ou 3º argumento CLI.');
    }

    $peso = (float) Bootstrap::param('peso', '25000');

    $resposta = $tools->encerrarOperacao(
        (new EncerramentoOperacaoTransporteRequest($ciot))
            ->comPesoTotalCarga($peso),
    );

    return [
        'servico' => '06 EncerramentoOperacaoTransporte',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'data_encerramento' => $resposta->dataEncerramento(),
        'antt' => $resposta->raw(),
    ];
});