<?php

declare(strict_types=1);

use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\ConsultarCiotGeradoRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools, array $config) {
    $id12 = Bootstrap::argOuParam(3, 'id_operacao', (string) ($config['ciot_12'] ?? ''));
    if ($id12 === '' && ($config['ciot_16'] ?? '') !== '') {
        $id12 = substr((string) $config['ciot_16'], 0, 12);
    }
    if (strlen($id12) !== 12) {
        throw new \InvalidArgumentException('Informe ciot_12 ou id_operacao (12 dígitos).');
    }

    $ano = (int) Bootstrap::argOuParam(4, 'ano', (string) ($config['ano_declaracao'] ?: date('Y')));

    $resposta = $tools->consultarCiotGerado(
        new ConsultarCiotGeradoRequest($id12, $ano),
    );

    return [
        'servico' => '08 ConsultarCIOTGerado',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'ciot' => $resposta->ciotComDigito(),
        'antt' => $resposta->raw(),
    ];
});