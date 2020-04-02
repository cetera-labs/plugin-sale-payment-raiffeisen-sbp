<?php
if (class_exists("\Sale\Payment")) {
    try {
        \Sale\Payment::addGateway('\SalePaymentRaiffeisen\Gateway');
    } catch (\Exception $e) {
    }
}

// Подключаем каталог с переводами модуля
$t = $this->getTranslator();
$t->addTranslation(__DIR__.'/lang');