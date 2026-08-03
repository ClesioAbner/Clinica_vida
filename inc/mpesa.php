<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gateway de pagamento M-Pesa (C2B Single Stage).
 *
 * Camada: Serviço.
 * Responsabilidade única: encriptar credenciais, montar e enviar o pedido
 * de cobrança à API Vodacom M-Pesa, e devolver um resultado estruturado.
 *
 * Esta classe NUNCA é instanciada/executada no carregamento do tema.
 * É invocada apenas via AJAX (ver Clinica_Mpesa_Ajax_Handler no fim do ficheiro).
 */
final class Clinica_Mpesa_Gateway
{
    private const MSISDN_PATTERN = '/^2588[245678]\d{7}$/';
    private const HTTP_CONNECT_TIMEOUT = 20;
    private const HTTP_TIMEOUT = 30;

    // Defaults — usados apenas se as constantes MPESA_* não estiverem
    // definidas em wp-config.php. Mantidos aqui só para não bloquear o
    // teste local; mover para wp-config.php antes de qualquer commit/deploy.
    private const DEFAULT_API_KEY = ' SUA API KEY';
    private const DEFAULT_SERVICE_PROVIDER_CODE = 'SEU CODIGO';
    private const DEFAULT_ENDPOINT = 'SEU ENDPOINT;
    private const DEFAULT_PUBLIC_KEY = 'SUA CHAVE PUBLICA';

    private string $apiKey;
    private string $serviceProviderCode;
    private string $publicKeyBase64;
    private string $endpoint;

    public function __construct(
        string $apiKey,
        string $serviceProviderCode,
        string $publicKeyBase64,
        string $endpoint
    ) {
        if ($apiKey === '' || $serviceProviderCode === '' || $publicKeyBase64 === '') {
            throw new InvalidArgumentException(
                'Clinica_Mpesa_Gateway: credenciais em falta. Verifique wp-config.php.'
            );
        }

        if (!extension_loaded('openssl') || !extension_loaded('curl')) {
            throw new RuntimeException(
                'Clinica_Mpesa_Gateway: extensões OpenSSL e/ou cURL indisponíveis no PHP.'
            );
        }

        $this->apiKey = $apiKey;
        $this->serviceProviderCode = $serviceProviderCode;
        $this->publicKeyBase64 = $publicKeyBase64;
        $this->endpoint = $endpoint;
    }

    /**
     * Cria a instância a partir das constantes definidas em wp-config.php,
     * com fallback para os valores de teste hardcoded abaixo.
     *
     * ATENÇÃO: os valores hardcoded existem só para teste local rápido.
     * Antes de fazer commit/deploy, definir as constantes em wp-config.php
     * e remover os literais daqui — ver comentário no fim da classe.
     */
    public static function fromConfig(): self
    {
        return new self(
            defined('MPESA_API_KEY') ? (string) MPESA_API_KEY : self::DEFAULT_API_KEY,
            defined('MPESA_SERVICE_PROVIDER_CODE') ? (string) MPESA_SERVICE_PROVIDER_CODE : self::DEFAULT_SERVICE_PROVIDER_CODE,
            defined('MPESA_PUBLIC_KEY') ? (string) MPESA_PUBLIC_KEY : self::DEFAULT_PUBLIC_KEY,
            defined('MPESA_ENDPOINT') ? (string) MPESA_ENDPOINT : self::DEFAULT_ENDPOINT
        );
    }

    /**
     * Processa uma cobrança C2B.
     *
     * @return array{
     *   success: bool,
     *   http_code?: int,
     *   reference?: string,
     *   response?: mixed,
     *   error?: string
     * }
     */
    public function cobrar(string $msisdn, string $amount, string $descricao = 'Subscricao nJOBS'): array
    {
        $validationError = $this->validar($msisdn, $amount);
        if ($validationError !== null) {
            return ['success' => false, 'error' => $validationError];
        }

        try {
            $encodedApiKey = $this->encriptarApiKey();
        } catch (RuntimeException $e) {
            error_log('[Clinica_Mpesa_Gateway] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha na encriptação das credenciais.'];
        }

        $thirdPartyReference = $this->gerarReferencia();

        $payload = [
            'input_TransactionReference' => $descricao,
            'input_CustomerMSISDN' => $msisdn,
            'input_Amount' => $amount,
            'input_ThirdPartyReference' => $thirdPartyReference,
            'input_ServiceProviderCode' => $this->serviceProviderCode,
        ];

        $resultado = $this->enviar($payload, $encodedApiKey);
        $resultado['reference'] = $thirdPartyReference;

        return $resultado;
    }

