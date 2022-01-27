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
            header('Location: '.$this->params['url'].'?qrId='.urlencode($res['qrId']).'&qrUrl='.urlencode($res['qrUrl']));
        }
        else {
            header('Location: '.$res['qrUrl']);
        }
        die();
	}

    public static function isRefundAllowed() {
        return true;
    }

    public function refund( $items = null ) {
              
		$params = [
            'refundId'  => 'refund'.$this->order->id,
            'order'     => $this->order->id,
            'amount'    => $this->order->getTotal(),
		];
        
        if ($items !== null) {
            $amount = 0;
            foreach ($items as $key => $item) {
                if ($item['quantity_refund'] <= 0) continue;
                $amount += intval($item['quantity_refund']) * $item['price'];
            }
            $params['amount'] = $amount;
        }
        
        //print_r($params);
        //return;        

        $url = $this->params["test_mode"]?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
        
        $client = new \GuzzleHttp\Client();
		$response = $client->request('POST', $url.'/api/sbp/v1/qr/refund', [
			'verify' => false,
			'form_params' => $params,
            'headers' => [ 'Authorization' => "Bearer ".$this->params['secretKey'] ],
		]);

        $res = json_decode($response->getBody(), true);

		if (!$res['errorCode']) {
            
            $this->saveTransaction($params['refundId'], $res);
            
            if ($this->params['atol']) {
                $res = $this->sendRecieptRefund( $items );
                $gateway->saveTransaction($params['refundId'], $res);
            }
            
			return;		
		}
		else {
            throw new \Exception($res['errorCode'].': '.$res['errorMessage']);
		}        
        
    } 	

}