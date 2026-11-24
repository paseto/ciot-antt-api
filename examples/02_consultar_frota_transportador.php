<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools) {
    $interessado = Bootstrap::argOuParam(3, 'interessado', Bootstrap::empresa('cpf_cnpj_interessado'));
    $transportador = Bootstrap::argOuParam(4, 'transportador', Bootstrap::empresa('cpf_cnpj_transportador'));
    $rntrc = Bootstrap::argOuParam(5, 'rntrc', Bootstrap::empresa('rntrc_transportador'));
    $placasRaw = Bootstrap::argOuParam(6, 'placas', Bootstrap::empresa('placa_automotor'));
    $placas = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $placasRaw) ?: [])));

    $resposta = $tools->consultarFrotaTransportador(
        new ConsultarFrotaTransportadorRequest($interessado, $transportador, $rntrc, $placas),
    );

    return [
        'servico' => '02 ConsultarFrotaTransportador',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'frota' => $resposta->frota(),
        'antt' => $resposta->raw(),
    ];
});