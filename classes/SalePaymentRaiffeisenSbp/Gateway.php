<?php
namespace SalePaymentRaiffeisenSbp;

class Gateway extends \Sale\PaymentGateway\GatewayAbstract {
	
    const GATEWAY_PRODUCTION = 'https://e-commerce.raiffeisen.ru';
    const GATEWAY_TEST = 'https://test.ecom.raiffeisen.ru';
    
	public static function getInfo()
	{
		
		return [
			'name'        => 'Системы Платежей Raiffeisen',
			'description' => '',
			'icon'        => '/plugins/sale-payment-raiffeisen-sbp/images/icon.png',
			'params' => [	
				[
					'name'       => 'sbpMerchantId',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Идентификатор зарегистрированного партнёра в СБП *',
					'allowBlank' => false,
				],
				[
					'name'       => 'account',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Счет для зачисления',
					'allowBlank' => true,
				],                
                [
                    "xtype"          => 'checkbox',
                    "name"           => 'test_mode',
                    "boxLabel"       => 'тестовый режим',
                    "inputValue"     => 1,
                    "uncheckeDvalue" => 0
                ],                
			]			
		];
	}
	
	public function pay( $return = '', $fail = '' )
	{
        //https://github.com/Raiffeisen-DGTL/ecom-sdk-javascript
        if (!$return) $return = \Cetera\Application::getInstance()->getServer()->getFullUrl();
        if (!$fail) $fail = \Cetera\Application::getInstance()->getServer()->getFullUrl();
        
        $data = [
            'sbpMerchantId' => $this->params['sbpMerchantId'],
            'account'       => $this->params['account'],
            'order'         => $this->order->id,
            'amount'        => $this->order->getTotal(),
            'currency'      => $this->order->getCurrency()->code,
        ]; 

        $url = $this->params["test_mode"]?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
        
        $client = new \GuzzleHttp\Client();
		$response = $client->request('POST', $url.'/api/sbp/v1/qr/register', [
			'verify' => false,
			'json' => $data,
		]); 

		$res = json_decode($response->getBody(), true);	

        print_r($res);
        die();
	}	

}