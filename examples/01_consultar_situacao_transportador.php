<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools, array $config) {
    $interessado = Bootstrap::argOuParam(3, 'interessado', Bootstrap::empresa('cpf_cnpj_interessado'));
    $transportador = Bootstrap::argOuParam(4, 'transportador', Bootstrap::empresa('cpf_cnpj_transportador'));
    $rntrc = Bootstrap::argOuParam(5, 'rntrc', Bootstrap::empresa('rntrc_transportador'));

    $resposta = $tools->consultarSituacaoTransportador(
        new ConsultarSituacaoTransportadorRequest($interessado, $transportador, $rntrc),
    );

    return [
        'servico' => '01 ConsultarSituacaoTransportador',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'apto' => $resposta->apto(),
        'nome' => $resposta->nomeRazaoSocial(),
        'tipo_transportador' => $resposta->tipoTransportador()?->value,
        'equiparado_tac' => $resposta->equiparadoTac(),
        'antt' => $resposta->raw(),
    ];
});