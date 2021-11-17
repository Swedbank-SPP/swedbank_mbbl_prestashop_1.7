<?php

class SwedbankOrderStatus
{

    public static function insertItem($idOrder, $pay_status, $merchant_ref, $pmmm, $t, $lnv)
    {
        $data = [
            'id_order' => $idOrder,
            'pay_status' => $pay_status,
            'merchant_ref' => $merchant_ref,
            'date_created' => date('Y-m-d H:i:s'),
            'pmmm' => $pmmm,
            't' => $t,
            'lnv' => $lnv
        ];

        $result = Db::getInstance()->insert(
            'swedbank_order_status',
            $data,
            null,
            true,
            Db::ON_DUPLICATE_KEY
        );

        return (bool) $result;
    }

    public static function updateItem($merchantref, $pay_status)
    {
        $data = [
            'pay_status' => $pay_status
        ];

        $result = Db::getInstance()->update(
            'swedbank_order_status',
            $data,
            'merchant_ref = "'.pSQL($merchantref).'"',
            true,
            Db::ON_DUPLICATE_KEY
        );

        return (bool) $result;
    }

    public static function retrieveUnfinishedQuery()
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('swedbank_order_status');
        $where =  "date_created < '".date("Y-m-d H:i:s", strtotime ("-15 minutes"))."' AND date_created > '".date("Y-m-d H:i:s", strtotime ("-6 hour"))."' AND pay_status = 0 ";

        $query->where($where);
        //$query->where('date_created < "'.date("Y-m-d H:i:s", strtotime ("+6 hour")).'"');
        //$query->where('pay_status = 0');

        $result = Db::getInstance()->executeS($query);

        return $result;
    }

    public static function retrieveQuery($mref)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('swedbank_order_status');
        $where =  "merchant_ref = '".pSQL($mref)."' ";
        $query->where($where);

        $result = Db::getInstance()->executeS($query);

        return $result;
    }


    public static function retrieveStatus($merchantref)
    {
        $query = new DbQuery();
        $query->select('pay_status');
        $query->from('swedbank_order_status');
        $where =  "merchant_ref = '".pSQL($merchantref)."' ";

        $query->where($where);

        $result = Db::getInstance()->getValue($query);

        return $result;
    }

}
