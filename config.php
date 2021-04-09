<?php
if (class_exists("\Sale\Payment")) {
    try {
        \Sale\Payment::addGateway('\SalePaymentRaiffeisenSbp\Gateway');
    } catch (\Exception $e) {
    }
}