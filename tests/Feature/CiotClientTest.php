<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Tests\Feature;

use DateTimeImmutable;
use Paseto\CiotAntt\Ciot;
use Paseto\CiotAntt\Contracts\OperationIdGenerator;
use Paseto\CiotAntt\Enums\TipoCarga;
use Paseto\CiotAntt\Enums\TipoPagamento;
use Paseto\CiotAntt\Exceptions\CiotConfigurationException;
use Paseto\CiotAntt\Exceptions\CiotValidationException;
use Paseto\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\Components\DadosCarga;
use Paseto\CiotAntt\Requests\Components\InfIndicadoresOperacionais;
use Paseto\CiotAntt\Requests\Components\InfPagamento;
use Paseto\CiotAntt\Requests\Components\Localidade;
use Paseto\CiotAntt\Requests\Components\OrigemDestino;
use Paseto\CiotAntt\Requests\Components\Veiculo;
use Paseto\CiotAntt\Requests\ConsultarCiotGeradoRequest;
use Paseto\CiotAntt\Requests\ConsultarExcecaoRequest;
use Paseto\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;
use Paseto\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;
use Paseto\CiotAntt\Support\Datas;
use Paseto\CiotAntt\Tests\Support\FakePefTransport;
use Paseto\CiotAntt\Tests\TestCase;

