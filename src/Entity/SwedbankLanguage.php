<?php

class SwedbankLanguage extends \ObjectModel
{
    /** @var  int */
    public $id_language;
    /** @var  int */
    public $id_shop;
    /** @var  string */
    public $selected_language;
    /** @var  string */
    public $type;
    /** @var  string */
    public $environment;

    public static $definition = array(
        'table' => 'swedbank_payment_language',
        'primary' => 'id_swedbank_payment_language',
        'fields' => array(
            'id_language' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'selected_language' => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
            'type' => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
            'environment' => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
        )
    );

    public static function getId($idLang, $idShop, $type, $environment)
    {
        $query = new DbQuery();
        $query->select('`id_swedbank_payment_language`');
        $query->from(pSQL(self::$definition['table']));
        $query->where('id_language="'.(int) $idLang.'"');
        $query->where('id_shop="'.(int) $idShop.'"');
        $query->where('type="'.pSQL($type).'"');
        $query->where('environment="'.pSQL($environment).'"');
        return (int) Db::getInstance()->getValue($query);
    }

    public function getAll(array $searchParams)
    {
        if (empty($searchParams)) {
            return array();
        }

        $query = new DbQuery();
        $query->select('*');
        $query->from(pSQL(self::$definition['table']));
        $queue = [];
        foreach ($searchParams as $dbFieldName => $value) {
            $queue[] =  '`'.pSQL($dbFieldName).'`="'.pSQL($value).'"';
        }

        $stringQueue = implode(' AND ', $queue);
        $query->where($stringQueue);
        return (array) Db::getInstance()->executeS($query);
    }
}
