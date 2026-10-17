<?php
$application->connectDb();
$application->initSession();
$application->initPlugins();

/**
 * Пишет одну строку о результате обработки уведомления: в error_log и в logs/ рядом с корнем сайта.
 * Подпись, секретный ключ и персональные данные не пишутся.
 */
function raiffeisenSbpCallbackLog(array $context)
{
    $line = date('Y-m-d H:i:s') . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log('raiffeisen-sbp callback: ' . $line);

    $dir = dirname($_SERVER['DOCUMENT_ROOT']) . '/logs';
    if (is_dir($dir) && is_writable($dir)) {
        file_put_contents($dir . '/raiffeisen-sbp-callback.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * При несовпадении подписи подбирает, с каким форматом суммы и идентификатором мерчанта она бы сошлась.
 */
function raiffeisenSbpSignatureHint(array $tx, $orderId, array $params, $signature)
{
    $amount = $tx['amount'] ?? '';
    $amounts = array_unique([
        (string)$amount,
        is_numeric($amount) ? number_format((float)$amount, 2, '.', '') : (string)$amount,
    ]);
    $merchants = array_unique(array_filter([
        'sbpMerchantId' => $params['sbpMerchantId'] ?? null,
        'MerchantId'    => $params['MerchantId'] ?? null,
    ]));

    foreach ($amounts as $a) {
        foreach ($merchants as $merchantKey => $merchantId) {
            $hash = hash_hmac('sha256', implode('|', [
                $a,
                $merchantId,
                $orderId,
                $tx['status']['value'] ?? '',
                $tx['status']['date'] ?? '',
            ]), $params['secretKey'] ?? '');
            if (hash_equals($hash, (string)$signature)) {
                return 'matches with amount=' . $a . ', merchant=' . $merchantKey;
            }
        }
    }

    return 'no match (amount=' . implode('/', $amounts) . ', merchants=' . implode('/', array_keys($merchants)) . ')';
}

ob_start();

$log = [];

try {
    $source = file_get_contents('php://input');
    $requestBody = json_decode($source, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $log['body'] = mb_substr($source, 0, 200);
        throw new \Exception('Invalid JSON: ' . json_last_error_msg());
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $signature = null;
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-api-signature-sha256') {
            $signature = $value;
            break;
        }
    }
    if (!$signature && isset($_SERVER['HTTP_X_API_SIGNATURE_SHA256'])) {
        $signature = $_SERVER['HTTP_X_API_SIGNATURE_SHA256'];
    }

    $event = strtolower($requestBody['event'] ?? '');
    $tx = $requestBody['transaction'] ?? [];

    $log = [
        'event'      => $event !== '' ? $event : null,
        'keys'       => $event !== '' ? null : implode(',', array_keys((array)$requestBody)),
        'orderId'    => $tx['orderId'] ?? null,
        'txId'       => $tx['id'] ?? null,
        'qrId'       => $tx['paymentParams']['qrId'] ?? null,
        'status'     => $tx['status']['value'] ?? null,
        'statusDate' => $tx['status']['date'] ?? null,
        'amount'     => $tx['amount'] ?? null,
        'signature'  => $signature ? 'present' : 'missing',
    ];

    if ($event === 'payment') {
        $orderId = $tx['orderId'] ?? null;
        if (!$orderId) {
            throw new \Exception('Order ID not found in webhook data');
        }

        $order = \Sale\Order::getById($orderId);

        $gateway = $order->getPaymentGateway();
        $log['gateway'] = $gateway ? get_class($gateway) : null;
        $log['paid'] = $order->isPaid();
        if (!$gateway) {
            throw new \Exception('Payment gateway of the order not found');
        }

        $qrId = $tx['paymentParams']['qrId'] ?? null;

        if ($qrId) {
            $oid = $gateway->getOrderByTransaction($qrId);
            if ($oid != $order->id) {
                $log['qrOrderId'] = $oid ?: null;
                throw new \Exception('Order check failed');
            }
        }

        $merchantIdForHash = $gateway->params['sbpMerchantId'] ?? $gateway->params['MerchantId'] ?? '';

        $hash = hash_hmac("sha256", implode('|', [
            $tx['amount'] ?? '',
            $merchantIdForHash,
            $orderId,
            $tx['status']['value'] ?? '',
            $tx['status']['date'] ?? '',
        ]), $gateway->params['secretKey']);

        if ($hash !== $signature) {
            $log['signatureHint'] = raiffeisenSbpSignatureHint($tx, $orderId, $gateway->params, $signature);
            throw new \Exception('X-Api-Signature check failed');
        }

        $gateway->saveTransaction($qrId ?: ($tx['id'] ?? ''), $requestBody);

        if (strtoupper($tx['status']['value'] ?? '') === 'SUCCESS') {
            $order->paymentSuccess();
            $gateway->sendReceiptSell();
        }

        header("HTTP/1.1 200 OK");
        print 'OK';
    } elseif ($event === 'refund') {
        header("HTTP/1.1 200 OK");
        print 'OK';
    } else {
        header("HTTP/1.1 200 OK");
        print 'Unknown event';
    }

    $log['result'] = 'OK';
} catch (\Throwable $e) {
    $log['result'] = 'error';
    $log['error'] = get_class($e) . ': ' . $e->getMessage();
    if (!$e instanceof \Exception) {
        $log['error'] .= ' at ' . $e->getFile() . ':' . $e->getLine();
    }
    header("HTTP/1.1 500 " . trim(preg_replace('/\s+/', ' ', $e->getMessage())));
    print $e->getMessage();
}

raiffeisenSbpCallbackLog($log);

ob_end_clean();
