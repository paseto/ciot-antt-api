<?php

declare(strict_types=1);

namespace Paseto\CiotAntt\Examples;

use NFePHP\Common\Certificate;
use Paseto\CiotAntt\Exceptions\CiotConfigurationException;
use Paseto\CiotAntt\Exceptions\CiotConnectionException;
use Paseto\CiotAntt\Exceptions\CiotException;
use Paseto\CiotAntt\Exceptions\CiotValidationException;
use Paseto\CiotAntt\Tools;

/**
 * Suporte CLI/HTTP para os scripts em examples/.
 */
final class Bootstrap
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    private static ?Tools $tools = null;

    public static function run(callable $acao): never
    {
        try {
            $resultado = $acao(self::tools(), self::configArray());
            self::emitir([
                'ok' => true,
                ...$resultado,
                '_trace' => self::trace(),
            ]);
        } catch (CiotValidationException $e) {
            self::emitir([
                'ok' => false,
                'tipo' => 'validacao',
                'mensagem' => $e->getMessage(),
                'erros' => $e->erros(),
            ], 422);
        } catch (CiotConnectionException $e) {
            self::emitir([
                'ok' => false,
                'tipo' => 'conexao',
                'mensagem' => $e->getMessage(),
            ], 502);
        } catch (CiotConfigurationException $e) {
            self::emitir([
                'ok' => false,
                'tipo' => 'configuracao',
                'mensagem' => $e->getMessage(),
            ], 500);
        } catch (CiotException $e) {
            self::emitir([
                'ok' => false,
                'tipo' => 'ciot',
                'mensagem' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            self::emitir([
                'ok' => false,
                'tipo' => 'erro',
                'mensagem' => $e->getMessage(),
            ], 500);
        }
    }

    public static function tools(): Tools
    {
        if (self::$tools !== null) {
            return self::$tools;
        }

        self::carregarVendor();
        $json = file_get_contents(self::configPath());
        if ($json === false) {
            throw new \RuntimeException('Não foi possível ler ' . self::configPath());
        }

        $cert = self::certificado();
        self::$tools = Tools::create($json, $cert);

        return self::$tools;
    }

    /**
     * @return array<string, mixed>
     */
    public static function configArray(): array
    {
        if (self::$config === null) {
            self::carregarVendor();
            $json = file_get_contents(self::configPath());
            if ($json === false) {
                throw new \RuntimeException('Não foi possível ler ' . self::configPath());
            }
            $data = json_decode($json, true);
            if (! is_array($data)) {
                throw new \RuntimeException('JSON inválido em examples/config.json');
            }
            self::$config = $data;
        }

        return self::$config;
    }

    public static function empresa(string $chave, ?string $fallback = null): string
    {
        $cfg = self::configArray();
        $bloco = (array) ($cfg['empresa'] ?? []);
        $valor = (string) ($bloco[$chave] ?? '');

        if (PHP_SAPI !== 'cli') {
            $valor = self::param($chave, $valor) ?? $valor;
        }

        if ($valor === '' && $fallback !== null) {
            return $fallback;
        }

        return $valor;
    }

    public static function param(string $nome, ?string $padrao = null): ?string
    {
        if (PHP_SAPI === 'cli') {
            return $padrao;
        }

        $v = $_GET[$nome] ?? $_POST[$nome] ?? null;
        if ($v === null || $v === '') {
            return $padrao;
        }

        return is_string($v) ? trim($v) : (string) $v;
    }

    public static function configPath(): string
    {
        return __DIR__ . '/config.json';
    }

    private static function certificado(): Certificate
    {
        $cfg = self::configArray();
        $cert = (array) ($cfg['certificado'] ?? []);

        $pfx = self::cliArg(1) ?? (string) ($cert['pfx_path'] ?? '');
        $senha = self::cliArg(2) ?? (string) ($cert['pfx_senha'] ?? '');

        if ($pfx === '' || $senha === '') {
            throw new \RuntimeException(
                'Certificado: preencha certificado.pfx_path e pfx_senha em examples/config.json '
                . 'ou use CLI: php examples/script.php /caminho/cert.pfx senha',
            );
        }

        if (! is_readable($pfx)) {
            throw new \RuntimeException("PFX não encontrado ou sem leitura: {$pfx}");
        }

        return Certificate::readPfx((string) file_get_contents($pfx), $senha);
    }

    public static function cliArg(int $index): ?string
    {
        if (PHP_SAPI !== 'cli') {
            return null;
        }

        global $argv;

        return isset($argv[$index]) && $argv[$index] !== '' ? (string) $argv[$index] : null;
    }

    public static function argOuParam(int $indiceCli, string $nomeQuery, ?string $padrao = null): string
    {
        if (PHP_SAPI === 'cli') {
            return self::cliArg($indiceCli) ?? $padrao ?? '';
        }

        return self::param($nomeQuery, $padrao) ?? '';
    }

    private static function carregarVendor(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $candidatos = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];

        foreach ($candidatos as $path) {
            if (is_file($path)) {
                require_once $path;
                $loaded = true;

                return;
            }
        }

        throw new \RuntimeException('Execute composer install na raiz do pacote.');
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private static function emitir(array $dados, int $status = 200): never
    {
        if (PHP_SAPI !== 'cli') {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit($status >= 400 ? 1 : 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function trace(): array
    {
        $t = self::$tools;
        if ($t === null) {
            return [];
        }

        return array_filter([
            'http_status' => $t->lastStatus,
            'servico' => $t->lastService,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}