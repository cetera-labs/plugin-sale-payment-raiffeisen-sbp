<?php
$application->connectDb();
$application->initSession();
$application->initPlugins();

ob_start();

try {
    
    $source = file_get_contents('php://input');	
    $requestBody = json_decode($source, true);

    $headers = getallheaders();
    
    print_r($requestBody);
    print_r($headers);

	$order = \Sale\Order::getById( $requestBody['order'] );
	$gateway = $order->getPaymentGateway();
    
    $oid = $gateway->getOrderByTransaction( $requestBody['qrId'] );
    if ($oid != $order->id) {
        throw new \Exception('Order check failed');
    }
    
    $hash = hash_hmac ( "sha256" , implode('|',[
        $requestBody['amount'],
        $requestBody['sbpMerchantId'],
        $requestBody['order'],
        $requestBody['paymentStatus'],
        $requestBody['transactionDate'],
    ]), $gateway->params['secretKey']);
    
    if ($hash != $headers['X-Api-Signature-SHA256']) {
        throw new \Exception('X-Api-Signature check failed');
    }
		
	$gateway->saveTransaction($requestBody['qrId'], $requestBody);
		
	// Операция подтверждена
	if  ($requestBody['paymentStatus'] == 'SUCCESS') {
		$order->paymentSuccess();
        
        if ($gateway->params['atol']) {
            $gateway->sendRecieptSell();
        }
	}
	
	header("HTTP/1.1 200 OK");
	print 'OK';		
	
}
catch (\Exception $e) {
	
	header( "HTTP/1.1 500 ".trim(preg_replace('/\s+/', ' ', $e->getMessage())) );
	print $e->getMessage();
	 
}

$data = ob_get_contents();
ob_end_flush();
file_put_contents(__DIR__.'/log'.time().'.txt', $data);