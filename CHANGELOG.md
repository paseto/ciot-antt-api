# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/).

## [1.0.0] - 2026-11-24

### Adicionado

- SDK PEF/ANTT com `Tools`, validação local DCS, transporte Guzzle mTLS e suíte PHPUnit.
- Preenchimento automático de `DataDeclaracao` no fuso `America/Sao_Paulo` (regra B11).
- Validação local B11 (tolerância de data/hora da declaração).
- Normalização de código COMPE (3 dígitos) em `InfPagamento`.
- Documentação: `README.md`, `docs/INSTALL.md`, `docs/USAGE.md`, `config.example.json`.

### Removido

- Scripts de demonstração em `examples/` (o pacote é biblioteca; integre via Composer na sua aplicação).

[1.0.0]: https://github.com/paseto/ciot-antt-api/releases/tag/v1.0.0