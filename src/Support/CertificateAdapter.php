<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Support;

use NFePHP\Common\Certificate;
use Paseto\CiotAntt\Exceptions\CiotConfigurationException;

/**
 * Extrai certificado/chave do PFX e prepara opções mTLS do Guzzle (arquivos PEM).
 */
final class CertificateAdapter
{
    /** @var list<string> */
    private array $arquivosTemporarios = [];

    private bool $persistir = false;

    public function __construct(
        private readonly Certificate $certificate,
        private readonly ?string $cacheDir = null,
    ) {
        $this->persistir = $cacheDir !== null && $cacheDir !== '';
    }

    /**
     * @return array{cert: string, ssl_key: string}
     */
    public function guzzleOptions(): array
    {
        $certPem = (string) $this->certificate->publicKey . (string) $this->certificate->chainKeys;
        $keyPem = (string) $this->certificate->privateKey;

        if (trim($certPem) === '' || trim($keyPem) === '') {
            throw CiotConfigurationException::certificadoNaoConfigurado();
        }

        $dir = $this->cacheDir !== null && $this->cacheDir !== ''
            ? $this->cacheDir
            : sys_get_temp_dir();

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new CiotConfigurationException("Não foi possível criar o diretório de cache do certificado: {$dir}");
        }

        $hash = substr(hash('sha256', $certPem . $keyPem), 0, 16);
        $certFile = $dir . DIRECTORY_SEPARATOR . "ciot_{$hash}.crt.pem";
        $keyFile = $dir . DIRECTORY_SEPARATOR . "ciot_{$hash}.key.pem";

        $this->gravarSeguro($certFile, $certPem);
        $this->gravarSeguro($keyFile, $keyPem);

        return [
            'cert' => $certFile,
            'ssl_key' => $keyFile,
        ];
    }

    private function gravarSeguro(string $path, string $conteudo): void
    {
        if (is_file($path)) {
            return;
        }

        $tmp = $path . '.' . getmypid() . '.tmp';

        @unlink($tmp);
        $umaskAnterior = umask(0077);
        try {
            $gravou = @file_put_contents($tmp, $conteudo);
        } finally {
            umask($umaskAnterior);
        }

        if ($gravou === false) {
            throw new CiotConfigurationException("Não foi possível gravar o certificado em {$path}.");
        }

        @chmod($tmp, 0600);

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new CiotConfigurationException("Não foi possível finalizar a gravação do certificado em {$path}.");
        }

        @chmod($path, 0600);

        if (! $this->persistir) {
            $this->arquivosTemporarios[] = $path;
        }
    }

    public function __destruct()
    {
        foreach ($this->arquivosTemporarios as $arquivo) {
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }
}