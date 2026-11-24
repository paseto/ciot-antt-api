# CIOT ANTT API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paseto/ciot-antt-api.svg?style=flat-square)](https://packagist.org/packages/paseto/ciot-antt-api)
[![Total Downloads](https://img.shields.io/packagist/dt/paseto/ciot-antt-api.svg?style=flat-square)](https://packagist.org/packages/paseto/ciot-antt-api)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

SDK **PHP framework-agnostic** para emissão e gestão de **CIOT** (Código Identificador da Operação de Transporte) no web service **PEF da ANTT**, alinhado ao **DCS PEF** ([documentação oficial](https://www.gov.br/antt/pt-br/assuntos/cargas/ciot-para-todos-1/documentos-tecnicos)).

Configure com **JSON**, autentique com **certificado A1** (PFX) e chame os serviços pela classe **`Tools`**.

```bash
composer require paseto/ciot-antt-api
```

## Requisitos

- PHP **8.2+** (`json`, `openssl`)
- Certificado **A1 ICP-Brasil** com mTLS (obrigatório no PEF)
- **IdOperacaoTransporte** de 12 dígitos (ferramenta oficial ANTT) para declarar

## Instalação

```bash
cp vendor/paseto/ciot-antt-api/config.example.json ciot-config.json
```

Detalhes: [docs/INSTALL.md](docs/INSTALL.md).

## Uso rápido

```php
use NFePHP\Common\Certificate;
use Paseto\CiotAntt\Tools;
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
// … builders de Veiculo, OrigemDestino, DadosCarga, InfPagamento — ver USAGE.md

$config = file_get_contents('/caminho/ciot-config.json');
$cert = Certificate::readPfx(
    (string) file_get_contents('/caminho/certificado.pfx'),
    'senha-do-pfx',
);

$tools = Tools::create($config, $cert);

$declaracao = DeclaracaoOperacaoTransporteRequest::cargaLotacao(
    cpfCnpjContratado: '…',
    rntrcContratado: '…',
    cpfCnpjContratante: '…',
)
    ->comId('000000000123') // 12 dígitos (Gerador oficial ANTT)
    ->comValorFrete(1500)
    // demais campos obrigatórios…
;

$resposta = $tools->declararOperacao($declaracao);

if ($resposta->sucesso()) {
    echo $resposta->ciotComDigito(); // 16 caracteres (CIOT + DV)
}
```

Após a declaração, inspecione `$tools->lastRequest`, `lastResponse` e `lastStatus` se precisar auditar o HTTP.

Documentação completa dos 8 serviços, componentes e erros: [docs/USAGE.md](docs/USAGE.md).

## Serviços PEF

| # | Serviço | Método em `Tools` |
|---|---------|-------------------|
| 01 | ConsultarSituacaoTransportador | `consultarSituacaoTransportador()` |
| 02 | ConsultarFrotaTransportador | `consultarFrotaTransportador()` |
| 03 | DeclaracaoOperacaoTransporte | `declararOperacao()` |
| 04 | CancelamentoOperacaoTransporte | `cancelarOperacao()` |
| 05 | RetificacaoOperacaoTransporte | `retificarOperacao()` |
| 06 | EncerramentoOperacaoTransporte | `encerrarOperacao()` |
| 07 | ConsultarExcecao | `consultarExcecao()` |
| 08 | ConsultarCIOTGerado | `consultarCiotGerado()` |

## Scripts de teste

Com credenciais em `examples/config.json`:

```bash
php examples/01_consultar_situacao_transportador.php
php examples/03_declarar_operacao_lotacao.php /caminho/cert.pfx senha SEU_ID_12_DIGITOS
```

Lista completa: [examples/README.md](examples/README.md).

## Desenvolvimento e publicação

```bash
composer install
composer test
```

Checklist para publicar no Packagist: [docs/INSTALL.md#publicação-no-packagist](docs/INSTALL.md#publicação-no-packagist).

## Licença

MIT
