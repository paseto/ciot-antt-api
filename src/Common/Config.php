<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Common;

use Paseto\CiotAntt\Enums\Ambiente;
use Paseto\CiotAntt\Exceptions\CiotConfigurationException;

/**
 * Configuração do SDK (JSON, array ou objeto; sem dependência de .env).
 */
final class Config
{
    /**
     * @param  array<string, string>  $urlsPef
     * @param  array<string, mixed>  $certificado
     * @param  array<string, string>  $urlsGerador
     * @param  array<string, mixed>  $emissora
     */
    public function __construct(
        private readonly Ambiente $ambiente = Ambiente::Homologacao,
        private readonly array $urlsPef = [],
        private readonly string $pathPrefix = 'api',
        private readonly array $certificado = [],
        private readonly bool $verificarSsl = true,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 60,
        private readonly bool $validarAntesDeEnviar = true,
        private readonly ?string $operationIdGeneratorClass = null,
        private readonly array $emissora = [],
        private readonly ?string $geradorApiKey = null,
        private readonly array $urlsGerador = [],
        private readonly int $geradorTokenTtl = 3540,
        private readonly ?int $geradorTimeout = null,
        private readonly ?int $geradorConnectTimeout = null,
        private readonly ?bool $geradorVerificarSsl = null,
    ) {
    }

    /**
     * @param  array<string, mixed>|object  $data
     */
    public static function from(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        if (! is_array($data)) {
            throw new CiotConfigurationException('Configuração CIOT inválida: esperado array, objeto ou JSON.');
        }

        return self::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $ambienteRaw = (string) ($data['ambiente'] ?? Ambiente::Homologacao->value);
        $ambiente = Environment::resolveAmbiente($ambienteRaw);

        $urlsPef = (array) ($data['urls'] ?? []);
        if ($urlsPef === []) {
            $urlsPef = [
                Ambiente::Homologacao->value => Environment::urlPef(Ambiente::Homologacao),
                Ambiente::Producao->value => Environment::urlPef(Ambiente::Producao),
            ];
        }

        $gerador = (array) ($data['gerador'] ?? []);
        $urlsGerador = (array) ($gerador['urls'] ?? []);
        if ($urlsGerador === []) {
            $urlsGerador = [
                Ambiente::Homologacao->value => Environment::urlGerador(Ambiente::Homologacao),
                Ambiente::Producao->value => Environment::urlGerador(Ambiente::Producao),
            ];
        }

        return new self(
            ambiente: $ambiente,
            urlsPef: $urlsPef,
            pathPrefix: (string) ($data['path_prefix'] ?? 'api'),
            certificado: (array) ($data['certificado'] ?? []),
            verificarSsl: (bool) ($data['verificar_ssl'] ?? true),
            timeout: (int) ($data['timeout'] ?? 30),
            connectTimeout: (int) ($data['connect_timeout'] ?? 60),
            validarAntesDeEnviar: (bool) ($data['validar_antes_de_enviar'] ?? true),
            operationIdGeneratorClass: isset($data['operation_id_generator'])
                ? (string) $data['operation_id_generator']
                : null,
            emissora: (array) ($data['emissora'] ?? []),
            geradorApiKey: isset($gerador['api_key']) ? (string) $gerador['api_key'] : null,
            urlsGerador: $urlsGerador,
            geradorTokenTtl: (int) ($gerador['token_ttl'] ?? 3540),
            geradorTimeout: isset($gerador['timeout']) ? (int) $gerador['timeout'] : null,
            geradorConnectTimeout: isset($gerador['connect_timeout']) ? (int) $gerador['connect_timeout'] : null,
            geradorVerificarSsl: isset($gerador['verificar_ssl']) ? (bool) $gerador['verificar_ssl'] : null,
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new CiotConfigurationException('JSON de configuração CIOT inválido.');
        }

        return self::fromArray($data);
    }

    public function ambiente(): Ambiente
    {
        return $this->ambiente;
    }

    public function urlPefBase(): string
    {
        return rtrim($this->urlsPef[$this->ambiente->value] ?? '', '/');
    }

    public function pathPrefix(): string
    {
        return $this->pathPrefix;
    }

    /**
     * @return array<string, mixed>
     */
    public function certificado(): array
    {
        return $this->certificado;
    }

    public function verificarSsl(): bool
    {
        return $this->verificarSsl;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function connectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function validarAntesDeEnviar(): bool
    {
        return $this->validarAntesDeEnviar;
    }

    public function geradorApiKey(): ?string
    {
        $key = $this->geradorApiKey;

        if ($key === null || trim($key) === '') {
            return null;
        }

        $key = trim($key);

        // Placeholders de exemplo — não ativa o atalho HTTP do pacote (não faz parte do DCS/PEF).
        if (preg_match('/^(SUA_API|COLE_AQUI|EXEMPLO)/i', $key)) {
            return null;
        }

        return $key;
    }

    /**
     * Bloco resolvido para {@see \Paseto\CiotAntt\Http\GuzzleGeradorCiotTransport}.
     *
     * @return array<string, mixed>
     */
    public function geradorTransportConfig(): array
    {
        return [
            'ambiente' => $this->ambiente->value,
            'base_url' => rtrim($this->urlsGerador[$this->ambiente->value] ?? '', '/'),
            'api_key' => $this->geradorApiKey ?? '',
            'token_ttl' => $this->geradorTokenTtl,
            'timeout' => $this->geradorTimeout ?? $this->timeout,
            'connect_timeout' => $this->geradorConnectTimeout ?? $this->connectTimeout,
            'verificar_ssl' => $this->geradorVerificarSsl ?? $this->verificarSsl,
        ];
    }

    /**
     * Formato legado compatível com {@see \Paseto\CiotAntt\Ciot}.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ambiente' => $this->ambiente->value,
            'urls' => $this->urlsPef,
            'path_prefix' => $this->pathPrefix,
            'certificado' => $this->certificado,
            'verificar_ssl' => $this->verificarSsl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'validar_antes_de_enviar' => $this->validarAntesDeEnviar,
            'operation_id_generator' => $this->operationIdGeneratorClass,
            'emissora' => $this->emissora,
            'gerador' => [
                'api_key' => $this->geradorApiKey,
                'urls' => $this->urlsGerador,
                'token_ttl' => $this->geradorTokenTtl,
                'timeout' => $this->geradorTimeout,
                'connect_timeout' => $this->geradorConnectTimeout,
                'verificar_ssl' => $this->geradorVerificarSsl,
            ],
        ];
    }
}