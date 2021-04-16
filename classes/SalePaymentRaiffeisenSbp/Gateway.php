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
                    "xtype"          => 'checkbox',
                    "name"           => 'atol',
                    "boxLabel"       => 'формировать кассовый чек 54-ФЗ (ATOL)',
                    "inputValue"     => 1,
                    "uncheckeDvalue" => 0
                ],
				[
					'name'       => 'atol_login',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Логин',
					'allowBlank' => false,
				],
				[
					'name'       => 'atol_pass',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Пароль',
					'allowBlank' => false,
				],                
				[
					'name'       => 'atol_group',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Идентификатор группы ККТ',
					'allowBlank' => true,
				],  
				[
					'name'       => 'atol_inn',
					'xtype'      => 'textfield',
					'fieldLabel' => 'ИНН',
					'allowBlank' => true,
				],                 
                [
                    'name'       => 'atol_paymentObject',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Тип оплачиваемой позиции',
                    'value'      => 1,
                    'store'      => [
                        ["commodity", 'товар'],
                        ["excise", 'подакцизный товар'],
                        ["job", 'работа'],
                        ["service", 'услуга'],
                        ["payment", 'платёж'],
                        ["agent_commission", 'агентское вознаграждение'],
                    ],
                ], 
                [
                    'name'       => 'atol_payment_method',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Тип оплаты',
                    'value'      => 1,
                    'store'      => [
                        ["full_prepayment", 'полная предварительная оплата до момента передачи предмета расчёта'],
                        ["prepayment", 'частичная предварительная оплата до момента передачи предмета расчёта'],
                        ["advance", 'аванс'],
                        ["full_payment", 'полная оплата в момент передачи предмета расчёта'],
                        ["partial_payment", 'частичная оплата предмета расчёта в момент его передачи с последующей оплатой в кредит'],
                        ["credit", 'передача предмета расчёта без его оплаты в момент его передачи с последующей оплатой в кредит'],
                        ["credit_payment", 'оплата предмета расчёта после его передачи с оплатой в кредит'],
                    ],
                ],                 
				[
					'name'       => 'atol_sno',
					'xtype'      => 'combobox',
					'fieldLabel' => 'Система налогообложения',
					'value'      => 0,
					'store'      => [
						["osn", 'общая СН'],
						["usn_income", 'упрощенная СН (доходы)'],
						["usn_income_outcome", 'упрощенная СН (доходы минус расходы)'],
						["envd", 'единый налог на вмененный доход'],
						["esn", 'единый сельскохозяйственный налог'],
						["patent", 'патентная СН'],
					],
				],                 
				[
					'name'       => 'atol_vat',
					'xtype'      => 'combobox',
					'fieldLabel' => 'Ставка налога',
					'value'      => 0,
					'store'      => [
						["none", 'без НДС'],
						["vat0", 'НДС по ставке 0%'],
						["vat10", 'НДС чека по ставке 10%'],
						["vat18", 'НДС чека по ставке 18%'],
						["vat110", 'НДС чека по расчетной ставке 10/110'],
						["vat118", 'НДС чека по расчетной ставке 18/118'],
                        ["vat20", 'НДС чека по ставке 20%'],
                        ["vat120", 'НДС чека по расчётной ставке 20/120'],
					],
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
            'paymentDetails'=> 'Заказ №'.$this->order->id
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