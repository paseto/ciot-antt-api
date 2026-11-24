<?php

declare(strict_types=1);

namespace Paseto\CiotAntt;

use NFePHP\Common\Certificate;
use Psr\Log\LoggerInterface;
use Paseto\CiotAntt\Common\Config;
use Paseto\CiotAntt\Contracts\OperationIdGenerator;
use Paseto\CiotAntt\Contracts\PefTransport;
use Paseto\CiotAntt\Http\GuzzleGeradorCiotTransport;
use Paseto\CiotAntt\Http\GuzzlePefTransport;
use Paseto\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\ConsultarCiotGeradoRequest;
use Paseto\CiotAntt\Requests\ConsultarExcecaoRequest;
use Paseto\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;
use Paseto\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;
use Paseto\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;
use Paseto\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;
use Paseto\CiotAntt\Responses\CancelamentoOperacaoTransporteResponse;
use Paseto\CiotAntt\Responses\ConsultarCiotGeradoResponse;
use Paseto\CiotAntt\Responses\ConsultarExcecaoResponse;
use Paseto\CiotAntt\Responses\ConsultarFrotaTransportadorResponse;
use Paseto\CiotAntt\Responses\ConsultarSituacaoTransportadorResponse;
use Paseto\CiotAntt\Responses\DeclaracaoOperacaoTransporteResponse;
use Paseto\CiotAntt\Responses\EncerramentoOperacaoTransporteResponse;
use Paseto\CiotAntt\Responses\RetificacaoOperacaoTransporteResponse;
use Paseto\CiotAntt\Support\TokenManager;

/**
 * Ponto de entrada principal do SDK para os serviços PEF.
 */
final class Tools
{
    public ?string $lastRequest = null;

    public ?string $lastResponse = null;

    public ?int $lastStatus = null;

    public ?string $lastService = null;

    private readonly Config $config;

    private readonly Ciot $ciot;

    private readonly PefTransport $pefTransport;

    private readonly ?GuzzlePefTransport $guzzleTransport;

    private readonly ?GeradorCiot $gerador;

    /**
     * @param  array<string, mixed>|object|string  $config  JSON, array ou Config
     */
    public static function create(mixed $config, Certificate $certificate, ?LoggerInterface $logger = null): self
    {
        if (is_string($config)) {
            $config = Config::fromJson($config);
        } else {
            $config = Config::from($config);
        }

        return new self($config, $certificate, null, null, $logger);
    }

    public function __construct(
        Config $config,
        ?Certificate $certificate = null,
        ?PefTransport $pefTransport = null,
        ?OperationIdGenerator $idGenerator = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->config = $config;

        if ($pefTransport !== null) {
            $this->pefTransport = $pefTransport;
        } else {
            $this->pefTransport = new GuzzlePefTransport($config, $certificate, null, $this->logger);
        }

        $this->guzzleTransport = $this->pefTransport instanceof GuzzlePefTransport
            ? $this->pefTransport
            : null;

        $this->gerador = $this->montarGerador($config);
        $operationIdGenerator = $idGenerator ?? $this->gerador;

        $this->ciot = new Ciot($this->pefTransport, $config->toArray(), $operationIdGenerator);
    }

    public function gerador(): ?GeradorCiot
    {
        return $this->gerador;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function consultarSituacaoTransportador(
        ConsultarSituacaoTransportadorRequest $request
    ): ConsultarSituacaoTransportadorResponse {
        $resposta = $this->ciot->consultarSituacaoTransportador($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function consultarFrotaTransportador(
        ConsultarFrotaTransportadorRequest $request
    ): ConsultarFrotaTransportadorResponse {
        $resposta = $this->ciot->consultarFrotaTransportador($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function declararOperacao(
        DeclaracaoOperacaoTransporteRequest $request
    ): DeclaracaoOperacaoTransporteResponse {
        $resposta = $this->ciot->declararOperacao($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function cancelarOperacao(
        CancelamentoOperacaoTransporteRequest $request
    ): CancelamentoOperacaoTransporteResponse {
        $resposta = $this->ciot->cancelarOperacao($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function retificarOperacao(
        RetificacaoOperacaoTransporteRequest $request
    ): RetificacaoOperacaoTransporteResponse {
        $resposta = $this->ciot->retificarOperacao($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function encerrarOperacao(
        EncerramentoOperacaoTransporteRequest $request
    ): EncerramentoOperacaoTransporteResponse {
        $resposta = $this->ciot->encerrarOperacao($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function consultarExcecao(
        ConsultarExcecaoRequest|string $request
    ): ConsultarExcecaoResponse {
        $resposta = $this->ciot->consultarExcecao($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    public function consultarCiotGerado(
        ConsultarCiotGeradoRequest $request
    ): ConsultarCiotGeradoResponse {
        $resposta = $this->ciot->consultarCiotGerado($request);
        $this->sincronizarTrace();

        return $resposta;
    }

    private function sincronizarTrace(): void
    {
        if ($this->guzzleTransport === null) {
            return;
        }

        $this->lastRequest = $this->guzzleTransport->lastRequest;
        $this->lastResponse = $this->guzzleTransport->lastResponse;
        $this->lastStatus = $this->guzzleTransport->lastStatus;
        $this->lastService = $this->guzzleTransport->lastService;
    }

    private function montarGerador(Config $config): ?GeradorCiot
    {
        $apiKey = $config->geradorApiKey();
        if ($apiKey === null) {
            return null;
        }

        $transport = new GuzzleGeradorCiotTransport(
            $config->geradorTransportConfig(),
            null,
            $this->logger,
        );

        $blocoGerador = $config->geradorTransportConfig();

        $manager = new TokenManager(
            $transport,
            $apiKey,
            (int) ($blocoGerador['token_ttl'] ?? 3540),
        );

        return new GeradorCiot($transport, $manager);
    }
}