<?php

class SwedbankOrderPayment
{
    /**
     * Associate order with unique ID used in payment
     *
     * @param int $idOrder
     * @param int $idShop
     * @param string $idUnique
     *
     * @return bool
     */
    public static function associateUniqueId($idOrder, $idShop, $idUnique)
    {
        $data = [
            'id_order' => $idOrder,
            'id_shop' => $idShop,
            'id_unique' => $idUnique,
        ];

        $result = Db::getInstance()->insert(
            'swedbank_order_payment',
            $data,
            null,
            true,
            Db::ON_DUPLICATE_KEY
        );

        return (bool) $result;
    }

    /**
     * Retrieve unique ID associated with an order
     *
     * @param int $idOrder
     * @param int $idShop
     *
     * @return false|string
     */
    public static function retrieveUniqueId($idOrder, $idShop)
    {
        $query = new DbQuery();
        $query->select('id_unique');
        $query->from('swedbank_order_payment');
        $query->where('id_shop = '.(int) $idShop);
        $query->where('id_order = '.(int) $idOrder);

        $result = Db::getInstance()->getValue($query);

        return $result;
    }

    public static function retrieveIdOrderByReference($referenceId)
    {
        $fullReference = explode('-', $referenceId);

        $orderReference = $fullReference[0] ? $fullReference[0] : '';
        $idUnique = $fullReference[1] ? $fullReference[1] : '';

        if (!$orderReference || ! $idUnique) {
            return 0;
        }
        $query = new DbQuery();
        $query->select('op.`id_order`');
        $query->from('swedbank_order_payment', 'op');
        $query->innerJoin(
            'orders',
            'o',
            'o.`id_order`=op.`id_order`'
        );
        $query->where('op.`id_unique`="'.pSQL($idUnique).'"');
        $query->where('o.`reference`="'.pSQL($orderReference).'"');
        return (int) Db::getInstance()->getValue($query);
    }
}
