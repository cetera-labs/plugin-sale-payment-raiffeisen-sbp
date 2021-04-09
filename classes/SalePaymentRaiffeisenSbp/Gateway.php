<?php
namespace SalePaymentRaiffeisenSbp;

class Gateway extends \Sale\PaymentGateway\GatewayAbstract {
	
    const GATEWAY_PRODUCTION = 'https://e-commerce.raiffeisen.ru';
    const GATEWAY_TEST = 'https://test.ecom.raiffeisen.ru';
    
	public static function getInfo()
	{
		$t = \Cetera\Application::getInstance()->getTranslator();
		
		return [
			'name'        => 'Системы Платежей Raiffeisen',
			'description' => '',
			'icon'        => '/plugins/sale-payment-raiffeisen-sbp/images/icon.png',
			'params' => [	
				[
					'name'       => 'publicId',
					'xtype'      => 'textfield',
					'fieldLabel' => $t->_('Идентификатор магазина *'),
					'allowBlank' => false,
				],
				[
					'name'       => 'paymentMethod',
					'fieldLabel' => $t->_('Выбор метода оплаты'),
					'xtype'      => 'combobox',
					'value'      => '',
					'store'      => [
						['',  $t->_('Эквайринг и СБП')],
						["ONLY_ACQUIRING",$t->_('Эквайринг')],
						["ONLY_SBP",$t->_('СБП')],
					],
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
            'publicId'      => $this->params['publicId'],
            'paymentMethod' => $this->params['paymentMethod'],
            'orderId'       => $this->order->id,
            'amount'        => $this->order->getTotal(),
            'successUrl'    => $return,
            'failUrl'       => $fail,
        ]; 

        $url = $this->params["test_mode"]?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
        
        $client = new \GuzzleHttp\Client();
		$response = $client->request('GET', $url.'/register.do', [
			'verify' => false,
			'query' => $data,
		]); 

		$res = json_decode($response->getBody(), true);	

        print_r($res);
        
	}	

}