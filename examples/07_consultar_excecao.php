<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools) {
    $documento = Bootstrap::argOuParam(3, 'documento', Bootstrap::empresa('cpf_cnpj_transportador'));

    $resposta = $tools->consultarExcecao($documento);

    return [
        'servico' => '07 ConsultarExcecao',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'na_lista' => $resposta->naLista(),
        'cpf_cnpj' => $resposta->cpfCnpjTransportador(),
        'antt' => $resposta->raw(),
    ];
});