final class CiotClientTest extends TestCase
{
    private FakePefTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakePefTransport();
    }

    private function ciot(?OperationIdGenerator $gen = null): Ciot
    {
        return new Ciot($this->transport, ['validar_antes_de_enviar' => true], $gen);
    }

    private function declaracaoValida(): DeclaracaoOperacaoTransporteRequest
    {
        $hoje = new DateTimeImmutable('today');

        return DeclaracaoOperacaoTransporteRequest::cargaLotacao(self::CNPJ_VALIDO, self::RNTRC_VALIDO, self::CNPJ_VALIDO_2)
            ->comId('000000000123')
            ->comValorFrete(1500)
            ->comDestinatario(self::CNPJ_VALIDO_3)
            ->comViagem($hoje->modify('+1 day'), $hoje->modify('+5 days'))
            ->comVeiculos(Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO))
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comMunicipio(3550308),
                    Localidade::destino()->comMunicipio(3304557),
                )->comDistancia(430),
            )
            ->comDadosCarga(new DadosCarga(1234, 25000, TipoCarga::CargaGeral))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::ContaCorrente,
                cpfCnpjCreditado: self::CNPJ_VALIDO,
                codigoInstituicaoFinanceira: 1,
                numeroAgencia: '1234',
                numeroConta: '56789',
            ))
            ->comIndicadores(new InfIndicadoresOperacionais());
    }

    // ---- 03: Declaração (gera CIOT) ----------------------------------------

    public function test_declarar_operacao_gera_ciot(): void
    {
        $this->transport->programar('DeclaracaoOperacaoTransporte', [
            'CodigoIdentificacaoOperacao' => '000000000123',
            'CodigoVerificador' => '4567',
            'Protocolo' => 'PROT1',
            'Codigo' => '110',
            'Mensagem' => 'Dados inseridos com sucesso!',
        ]);

        $resp = $this->ciot()->declararOperacao($this->declaracaoValida());

        $this->assertTrue($resp->sucesso());
        $this->assertSame('0000000001234567', $resp->ciotComDigito());

        $chamada = $this->transport->ultimaChamada();
        $this->assertSame('POST', $chamada['metodo']);
        $this->assertSame('DeclaracaoOperacaoTransporte', $chamada['servico']);
    }

    public function test_declaracao_preenche_data_declaracao_automaticamente(): void
    {
        $this->ciot()->declararOperacao($this->declaracaoValida());

        $payload = $this->transport->ultimoPayload();
        $this->assertArrayHasKey('DataDeclaracao', $payload);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/',
            $payload['DataDeclaracao'],
        );
        $this->assertSame(Datas::dataHora(Datas::agoraBrasilia()), $payload['DataDeclaracao']);
    }

    public function test_validacao_local_impede_envio_e_lanca_excecao(): void
    {
        $req = $this->declaracaoValida()->comValorFrete(0);

        try {
            $this->ciot()->declararOperacao($req);
            $this->fail('Esperava CiotValidationException.');
        } catch (CiotValidationException $e) {
            $this->assertNotEmpty($e->erros());
        }

        $this->assertSame([], $this->transport->chamadas, 'Não deveria ter chamado a ANTT.');
    }

    public function test_usa_gerador_de_id_quando_id_ausente(): void
    {
        $gerador = new class implements OperationIdGenerator {
            public function gerar(DeclaracaoOperacaoTransporteRequest $request): string
            {
                return '999888777666';
            }
        };

        $req = $this->declaracaoValida();
        $req->idOperacaoTransporte = null;

        $this->ciot($gerador)->declararOperacao($req);

        $this->assertSame('999888777666', $this->transport->ultimoPayload()['IdOperacaoTransporte']);
    }

    public function test_sem_id_e_sem_gerador_lanca_excecao_de_configuracao(): void
    {
        $req = $this->declaracaoValida();
        $req->idOperacaoTransporte = null;

        $this->expectException(CiotConfigurationException::class);
        $this->ciot()->declararOperacao($req);
    }

    // ---- 01 / 02 -----------------------------------------------------------

    public function test_consultar_situacao_transportador(): void
    {
        $this->transport->programar('ConsultarSituacaoTransportador', [
            'RNTRCAtivo' => true,
            'TipoTransportador' => 'ETC',
            'Codigo' => '111',
        ]);

        $resp = $this->ciot()->consultarSituacaoTransportador(
            new ConsultarSituacaoTransportadorRequest(self::CNPJ_VALIDO, self::CPF_VALIDO, self::RNTRC_VALIDO),
        );

        $this->assertTrue($resp->apto());
        $this->assertSame('ConsultarSituacaoTransportador', $this->transport->ultimaChamada()['servico']);
    }

    public function test_consultar_frota_transportador(): void
    {
        $this->transport->programar('ConsultarFrotaTransportador', [
            'Frota' => [['PlacaVeiculo' => 'ABC1234', 'SituacaoVeiculoFrotaTransportador' => true]],
            'Codigo' => '111',
        ]);

        $resp = $this->ciot()->consultarFrotaTransportador(
            new ConsultarFrotaTransportadorRequest(self::CNPJ_VALIDO, self::CPF_VALIDO, self::RNTRC_VALIDO, ['ABC1234']),
        );

        $this->assertTrue($resp->veiculoPertence('ABC1234'));
    }

    // ---- 04 / 05 / 06 ------------------------------------------------------

    public function test_cancelar_operacao(): void
    {
        $this->transport->programar('CancelamentoOperacaoTransporte', [
            'CodigoIdentificacaoOperacao' => '0000000001234567',
            'DataCancelamento' => '2026-06-23T12:00:00Z',
            'Codigo' => '110',
        ]);

        $resp = $this->ciot()->cancelarOperacao(
            new CancelamentoOperacaoTransporteRequest('0000000001234567', 'Cancelado pelo embarcador'),
        );

        $this->assertTrue($resp->sucesso());
        $this->assertSame('2026-06-23T12:00:00Z', $resp->dataCancelamento());
    }

    public function test_retificar_operacao(): void
    {
        $this->transport->programar('RetificacaoOperacaoTransporte', [
            'CodigoIdentificacaoOperacao' => '0000000001234567',
            'DataRetificacao' => '2026-06-23T12:00:00Z',
            'Codigo' => '110',
        ]);

        $resp = $this->ciot()->retificarOperacao(
            (new RetificacaoOperacaoTransporteRequest('0000000001234567'))->comValorFrete(1800),
        );

        $this->assertTrue($resp->sucesso());
        $this->assertSame('1800.00', $this->transport->ultimoPayload()['ValorFrete']);
    }

    public function test_encerrar_operacao(): void
    {
        $this->transport->programar('EncerramentoOperacaoTransporte', [
            'CodigoIdentificacaoOperacao' => '0000000001234567',
            'DataEncerramento' => '2026-06-28T18:00:00Z',
            'Codigo' => '110',
        ]);

        $resp = $this->ciot()->encerrarOperacao(
            (new EncerramentoOperacaoTransporteRequest('0000000001234567'))->comPesoTotalCarga(25000),
        );

        $this->assertTrue($resp->sucesso());
        $this->assertSame('2026-06-28T18:00:00Z', $resp->dataEncerramento());
    }

    // ---- 07 / 08 -----------------------------------------------------------

    public function test_consultar_excecao_via_string(): void
    {
        $this->transport->programar('ConsultarExcecao', [
            'Retorno' => ['CpfCnpjTransportador' => self::CPF_VALIDO, 'Flag' => true],
            'Codigo' => '111',
        ]);

        $resp = $this->ciot()->consultarExcecao(self::CPF_VALIDO);

        $this->assertTrue($resp->naLista());
        $chamada = $this->transport->ultimaChamada();
        $this->assertSame('GET', $chamada['metodo']);
        $this->assertSame(['CPFCNPJTransportador' => self::CPF_VALIDO], $chamada['payload']);
    }

    public function test_consultar_ciot_gerado(): void
    {
        $this->transport->programar('ConsultarCIOTGerado', [
            'CodigoIdentificacaoOperacao' => '0000000001234567',
            'Codigo' => ['111'],
            'Mensagem' => ['Consulta realizada com sucesso!'],
        ]);

        $resp = $this->ciot()->consultarCiotGerado(new ConsultarCiotGeradoRequest('000000000123', 2026));

        $this->assertSame('0000000001234567', $resp->ciotComDigito());
        $this->assertTrue($resp->sucesso());
    }

    // ---- rejeição da ANTT --------------------------------------------------

    public function test_resposta_rejeitada_nao_lanca_por_padrao(): void
    {
        $this->transport->programar('DeclaracaoOperacaoTransporte', [
            'Codigo' => '291',
            'Mensagem' => 'O valor do frete informado é menor do que o valor mínimo de frete estabelecido.',
        ]);

        $resp = $this->ciot()->declararOperacao($this->declaracaoValida());

        $this->assertTrue($resp->rejeitado());
        $this->assertSame('291', $resp->codigo());
    }

    public function test_validacao_pode_ser_desabilitada_por_config(): void
    {
        $ciot = new Ciot($this->transport, ['validar_antes_de_enviar' => false]);

        // valorFrete 0 violaria B120, mas a validação está desativada.
        $ciot->declararOperacao($this->declaracaoValida()->comValorFrete(0));

        $this->assertNotEmpty($this->transport->chamadas);
    }
}
