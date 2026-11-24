# Instalação — paseto/ciot-antt-api

## Requisitos

- PHP **8.2+** com extensões `json` e `openssl`
- Certificado digital **A1 ICP-Brasil** do CNPJ credenciado (mTLS no PEF)
- Credenciamento ANTT em **homologação** antes de `producao`

## Composer

```bash
composer require paseto/ciot-antt-api
```

No repositório fonte:

```bash
composer install
composer test
```

## Configuração (`config.json`)

Copie o modelo na raiz do pacote:

```bash
cp vendor/paseto/ciot-antt-api/config.example.json /caminho/seguro/ciot-config.json
```

Ou, ao desenvolver este repositório:

```bash
cp config.example.json config.json
```

| Campo | Descrição |
|-------|-----------|
| `ambiente` | `homologacao` ou `producao` |
| `path_prefix` | Contexto da URL (padrão `api`) |
| `timeout` / `connect_timeout` | Segundos para leitura e conexão (recomendado ≥ 60 na ANTT) |
| `validar_antes_de_enviar` | Valida regras B1–B122 localmente antes do POST (padrão `true`) |
| `certificado.pfx_path` | Caminho absoluto ao `.pfx` (opcional se injetar `Certificate` no código) |
| `certificado.pfx_senha` | Senha do PFX |
| `certificado.openssl_bin` | Binário OpenSSL (fallback `-legacy` em OpenSSL 3) |

O **IdOperacaoTransporte** (12 dígitos) **não** vai no JSON global: informe em cada declaração com `->comId(...)` ou registre um `OperationIdGenerator` (DLL/executável oficial da ANTT ou integração própria).

### Gerador CIOT v3 (opcional)

O DCS/PEF oficial usa **apenas mTLS**. O bloco `gerador.api_key` é um **atalho HTTP** para quem já possui chave do serviço Gerador v3 — não substitui o credenciamento PEF. Se não usar, omita o bloco `gerador`.

```json
"gerador": {
    "api_key": "sua-chave",
    "token_ttl": 3540
}
```

Com `api_key` válida, `Tools` usa `GeradorCiot` como gerador automático de id na declaração.

## Certificado no código

Recomendado em produção (segredos fora do disco versionado):

```php
use NFePHP\Common\Certificate;
use Paseto\CiotAntt\Tools;

$configJson = file_get_contents('/caminho/seguro/ciot-config.json');
$cert = Certificate::readPfx(
    (string) file_get_contents(getenv('CIOT_PFX_PATH')),
    (string) getenv('CIOT_PFX_SENHA'),
);

$tools = Tools::create($configJson, $cert);
```

## Fluxo para gerar o CIOT

1. Credenciamento e certificado válidos na ANTT.
2. (Opcional) `consultarSituacaoTransportador` / `consultarFrotaTransportador`.
3. Gerar **IdOperacaoTransporte** com a ferramenta oficial ([documentos técnicos](https://www.gov.br/antt/pt-br/assuntos/cargas/ciot-para-todos-1/documentos-tecnicos)).
4. Montar `DeclaracaoOperacaoTransporteRequest` e chamar `$tools->declararOperacao()`.
5. Em sucesso (**código 110**), usar o CIOT de 16 dígitos (`ciotComDigito()`) para cancelar, retificar ou encerrar.

> **DataDeclaracao (regra B11):** se omitir o campo, o SDK preenche com o horário de **Brasília**. Declare logo após gerar o id (janela de tolerância de ~15 min).

## Publicação no Packagist

1. Repositório Git público (GitHub, GitLab, etc.).
2. Tag semver (`v1.0.0`) apontando para um commit estável com `composer test` verde.
3. Cadastro em [packagist.org](https://packagist.org/) com URL do repositório.
4. Ative webhook de push para atualizar o Pacote automaticamente.

Ajuste `name`, `support.source` e `support.issues` no `composer.json` para o repositório real antes do primeiro tag.

## Links oficiais

- [CIOT Para Todos](https://www.gov.br/antt/pt-br/assuntos/cargas/ciot-para-todos-1)
- [Documentos técnicos / DCS PEF](https://www.gov.br/antt/pt-br/assuntos/cargas/ciot-para-todos-1/documentos-tecnicos)
- [Perguntas frequentes](https://www.gov.br/antt/pt-br/assuntos/cargas/perguntas-frequentes-ciot)

Guia de uso detalhado: [USAGE.md](USAGE.md).

### Testar com scripts

Na raiz do repositório, copie `examples/config.example.json` → `examples/config.json` e execute os scripts numerados em [examples/README.md](../examples/README.md) (CLI ou navegador).