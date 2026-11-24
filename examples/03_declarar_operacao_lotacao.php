<?php

declare(strict_types=1);

use Paseto\CiotAntt\Enums\TipoCarga;
use Paseto\CiotAntt\Enums\TipoPagamento;
use Paseto\CiotAntt\Examples\Bootstrap;
use Paseto\CiotAntt\Requests\Components\DadosCarga;
use Paseto\CiotAntt\Requests\Components\InfIndicadoresOperacionais;
use Paseto\CiotAntt\Requests\Components\InfPagamento;
use Paseto\CiotAntt\Requests\Components\Localidade;
use Paseto\CiotAntt\Requests\Components\OrigemDestino;
use Paseto\CiotAntt\Requests\Components\Veiculo;
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;

require_once __DIR__ . '/Bootstrap.php';

Bootstrap::run(static function ($tools, array $config) {
    $contratado = Bootstrap::empresa('cpf_cnpj_transportador');
    $rntrc = Bootstrap::empresa('rntrc_transportador');
    $contratante = Bootstrap::empresa('cpf_cnpj_contratante', Bootstrap::empresa('cpf_cnpj_interessado'));
    $destinatario = Bootstrap::empresa('cpf_cnpj_destinatario');
    $placa = Bootstrap::empresa('placa_automotor');

    $id = Bootstrap::argOuParam(3, 'id_operacao', (string) ($config['id_operacao_transporte'] ?? ''));
    if ($id === '') {
        throw new \InvalidArgumentException(
            'Informe id_operacao_transporte (12 dígitos) em config.json, ?id_operacao= ou como 3º argumento CLI.',
        );
    }

    $natureza = (int) Bootstrap::param('natureza_carga', '2401');
    $hoje = new \DateTimeImmutable('today');

    $declaracao = DeclaracaoOperacaoTransporteRequest::cargaLotacao($contratado, $rntrc, $contratante)
        ->comId($id)
        ->comValorFrete((float) Bootstrap::param('valor_frete', '1500'))
        ->comDestinatario($destinatario)
        ->comViagem($hoje->modify('+1 day'), $hoje->modify('+5 days'))
        ->comVeiculos(Veiculo::automotor($placa, 2, $rntrc))
        ->comOrigemDestino(
            OrigemDestino::entre(
                Localidade::origem()->comMunicipio(3550308),
                Localidade::destino()->comMunicipio(3304557),
            )->comDistancia(430),
        )
        ->comDadosCarga(new DadosCarga($natureza, 25000, TipoCarga::CargaGeral))
        ->comPagamentos(new InfPagamento(
            tipoPagamento: TipoPagamento::ContaCorrente,
            cpfCnpjCreditado: $contratado,
            codigoInstituicaoFinanceira: (int) Bootstrap::param('banco', '237'),
            numeroAgencia: Bootstrap::param('agencia', '1234'),
            numeroConta: Bootstrap::param('conta', '56789'),
        ))
        ->comIndicadores(new InfIndicadoresOperacionais());

    $resposta = $tools->declararOperacao($declaracao);

    return [
        'servico' => '03 DeclaracaoOperacaoTransporte',
        'sucesso' => $resposta->sucesso(),
        'codigo' => $resposta->codigo(),
        'mensagem' => $resposta->mensagem(),
        'ciot' => $resposta->ciotComDigito(),
        'protocolo' => $resposta->protocolo(),
        'aviso' => $resposta->avisoTransportador(),
        'antt' => $resposta->raw(),
    ];
});