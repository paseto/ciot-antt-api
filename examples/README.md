# Scripts de teste (PEF / ANTT)

Scripts PHP para exercitar os **8 serviços** expostos em `Paseto\CiotAntt\Tools`.

## Preparação

```bash
cp examples/config.example.json examples/config.json
# Edite certificado, bloco empresa e (para declarar) id_operacao_transporte
composer install
```

Gere o **IdOperacaoTransporte** (12 dígitos) com o Gerador oficial ANTT antes do serviço **03**.

## Uso em CLI

Certificado no JSON ou na linha de comando (`argv[1]` = PFX, `argv[2]` = senha):

```bash
php examples/01_consultar_situacao_transportador.php
php examples/01_consultar_situacao_transportador.php /caminho/cert.pfx senha

php examples/03_declarar_operacao_lotacao.php /caminho/cert.pfx senha 5600006363594075
```

Argumentos extras (a partir do 3º, após cert/senha) variam por script — veja tabela abaixo.

## Uso via HTTP

Com Apache/nginx apontando para o projeto, acesse o `.php` e use query string. O certificado vem **somente** de `examples/config.json` (não há `$argv`).

Exemplo: `.../examples/07_consultar_excecao.php?documento=00000000000000`

## Scripts

| Arquivo | Método `Tools` | Parâmetros CLI (após pfx/senha) / query |
|---------|----------------|----------------------------------------|
| `01_consultar_situacao_transportador.php` | `consultarSituacaoTransportador` | `interessado`, `transportador`, `rntrc` |
| `02_consultar_frota_transportador.php` | `consultarFrotaTransportador` | idem + `placas` (vírgula) |
| `03_declarar_operacao_lotacao.php` | `declararOperacao` | `id_operacao` (+ HTTP: `valor_frete`, `banco`, `natureza_carga`, …) |
| `04_cancelar_operacao.php` | `cancelarOperacao` | `ciot` (16), `motivo` |
| `05_retificar_operacao.php` | `retificarOperacao` | `ciot` (16) — não vale para carga lotação (B121) |
| `06_encerrar_operacao.php` | `encerrarOperacao` | `ciot` (16) |
| `07_consultar_excecao.php` | `consultarExcecao` | `documento` |
| `08_consultar_ciot_gerado.php` | `consultarCiotGerado` | `id_operacao` (12), `ano` |

Campos fixos da operação vêm do bloco `empresa` em `config.json`. Após declarar com sucesso, grave `ciot_16` no JSON para testar cancelar/encerrar/consulta.

## Saída

JSON com `sucesso`, `codigo`, `mensagem`, corpo `antt` e `_trace` (`http_status`, `servico`). Erros de validação local retornam HTTP 422 e lista `erros` (regras B*).

Documentação do SDK: [../docs/USAGE.md](../docs/USAGE.md).