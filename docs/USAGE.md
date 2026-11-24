# Guia de uso — paseto/ciot-antt-api

Guia completo para emitir e gerenciar **CIOT** via web service **PEF da ANTT**
usando este SDK. Os exemplos são copy-paste e cobrem os **8 serviços**,
configuração, certificado, regras de negócio e tratamento de erros.

> **SDK framework-agnostic:** a entrada é sempre `Paseto\CiotAntt\Tools::create($configJson, $certificate)`.
> Configuração: [INSTALL.md](INSTALL.md) · visão geral: [README.md](../README.md).

Nos exemplos das seções **6–16**, `$tools` já foi instanciado conforme a [seção 5](#5-como-chamar).

## Sumário

1. [Conceitos e fluxo](#1-conceitos-e-fluxo)
2. [Instalação e configuração](#2-instalação-e-configuração)
3. [Certificado digital (mTLS)](#3-certificado-digital-mtls)
4. [Geração do IdOperacaoTransporte](#4-geração-do-idoperacaotransporte)
5. [Como chamar (`Tools`)](#5-como-chamar)
6. [Serviço 01 — ConsultarSituacaoTransportador](#6-serviço-01--consultarsituacaotransportador)
7. [Serviço 02 — ConsultarFrotaTransportador](#7-serviço-02--consultarfrotatransportador)
8. [Serviço 03 — DeclaracaoOperacaoTransporte (gera o CIOT)](#8-serviço-03--declaracaooperacaotransporte-gera-o-ciot)
9. [Serviço 04 — CancelamentoOperacaoTransporte](#9-serviço-04--cancelamentooperacaotransporte)
10. [Serviço 05 — RetificacaoOperacaoTransporte](#10-serviço-05--retificacaooperacaotransporte)
11. [Serviço 06 — EncerramentoOperacaoTransporte](#11-serviço-06--encerramentooperacaotransporte)
12. [Serviço 07 — ConsultarExcecao](#12-serviço-07--consultarexcecao)
13. [Serviço 08 — ConsultarCIOTGerado](#13-serviço-08--consultarciotgerado)
14. [Componentes (Veiculo, OrigemDestino, DadosCarga, InfPagamento…)](#14-componentes)
15. [Enums](#15-enums)
16. [Tratamento de erros](#16-tratamento-de-erros)
17. [Regras de negócio validadas localmente](#17-regras-de-negócio-validadas-localmente)
18. [Ambiguidades do manual e como ajustar](#18-ambiguidades-do-manual)

---

## 1. Conceitos e fluxo

- **CIOT**: Código Identificador da Operação de Transporte, gerado pela ANTT na
  **declaração** da operação. A resposta traz o CIOT (12) + dígito verificador (4).
  O identificador **com DV (16)** é o que se usa para cancelar/retificar/encerrar.
- **Tipos de operação** (`TipoOperacao`): `1` Carga Lotação, `2` Carga Fracionada,
  `3` TAC-Agregado. As obrigatoriedades dos campos mudam conforme o tipo.
- **Fluxo típico**:

```
(opcional) ConsultarSituacaoTransportador  -> o contratado está apto? (RNTRC ativo)
(opcional) ConsultarFrotaTransportador     -> as placas pertencem a ele?
DeclaracaoOperacaoTransporte               -> GERA o CIOT  ⭐
   ├─ (se preciso) RetificacaoOperacaoTransporte
   ├─ (antes de iniciar) CancelamentoOperacaoTransporte
   └─ (ao concluir) EncerramentoOperacaoTransporte
ConsultarCIOTGerado / ConsultarExcecao     -> consultas auxiliares
```

---

## 2. Instalação e configuração

```bash
composer require paseto/ciot-antt-api
cp vendor/paseto/ciot-antt-api/config.example.json /caminho/seguro/ciot-config.json
```

Edite o JSON (veja também [`INSTALL.md`](INSTALL.md)):

```json
{
    "ambiente": "homologacao",
    "path_prefix": "api",
    "verificar_ssl": true,
    "timeout": 120,
    "connect_timeout": 60,
    "validar_antes_de_enviar": true,
    "certificado": {
        "pfx_path": "/var/secrets/certificado.pfx",
        "pfx_senha": "senha-do-certificado",
        "openssl_bin": "openssl"
    }
}
```

Em aplicações, prefira carregar o certificado por variável de ambiente ou cofre de segredos e passar `Certificate::readPfx()` para `Tools::create()` em vez de deixar senha em arquivo.

---

## 3. Certificado digital (mTLS)

A comunicação usa **TLS com autenticação mútua**: o servidor da ANTT exige um
certificado **ICP-Brasil A1 ou A3** do CNPJ titular. Em CI/CD, use **A1** em
arquivo `.pfx`/`.p12`.

```php
use NFePHP\Common\Certificate;

$certificate = Certificate::readPfx(
    (string) file_get_contents('/caminho/seguro/certificado.pfx'),
    'senha-do-pfx',
);
```

O pacote lê o `.pfx` com `ext-openssl` e extrai o par certificado+chave para
arquivos PEM temporários com permissão `0600` (necessário ao cURL). Você não
precisa converter nada manualmente.

> **Certificados A1 ICP-Brasil e OpenSSL 3.** Muitos `.pfx` A1 usam algoritmos
> legados (RC2-40/3DES) que o OpenSSL 3 (PHP 8.1+) não habilita por padrão —
> `openssl_pkcs12_read()` falharia mesmo com a senha correta. O pacote detecta
> isso e recorre automaticamente ao binário `openssl` (`-legacy`) como fallback.
> Garanta que o `openssl` esteja no PATH (ou defina `certificado.openssl_bin` no JSON), ou
> reexporte o `.pfx` com cifra moderna.

**Boas práticas:** trate o `.pfx` e a senha como segredo; não versione (`*.pfx` está no `.gitignore` do pacote).

---

## 4. Geração do IdOperacaoTransporte

O `IdOperacaoTransporte` (12 dígitos) é gerado pela **DLL/executável oficial da
ANTT** e o algoritmo **não acompanha** este pacote. Há duas formas:

**A) Informar manualmente** (você gera por fora e passa na requisição):

```php
$declaracao->comId('000000000123');
```

**B) Registrar um gerador** que implemente o contrato e faça a ponte com a
ferramenta da ANTT:

```php
namespace App\Ciot;

use Paseto\CiotAntt\Contracts\OperationIdGenerator;
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;

final class MeuGeradorDeId implements OperationIdGenerator
{
    public function gerar(DeclaracaoOperacaoTransporteRequest $request): string
    {
        // Chame aqui a DLL/serviço da ANTT e retorne os 12 dígitos.
        return $this->antt->gerarId(/* ...dados do $request... */);
    }
}
```

```json
"operation_id_generator": "App\\Ciot\\MeuGeradorDeId"
```

Registre a classe via injeção em `new Tools($config, $cert, null, $meuGerador)` ou implemente o binding na sua aplicação.

Com o gerador registrado, você **não** precisa chamar `->comId(...)`: o pacote
gera o id automaticamente na hora de declarar. Sem id e sem gerador, é lançada
`CiotConfigurationException`.

**C) Gerador CIOT v3 HTTP (opcional)** — atalho com `gerador.api_key` no JSON. **Não**
faz parte do credenciamento PEF oficial; use apenas se sua operação já possui chave:

```json
"gerador": {
    "api_key": "sua-chave-aqui",
    "token_ttl": 3540
}
```

Com `api_key` válida e sem `operation_id_generator`, `Tools` registra `GeradorCiot`
automaticamente na declaração.

Uso direto após criar `Tools`:

```php
$gerador = $tools->gerador();
if ($gerador !== null) {
    $numero = $gerador->gerarCiot('12345678901');
}
```

Falhas HTTP do gerador: `CiotAuthenticationException`, `CiotUnauthorizedException`, `CiotApiException`.

> **Prioridade:** (1) `OperationIdGenerator` passado ao construtor de `Tools`;
> (2) `GeradorCiot` se `gerador.api_key` estiver definida; (3) nenhum — use `->comId(...)`.

---

## 5. Como chamar

Forma recomendada — **`Tools`**:

```php
use NFePHP\Common\Certificate;
use Paseto\CiotAntt\Tools;

$configJson = file_get_contents('/caminho/ciot-config.json');
$certificate = Certificate::readPfx(
    (string) file_get_contents('/caminho/certificado.pfx'),
    'senha-do-pfx',
);

$tools = Tools::create($configJson, $certificate);

$resposta = $tools->consultarExcecao('12345678901');
```

Encapsule `$tools` no seu serviço de domínio (frete, TMS, ERP) e injete em testes com `FakePefTransport` (ver suíte `tests/`).

Avançado — montar `Ciot` + transporte manualmente (mesmos contratos de `tests/Feature/CiotClientTest.php`).

---

## 6. Serviço 01 — ConsultarSituacaoTransportador

Valida o transportador no RNTRC e retorna a categoria (TAC/ETC/CTC) e se ele se
equipara ao TAC.

```php
use Paseto\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;

$resposta = $tools->consultarSituacaoTransportador(
    new ConsultarSituacaoTransportadorRequest(
        cpfCnpjInteressado: '11444777000161', // quem consulta (sua empresa)
        cpfCnpjTransportador: '12345678901',  // o transportador consultado
        rntrcTransportador: '12345678',       // 8 ou 9 dígitos (normaliza p/ 9 — regra B60)
    ),
);

if ($resposta->sucesso()) {
    $resposta->apto();              // bool: RNTRC ativo
    $resposta->nomeRazaoSocial();   // string
    $resposta->tipoTransportador(); // TipoTransportador::TAC | ETC | CTC | null
    $resposta->equiparadoTac();     // bool
}
```

## 7. Serviço 02 — ConsultarFrotaTransportador

Verifica, por placa, se os veículos pertencem ao transportador.

```php
use Paseto\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;

$resposta = $tools->consultarFrotaTransportador(
    new ConsultarFrotaTransportadorRequest(
        cpfCnpjInteressado: '11444777000161',
        cpfCnpjTransportador: '12345678901',
        rntrcTransportador: '123456789',
        placas: ['ABC1234', 'XYZ9876'],
    ),
);

foreach ($resposta->frota() as $veiculo) {
    // ['placa' => 'ABC1234', 'pertence' => true]
}

$resposta->veiculoPertence('ABC1234'); // bool
```

## 8. Serviço 03 — DeclaracaoOperacaoTransporte (gera o CIOT)

⭐ Serviço principal. As obrigatoriedades mudam por `TipoOperacao`. Há um
*factory* por tipo: `cargaLotacao()`, `cargaFracionada()`, `tacAgregado()`.

### 8.1 Carga Lotação (TipoOperacao = 1)

```php
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\Components\{Veiculo, OrigemDestino, Localidade, DadosCarga, InfPagamento, InfIndicadoresOperacionais};
use Paseto\CiotAntt\Enums\{TipoCarga, TipoPagamento};

$declaracao = DeclaracaoOperacaoTransporteRequest::cargaLotacao(
        cpfCnpjContratado: '11222333000181',   // transportador (TAC/ETC/CTC)
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',   // quem paga o frete
    )
    ->comId('000000000123')                     // dispensável se houver OperationIdGenerator
    ->comValorFrete(1500.00)
    ->comDestinatario('19131243000197')         // obrigatório p/ carga lotação/fracionada
    ->comRntrcContratante('987654321')          // opcional
    // opcional: se omitido, preenche com horário de Brasília (regra B11)
    ->comViagem(inicio: '2026-06-24', fim: '2026-06-28')
    ->comVeiculos(
        // exatamente UM automotor; demais são implementos (regras B83/B84)
        Veiculo::automotor(placa: 'ABC1234', numeroEixos: 3, rntrc: '123456789', cavaloTrator: true),
        Veiculo::implemento(placa: 'XYZ9876', numeroEixos: 2),
    )
    ->comOrigemDestino(
        OrigemDestino::entre(
            Localidade::origem()->comMunicipio(3550308),   // São Paulo (IBGE)
            Localidade::destino()->comMunicipio(3304557),  // Rio de Janeiro (IBGE)
        )->comDistancia(430),                              // km (> 0)
    )
    ->comDadosCarga(new DadosCarga(
        codigoNaturezaCarga: 1234,
        pesoCarga: 25000.0,                 // kg (> 0)
        codigoTipoCarga: TipoCarga::CargaGeral,
    ))
    ->comPagamentos(new InfPagamento(
        tipoPagamento: TipoPagamento::ContaCorrente,
        cpfCnpjCreditado: '11222333000181',
        codigoInstituicaoFinanceira: 237,
        numeroAgencia: '1234',
        numeroConta: '56789-0',
    ))
    ->comIndicadores(new InfIndicadoresOperacionais(  // obrigatório p/ carga lotação
        indAltoDesempenho: false,
        indRetornoVazio: false,
        composicaoVeicular: true,
    ));

$resposta = $tools->declararOperacao($declaracao);

if ($resposta->sucesso()) {
    $ciot        = $resposta->ciot();          // 12 dígitos
    $dv          = $resposta->codigoVerificador(); // 4 dígitos
    $ciotComDv   = $resposta->ciotComDigito(); // 16 — guarde este p/ cancelar/encerrar
    $protocolo   = $resposta->protocolo();

    if ($resposta->temAviso()) {
        // Apresentação/impressão do aviso é OBRIGATÓRIA (manual, item 6)
        $aviso = $resposta->avisoTransportador();
    }
} else {
    // rejeição da ANTT (ex.: piso mínimo, vínculo de veículo)
    // tratar rejeição (ex.: log, fila, retorno ao usuário)
}
```

### 8.2 Carga Fracionada (TipoOperacao = 2)

Igual ao lotação, porém **com contratantes da carga fracionada** e **sem**
`InfIndicadoresOperacionais`:

```php
$declaracao = DeclaracaoOperacaoTransporteRequest::cargaFracionada(
        cpfCnpjContratado: '11222333000181',
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',
    )
    ->comId('000000000124')
    ->comValorFrete(2200.00)
    ->comDestinatario('19131243000197')
    ->comViagem('2026-06-24', '2026-06-28')
    ->comVeiculos(Veiculo::automotor('ABC1234', 2, '123456789'))
    ->comOrigemDestino(
        OrigemDestino::entre(
            Localidade::origem()->comCep('01001000'),
            Localidade::destino()->comCep('20040002'),
        )->comDistancia(430),
    )
    ->comDadosCarga(new DadosCarga(
        codigoNaturezaCarga: 1234,
        pesoCarga: 18000.0,
        codigoTipoCarga: TipoCarga::CargaGeral,
        contratantesCargaFracionada: ['04252011000110'], // ≠ do contratante (B119)
    ));

$resposta = $tools->declararOperacao($declaracao);
```

### 8.3 TAC-Agregado (TipoOperacao = 3)

Não informa destinatário, origem/destino, dados de carga nem indicadores na
**declaração** (esses vão no **encerramento**):

```php
$declaracao = DeclaracaoOperacaoTransporteRequest::tacAgregado(
        cpfCnpjContratado: '12345678901',  // deve ser TAC
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',
    )
    ->comId('000000000125')
    ->comValorFrete(3000.00)
    // TAC-Agregado NÃO informa data de início (regra B56): o servidor assume
    // dataInicioViagem = dataDeclaracao. Informe apenas o fim (≤ 30 dias — B30).
    ->comDataFimViagem('2026-07-10')
    ->comVeiculos(Veiculo::automotor('ABC1234', 3, '123456789'))
    ->comPagamentos(new InfPagamento(
        tipoPagamento: TipoPagamento::Outros,
        cpfCnpjCreditado: '12345678901',
    ));

$resposta = $tools->declararOperacao($declaracao);
```

### 8.4 Declaração em contingência

```php
$declaracao->emContingencia('Indisponibilidade do web service da ANTT em 23/06');
```

### 8.5 Pagamento a prazo (parcelado)

```php
use Paseto\CiotAntt\Enums\{IndPagamento, TipoPagamento};
use Paseto\CiotAntt\Requests\Components\{InfPagamento, Parcela};

$pagamento = (new InfPagamento(
        tipoPagamento: TipoPagamento::Pix,
        indPagamento: IndPagamento::APrazo,
        cpfCnpjCreditado: '11222333000181',
        chavePix: '11222333000181',
        identificadorPix: 'E1234567890',
    ))
    ->comParcelas(
        new Parcela(numeroParcela: 1, dataVencimento: '2026-07-10', valorParcela: 750.00),
        new Parcela(numeroParcela: 2, dataVencimento: '2026-08-10', valorParcela: 750.00),
    );

$declaracao->comPagamentos($pagamento);
```

## 9. Serviço 04 — CancelamentoOperacaoTransporte

Cancela uma operação ainda **não realizada** (prazo: até 24h após o início da
viagem e desde que não consultada pela fiscalização).

```php
use Paseto\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;

$resposta = $tools->cancelarOperacao(
    new CancelamentoOperacaoTransporteRequest(
        codigoIdentificacaoOperacao: '0000000001234567', // CIOT + DV (16)
        motivoCancelamento: 'Operação cancelada pelo embarcador.',
    ),
);

$resposta->sucesso();           // bool
$resposta->dataCancelamento();  // string ISO
```

## 10. Serviço 05 — RetificacaoOperacaoTransporte

Retifica uma operação no status "Declarado". **Carga lotação (tipo 1) não pode
ser retificada** (regra B121).

```php
use Paseto\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\Components\DadosCarga;

$resposta = $tools->retificarOperacao(
    (new RetificacaoOperacaoTransporteRequest('0000000001234567'))
        ->comValorFrete(1800.00)
        ->comDadosCarga(new DadosCarga(codigoNaturezaCarga: 5555, pesoCarga: 12000.0)),
);

$resposta->dataRetificacao(); // string ISO
```

Para **TAC-Agregado** também informe `comDataFimViagem()` e `comOrigemDestino()`.

## 11. Serviço 06 — EncerramentoOperacaoTransporte

Declara a conclusão da operação.

**Carga lotação/fracionada (padrão):** informe o peso total da carga.

```php
use Paseto\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;

$resposta = $tools->encerrarOperacao(
    (new EncerramentoOperacaoTransporteRequest('0000000001234567'))
        ->comPesoTotalCarga(25000.0),
);
```

**TAC-Agregado:** informe os dados de viagem (origem/destino, distância e
quantidade de viagens) — encerrar até 10 dias após o início (regra B116):

```php
use Paseto\CiotAntt\Requests\Components\{OrigemDestino, Localidade};

$resposta = $tools->encerrarOperacao(
    (new EncerramentoOperacaoTransporteRequest('0000000009999999'))
        ->comOrigemDestino(
            OrigemDestino::entre(
                Localidade::origem()->comMunicipio(3550308),
                Localidade::destino()->comMunicipio(3304557),
            )->comDistancia(430)->comQtdViagens(4),
        )
        ->comPesoTotalCarga(50000.0),
);

$resposta->dataEncerramento();
```

## 12. Serviço 07 — ConsultarExcecao

Verifica se o transportador está na lista de exceções à Resolução 5862 (GET).

```php

$resposta = $tools->consultarExcecao('12345678901'); // aceita string direto

$resposta->naLista();                 // bool
$resposta->cpfCnpjTransportador();    // string
```

## 13. Serviço 08 — ConsultarCIOTGerado

Retorna o CIOT com dígito verificador a partir do id e do ano de declaração.

```php
use Paseto\CiotAntt\Requests\ConsultarCiotGeradoRequest;

$resposta = $tools->consultarCiotGerado(
    new ConsultarCiotGeradoRequest(
        codigoIdentificacaoOperacao: '000000000123', // 12 dígitos
        anoDeclaracao: 2026,
    ),
);

$resposta->ciotComDigito(); // 16
```

---

## 14. Componentes

| Classe | Para que serve | Construção |
|--------|----------------|-----------|
| `Veiculo` | Veículo da operação | `Veiculo::automotor(placa, numeroEixos, rntrc?, cavaloTrator?)` / `Veiculo::implemento(placa, numeroEixos, rntrc?)` |
| `Localidade` | Origem/destino de um par | `Localidade::origem()` / `::destino()` + `->comMunicipio(ibge)` \| `->comCep('01001000')` \| `->comCoordenadas(lat, long)` |
| `OrigemDestino` | Par origem→destino | `OrigemDestino::entre($origem, $destino)->comDistancia(km)->comQtdViagens(n)` |
| `DadosCarga` | Carga | `new DadosCarga(codigoNaturezaCarga, pesoCarga, codigoTipoCarga, contratantesCargaFracionada?)` |
| `InfPagamento` | Forma de pagamento | `new InfPagamento(tipoPagamento, indPagamento?, cpfCnpjCreditado?, codigoInstituicaoFinanceira?, numeroAgencia?, numeroConta?, chavePix?, identificadorPix?, codigoPagamento?, parcelas?)` |
| `Parcela` | Parcela (a prazo) | `new Parcela(numeroParcela, dataVencimento, valorParcela)` |
| `InfIndicadoresOperacionais` | Indicadores (carga lotação) | `new InfIndicadoresOperacionais(indAltoDesempenho?, indRetornoVazio?, composicaoVeicular?)` |

> **Localização** (regras B109/B110/B111): para cada par OD informe **o mesmo
> tipo** na origem e no destino (município, CEP **ou** coordenadas). Coordenadas
> exigem latitude **e** longitude juntas.

## 15. Enums

```php
use Paseto\CiotAntt\Enums\{TipoOperacao, TipoPagamento, IndPagamento, TipoCarga, TipoTransportador, Ambiente};

TipoOperacao::CargaLotacao;     // 1
TipoOperacao::CargaFracionada;  // 2
TipoOperacao::TacAgregado;      // 3

TipoPagamento::CartaoPrePago;   // 1 (IP/IF)
TipoPagamento::ContaCorrente;   // 2
TipoPagamento::ContaPoupanca;   // 3
TipoPagamento::ContaPagamento;  // 4
TipoPagamento::Outros;          // 5
TipoPagamento::Pix;             // 6

IndPagamento::AVista;  // 0
IndPagamento::APrazo;  // 1

TipoCarga::GranelSolido /* 1 */, ..., TipoCarga::CargaGranelPressurizada /* 12 */;

TipoTransportador::TAC | ETC | CTC;
```

## 16. Tratamento de erros

O pacote distingue **três** situações:

| Situação | Como aparece |
|----------|--------------|
| Dados inválidos (validação local) | lança `CiotValidationException` **antes** do envio |
| Falha de rede/TLS/HTTP/JSON | lança `CiotConnectionException` |
| Configuração incompleta (certificado, id, ambiente, **API key**) | lança `CiotConfigurationException` |
| ANTT recusou por regra de negócio | **não lança**: `$resposta->rejeitado()` / `codigo()` / `mensagem()` |

No **Gerador CIOT v3** (token + API key), as falhas HTTP viram exceções próprias
que carregam `statusHttp`, `corpoResposta` e `endpoint` (todas estendem `CiotException`):

| Situação | Exceção |
|----------|---------|
| Falha na autenticação (`POST /token` != 2xx ou sem `token`) | `CiotAuthenticationException` |
| `POST /gerar` retornou HTTP 401 (token inválido/expirado) | `CiotUnauthorizedException` |
| `POST /gerar` retornou outro status != 2xx ou corpo inesperado | `CiotApiException` |

```php
use Paseto\CiotAntt\Exceptions\{CiotValidationException, CiotConnectionException, CiotConfigurationException};

try {
    $resposta = $tools->declararOperacao($declaracao);

    if ($resposta->rejeitado()) {
        throw new \RuntimeException("ANTT [{$resposta->codigo()}]: {$resposta->mensagem()}");
    }

    $ciot = $resposta->ciotComDigito();
} catch (CiotValidationException $e) {
  // $e->erros() => list<string> (ex.: "B11: DataDeclaracao…")
} catch (CiotConnectionException $e) {
  // rede/TLS/timeout
} catch (CiotConfigurationException $e) {
  // certificado, id ou ambiente
}
```

> Prefere tratar rejeição como exceção? Use
> `$resposta->lancarSeRejeitado()` para lançar `CiotRejectionException` quando o
> código não for 110/111.

## 17. Regras de negócio validadas localmente

Quando `validar_antes_de_enviar` é `true` no JSON (padrão), o pacote valida localmente — antes de
enviar — as regras verificáveis sem acesso aos dados da ANTT, lançando
`CiotValidationException` com a regra citada. As principais:

- **B1/B4**: obrigatoriedade e formato (CPF/CNPJ com DV, RNTRC B60, placa BR/Mercosul, datas).
- **B5**: placas duplicadas. **B49**: máximo de 5 veículos.
- **B83/B84**: exatamente um veículo automotor. **B101**: eixos (automotor 2–4, implemento 1–4). **B117**: cavalo-trator exige implemento.
- **B103/B104**: justificativa de contingência. **B6/B11/B12/B13/B21/B22/B30**: coerência/janela das datas (`DataDeclaracao` em fuso de Brasília).
- **B56**: TAC-Agregado não informa data de início. **B66**: destinatário (carga). **B62**: campos proibidos no TAC-Agregado. **B69/B18**: natureza/peso da carga.
- **B82/B109/B110/B111**: distância e localização do par origem/destino.
- **B67/B119**: contratantes da carga fracionada. **B92/B99/B105/B106**: campos por tipo de pagamento e parcelamento.
- **B120**: valor do frete > 0.

**Validadas apenas pela ANTT** (precisam de dados do servidor): piso mínimo de
frete (**B80**), vínculo/exclusividade de veículos (**B15/B20/B73/B107**),
bloqueios/penalidades (**B114**), equiparação TAC (**B100**), certificado de IP
(**B118**), duplicidade de CIOT (**B17/B24/B25/B31/B32/B33**), prazos de
cancelamento/retificação/encerramento (**B35/B36/B39/B116**), etc. Por isso,
**sempre trate `$resposta->rejeitado()`** mesmo passando pela validação local.

## 18. Ambiguidades do manual

O manual DCS PEF v1.1 traz **grafias divergentes** entre as tabelas de leiaute e
os exemplos JSON. O pacote adota a grafia do **exemplo JSON** (o que de fato
trafega) e centraliza os casos ambíguos em `src/Support/Wire.php` — corrigir, se
a homologação exigir, é mudança de **uma linha**:

| Constante | Valor adotado | Alternativa na tabela |
|-----------|---------------|------------------------|
| `Wire::CONTRATANTES_CARGA_FRAC` | `ContratantesCargFrac` | `ContratantesCargaFrac` |
| `Wire::VEICULO_RNTRC` | `RNTRC` | `RNTRCVeiculo` |
| `Wire::ENCERRAMENTO_PESO_TOTAL` | `PesoTotalCarga` | `PesoCarga` |

Da mesma forma, o **contexto da URL** (`api`) e o **host** devem ser confirmados
com a ANTT — ajuste `path_prefix` ou `urls` no JSON sem alterar o SDK.

As respostas são lidas de forma **tolerante** (ignoram caixa e aceitam apelidos
como `CodigoIdentificacaoOperacao`/`IdOperacaoTransporte`), então variações de
grafia na resposta não quebram o parsing.
