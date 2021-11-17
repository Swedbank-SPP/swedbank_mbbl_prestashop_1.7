<?php


namespace Invertus\SwedBank\Entity;

use Db;
use DbQuery;
use Invertus\SwedBank\Config\Config;
use SwedBank;

/**
 * Class SwedbankPayment
 *
 * We don't want an object model here because it's only a relation between payment and currency and
 * we have a constant number of payment methods with fixed IDs.
 */
class SwedbankPayment
{
    /**
     * @var int
     */
    public $id;
    
    /**
     * @var array all available payment methods
     */
    public static $paymentMethods = array(
        Config::PAYMENT_METHOD_CARD,
        Config::PAYMENT_METHOD_SWEDBANK,
        Config::PAYMENT_METHOD_SEB,
        Config::PAYMENT_METHOD_DNB,
        Config::PAYMENT_METHOD_NORDEA,
        Config::PAYMENT_METHOD_DANSKE,
        Config::PAYMENT_METHOD_PAYPAL,
    );
    
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Gets all currencies with indication if it's assigned to this payment method or not
     *
     * @param bool $useCache
     *
     * @param string $environment
     * @return array
     */
    public function getCurrencies($useCache, $environment)
    {
        static $cache;
        $cacheKey = $this->id.$environment;
        
        if ($useCache && isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        
        $sql = 'SELECT c.id_currency, c.`name`, IF(ISNULL(spc.id_currency), 0, spc.`status`) `selected`
                FROM `'._DB_PREFIX_.'currency` c
                LEFT JOIN `'._DB_PREFIX_.'swedbank_payment_currency` spc ON c.id_currency = spc.id_currency
                WHERE (spc.id_payment = '.(int) $this->id.' AND spc.environment = "'.pSQL($environment).'")
                    OR (spc.id_payment IS NULL AND spc.environment IS NULL)';
        
        $resource = Db::getInstance()->query($sql);
        $result = array();
        
        while ($row = Db::getInstance()->nextRow($resource)) {
            $result[$row['id_currency']] = $row;
        }

        $cache[$cacheKey] = $result;

        return $cache[$cacheKey];
    }

    /**
     * Get all enabled currencies for current payment method in given environment
     *
     * @param int $idShop
     * @param string $environment
     *
     * @return array|int[] Array of currency IDs
     */
    public function getEnabledCurrencies($idShop, $environment)
    {
        $enabledCurrencies = [];
        $db = Db::getInstance();

        $query = new DbQuery();
        $query->select('spc.id_currency, spc.id_payment');

        $query->from('swedbank_payment_currency', 'spc');
        $query->leftJoin('currency_shop', 'cs', 'cs.id_currency = spc.id_currency');

        $query->where('cs.id_shop = '.(int) $idShop);
        $query->where('spc.id_payment = '.(int) $this->id);
        $query->where('spc.status = 1');
        $query->where('spc.environment = "'.$db->escape($environment).'"');

        $result = $db->query($query);

        while ($row =$db->nextRow($result)) {
            $enabledCurrencies[] = (int) $row['id_currency'];
        }

        return $enabledCurrencies;
    }

    /**
     * @param $currencyId
     *
     * @param bool $useCache
     *
     * @return bool true if given currency is enabled for this payment method, false otherwise
     */
    public function getCurrencyStatus($currencyId, $useCache, $environment)
    {
        $currencies = $this->getCurrencies($useCache, $environment);
        
        if (!isset($currencies[$currencyId])) {
            return false;
        }
        
        return $currencies[$currencyId]['selected'];
    }

    /**
     * Assigns a currency to this payment method and sets it's status
     *
     * @param $currencyId
     * @param $status
     * @param string $environment
     *
     * @return bool
     */
    public function saveCurrency($currencyId, $status, $environment)
    {
        // If the payment Id is not one of available payment methods - do not save it
        if (!in_array($this->id, self::$paymentMethods)) {
            return false;
        }

        if (!in_array($environment, [SwedBank::ENV_TEST, SwedBank::ENV_LIVE])) {
            return false;
        }

        return Db::getInstance()->insert(
            'swedbank_payment_currency',
            [
                'id_payment' => (int) $this->id,
                'id_currency' => (int) $currencyId,
                'status' => $status ? 1 : 0,
                'environment' => $environment,
            ],
            false,
            true,
            Db::ON_DUPLICATE_KEY
        );
    }
}
