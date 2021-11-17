<?php

class SwedbankCardPaymentData extends ObjectModel
{
    /**
     * @var int
     */
    public $id_order;

    /**
     * @var string
     */
    public $expiry_date;

    /**
     * @var string
     */
    public $pan;

    /**
     * @var string
     */
    public $authorization_code;

    /**
     * @var string
     */
    public $merchant_reference;

    /**
     * @var string
     */
    public $fulfill_date;

    /**
     * @var array
     */
    public static $definition = [
        'table' => 'swedbank_card_payment_data',
        'primary' => 'id_swedbank_card_payment_data',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'required' => 1, 'validate' => 'isUnsignedInt'],
            'expiry_date' => ['type' => self::TYPE_STRING, 'required' => 1, 'validate' => 'isCleanHtml'],
            'pan' => ['type' => self::TYPE_STRING, 'required' => 1, 'validate' => 'isCleanHtml'],
            'authorization_code' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
            'merchant_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
            'fulfill_date' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
        ],
    ];
}
