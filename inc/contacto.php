<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// CONFIGURAÇÕES DO TESTE


$customerMsisdn = '258849596865';
$amount = '5';

$apiKey = '66bcaqeruzhqovqshaawuujrebfdpsdl';
$serviceProviderCode = '901819';

$endpoint = 'https://api.vm.co.mz:18352/ipg/v1x/c2bPayment/singleStage/';

$publicKeyBase64 = 'MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAyrOP7fgXIJgJyp6nP/Vtlu8kW94Qu+gJjfMaTNOSd/mQJChqXiMWsZPH8uOoZGeR/9m7Y8vAU83D96usXUaKoDYiVmxoMBkfmw8DJAtHHt/8LWDdoAS/kpXyZJ5dt19Pv+rTApcjg7AoGczT+yIU7xp4Ku23EqQz70V5Rud+Qgerf6So28Pt3qZ9hxgUA6lgF7OjoYOIAKPqg07pHp2eOp4P6oQW8oXsS+cQkaPVo3nM1f+fctFGQtgLJ0y5VG61ZiWWWFMOjYFkBSbNOyJpQVcMKPcfdDRKq+9r5DFLtFGztPYIAovBm3a1Q6XYDkGYZWtnD8mDJxgEiHWCzog0wZqJtfNREnLf1g2ZOanTDcrEFzsnP2MQwIatV8M6q/fYrh5WejlNm4ujnKUVbnPMYH0wcbXQifSDhg2jcnRLHh9CF9iabkxAzjbYkaG1qa4zG+bCidLCRe0cEQvt0+/lQ40yESvpWF60omTy1dLSd10gl2//0v4IMjLMn9tgxhPp9c+C2Aw7x2Yjx3GquSYhU6IL41lrURwDuCQpg3F30QwIHgy1D8xIfQzno3XywiiUvoq4YfCkN9WiyKz0btD6ZX02RRK6DrXTFefeKjWf0RHREHlfwkhesZ4X168Lxe9iCWjP2d0xUB+lr10835ZUpYYIr4Gon9NTjkoOGwFyS5ECAwEAAQ==';

// VALIDAÇÕES INICIAIS

if (!extension_loaded('openssl')) {
    exit('Erro: a extensão OpenSSL não está activa no PHP.');
}

if (!extension_loaded('curl')) {
    exit('Erro: a extensão cURL não está activa no PHP.');
}

if ($apiKey === '' || $apiKey === 'COLOQUE_AQUI_A_NOVA_API_KEY') {
    exit('Erro: configure a API key antes de executar o teste.');
}

if (!preg_match('/^2588[245678]\d{7}$/', $customerMsisdn)) {
    exit('Erro: o número de telefone não parece ser um MSISDN válido.');
}

if (!is_numeric($amount) || (float) $amount <= 0) {
    exit('Erro: o valor da cobrança deve ser numérico e superior a zero.');
}

// PREPARAÇÃO DA CHAVE PÚBLICA

$decodedPublicKey = base64_decode($publicKeyBase64, true);

if ($decodedPublicKey === false) {
    exit('Erro: a chave pública não está num formato Base64 válido.');
}

$publicKeyPem =
    "-----BEGIN PUBLIC KEY-----\n" .
    chunk_split(base64_encode($decodedPublicKey), 64, "\n") .
    "-----END PUBLIC KEY-----\n";

$publicKey = openssl_pkey_get_public($publicKeyPem);

if ($publicKey === false) {
    $opensslErrors = [];

    while ($error = openssl_error_string()) {
        $opensslErrors[] = $error;
    }

    exit(
        'Erro ao carregar a chave pública: ' .
        implode(' | ', $opensslErrors)
    );
}

// ENCRIPTAÇÃO DA API KEY

$encryptedApiKey = '';

$encryptionSucceeded = openssl_public_encrypt(
    $apiKey,
    $encryptedApiKey,
    $publicKey,
    OPENSSL_PKCS1_PADDING
);

if (!$encryptionSucceeded) {
    $opensslErrors = [];

    while ($error = openssl_error_string()) {
        $opensslErrors[] = $error;
    }

    exit(
        'Erro ao encriptar a API key: ' .
        implode(' | ', $opensslErrors)
    );
}

$encodedApiKey = base64_encode($encryptedApiKey);

// DADOS DO PEDIDO

$thirdPartyReference =
    'TIME' .
    date('YmdHis') .
    random_int(100, 999);

$requestData = [
    'input_TransactionReference' => "Subscricao nJOBS",
    'input_CustomerMSISDN' => $customerMsisdn,
    'input_Amount' => $amount,
    'input_ThirdPartyReference' => $thirdPartyReference,
    'input_ServiceProviderCode' => $serviceProviderCode,
];

$jsonData = json_encode(
    $requestData,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ($jsonData === false) {
    exit('Erro ao converter os dados para JSON: ' . json_last_error_msg());
}

// PEDIDO CURL

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $encodedApiKey,
    'Origin: developer.mpesa.vm.co.mz',
];

$curl = curl_init();

if ($curl === false) {
    exit('Erro: não foi possível inicializar o cURL.');
}

curl_setopt_array($curl, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($curl);

$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlErrorNumber = curl_errno($curl);
$curlErrorMessage = curl_error($curl);

curl_close($curl);

// RESULTADO

header('Content-Type: text/plain; charset=utf-8');

echo "===== PEDIDO ENVIADO =====\n\n";
echo json_encode(
    $requestData,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

echo "\n\n===== RESULTADO =====\n\n";
echo "HTTP Status: {$httpCode}\n";
echo "Referência: {$thirdPartyReference}\n\n";

if ($response === false) {
    echo "Erro cURL: {$curlErrorNumber}\n";
    echo "Descrição: {$curlErrorMessage}\n";
    exit;
}

echo "Resposta da API:\n{$response}\n";