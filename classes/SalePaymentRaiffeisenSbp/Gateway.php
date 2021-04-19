<?php
namespace SalePaymentRaiffeisenSbp;

class Gateway extends \Sale\PaymentGateway\GatewayAtol {
	
    const GATEWAY_PRODUCTION = 'https://e-commerce.raiffeisen.ru';
    const GATEWAY_TEST = 'https://test.ecom.raiffeisen.ru';
    
	public static function getInfo2()
	{
		return [
			'name'        => 'Система быстрых платежей Raiffeisen',
			'description' => '',
			'icon'        => '/plugins/sale-payment-raiffeisen-sbp/images/icon.png',
			'params' => [	
				[
					'name'       => 'sbpMerchantId',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Идентификатор партнёра в СБП *',
					'allowBlank' => false,
				],
				[
					'name'       => 'secretKey',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Секретный ключ',
					'allowBlank' => false,
				],                
				[
					'name'       => 'account',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Счет для зачисления',
					'allowBlank' => true,
				],        
				[
					'name'       => 'url',
					'xtype'      => 'textfield',
					'fieldLabel' => 'URL страницы для отображения QR-кода',
					'allowBlank' => true,
				],                 
                [
                    "xtype"          => 'checkbox',
                    "name"           => 'test_mode',
                    "boxLabel"       => 'тестовый режим',
                    "inputValue"     => 1,
                    "uncheckeDvalue" => 0
                ],
                [
					'xtype'      => 'displayfield',
					'fieldLabel' => 'URL-адрес для callback уведомлений',
					'value'      => '//'.$_SERVER['HTTP_HOST'].'/cms/plugins/sale-payment-raiffeisen-sbp/callback.php'
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
            'paymentDetails'=> 'Заказ №'.$this->order->id,
            'additionalInfo'=> 'Заказ №'.$this->order->id,
        ]; 

        $url = $this->params["test_mode"]?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
        
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', $url.'/api/sbp/v1/qr/register', [
            'verify' => false,
            'json' => $data,
        ]); 

        $res = json_decode($response->getBody(), true);	

        $this->saveTransaction($res['qrId'], $res);

        if ($this->params['url']) {
            header('Location: '.$this->params['url'].'?qr='.urlencode($res['qrUrl']));
        }
        else {
            header('Location: '.$res['qrUrl']);
        }
        die();
	}	

}