    private function validar(string $msisdn, string $amount): ?string
    {
        if (!preg_match(self::MSISDN_PATTERN, $msisdn)) {
            return 'Número de telefone inválido.';
        }

        if (!is_numeric($amount) || (float) $amount <= 0) {
            return 'Valor da cobrança inválido.';
        }

        return null;
    }

    private function encriptarApiKey(): string
    {
        $decodedPublicKey = base64_decode($this->publicKeyBase64, true);

        if ($decodedPublicKey === false) {
            throw new RuntimeException('Chave pública não está em Base64 válido.');
        }

        $publicKeyPem =
            "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($decodedPublicKey), 64, "\n") .
            "-----END PUBLIC KEY-----\n";

        $publicKey = openssl_pkey_get_public($publicKeyPem);

        if ($publicKey === false) {
            throw new RuntimeException('Erro ao carregar chave pública: ' . $this->drainOpensslErrors());
        }

        $encrypted = '';
        $ok = openssl_public_encrypt($this->apiKey, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        if (!$ok) {
            throw new RuntimeException('Erro ao encriptar API key: ' . $this->drainOpensslErrors());
        }

        return base64_encode($encrypted);
    }

    private function drainOpensslErrors(): string
    {
        $errors = [];
        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }
        return implode(' | ', $errors);
    }

    private function gerarReferencia(): string
    {
        return 'TIME' . date('YmdHis') . random_int(100, 999);
    }

    /**
     * @param array<string, string> $payload
     */
    private function enviar(array $payload, string $encodedApiKey): array
    {
        $jsonData = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($jsonData === false) {
            return ['success' => false, 'error' => 'Erro ao converter payload para JSON.'];
        }

        $curl = curl_init($this->endpoint);

        if ($curl === false) {
            return ['success' => false, 'error' => 'Falha ao inicializar cURL.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $encodedApiKey,
                'Origin: developer.mpesa.vm.co.mz',
            ],
            CURLOPT_CONNECTTIMEOUT => self::HTTP_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($response === false) {
            error_log("[Clinica_Mpesa_Gateway] cURL #{$curlErrno}: {$curlError}");
            return [
                'success' => false,
                'error' => "Falha de comunicação com o gateway (cURL #{$curlErrno}).",
            ];
        }

        return [
            'success' => $httpCode === 200 || $httpCode === 201,
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
        ];
    }
}

/**
 * Handler AJAX — único ponto de entrada externo para esta classe.
 * Nunca é chamado no carregamento do tema.
 */
final class Clinica_Mpesa_Ajax_Handler
{
    public static function registar(): void
    {
        add_action('wp_ajax_clinica_mpesa_pagar', [self::class, 'handle']);
        add_action('wp_ajax_nopriv_clinica_mpesa_pagar', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        check_ajax_referer('clinica_mpesa_nonce', 'nonce');

        $msisdn = sanitize_text_field(wp_unslash($_POST['msisdn'] ?? ''));
        $amount = sanitize_text_field(wp_unslash($_POST['amount'] ?? ''));

        try {
            $gateway = Clinica_Mpesa_Gateway::fromConfig();
        } catch (Throwable $e) {
            error_log('[Clinica_Mpesa_Ajax_Handler] ' . $e->getMessage());
            wp_send_json(['success' => false, 'error' => 'Gateway de pagamento indisponível.'], 500);
            return;
        }

        $resultado = $gateway->cobrar($msisdn, $amount);

        wp_send_json($resultado, $resultado['success'] ? 200 : 422);
    }
}

Clinica_Mpesa_Ajax_Handler::registar();