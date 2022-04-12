<?php
$application->connectDb();
$application->initSession();
$application->initPlugins();

ob_start();

try {
    
    $source = file_get_contents('php://input');	
    $requestBody = json_decode($source, true);

    $headers = getallheaders();
    
    /*
    $requestBody = [
        'transactionId' => '156781',
        'qrId' => 'AD100022A20MJ2TV9N79UESV97I091TO',
        'sbpMerchantId' => 'MA0000091561',
        'merchantId' => '1786926001',
        'amount' => '1',
        'currency' => 'RUB',
        'transactionDate' => '2021-04-19T15:33:48+03:00',
        'paymentStatus' => 'SUCCESS',
        'additionalInfo' => '',
        'order' => '59528',
        'createDate' => '2021-04-19T15:31:59+03:00', 
    ];
    $headers = [
        'X-Api-Signature-Sha256' => '491a68d56081262d083b1314abfc3e94de9ed361048b123aea75a7c57abe0fcb'
    ];
    */
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
    
    if ($hash != $headers['X-Api-Signature-Sha256']) {
        throw new \Exception('X-Api-Signature check failed');
    }
		
	$gateway->saveTransaction($requestBody['qrId'], $requestBody);
		
	// Операция подтверждена
	if  ($requestBody['paymentStatus'] == 'SUCCESS') {
		$order->paymentSuccess();
        $gateway->sendRecieptSell();
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