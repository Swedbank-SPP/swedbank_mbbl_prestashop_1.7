<?php

class Swedbank_mbblValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */

    public $module;

    public function postProcess()
    {

        $swedbank = Module::getInstanceByName('swedbank_mbbl');

        if (Tools::getValue('swedbankv2') === 'doneB') {

            $config = new Configuration();
            include_once __DIR__. '/../../includes/logger.php';
            $log = new Swedbank_Client_Logger();

            try{

                require __DIR__ . '/../../includes/mbbl/Protocol/Protocol.php';

                $lng = $_GET['lnv'];

                $protocol = new Protocol(
                    trim($config->get('swedbank_seller_id_lt')), // seller ID (VK_SND_ID)
                    trim($config->get('swedbank_privatekey_lt')), // private key
                    '', // private key password, leave empty, if not neede
                    trim($config->get('swedbank_publickey_lt')), // public key
                    '' // return url
                );

                require __DIR__ . '/../../includes/mbbl/Banklink.php';
                $banklink = new Banklink($protocol);

                $config->get('swedbank_debuging') ? $log->logData('POST: '.print_r($_POST, true)) : null;
                $config->get('swedbank_debuging') ? $log->logData('GET: '.print_r($_GET, true)) : null;

                $r = $banklink->handleResponse(empty($_POST) ? $_GET : $_POST);

                $cart = new Cart(Tools::getValue('order_id'));

                $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

                $idOrderStatus = null;
                $mailV = [];

                include_once __DIR__ . '/../../src/Entity/SwedbankOrderStatus.php';

                $paymentStatus = new SwedbankOrderStatus();
                $statusPayment = $paymentStatus->retrieveStatus(Tools::getValue('order_id'));

                if ($r->wasSuccessful()) {
                    if ((int)$statusPayment === 0) {
                        include_once __DIR__ . '/../../src/Entity/SwedbankOrderStatus.php';

                        $paymentStatus = new SwedbankOrderStatus();
                        $paymentStatus->updateItem(Tools::getValue('order_id'), 1);


                        $order_id = Order::getOrderByCartId((int)$cart->id);
                        $history = new OrderHistory();
                        $history->id_order = (int)$order_id;

                        if($history->id_order_state != (int)Configuration::get('SB_ORDER_STATUS_SUCCESS')){
                            $history->changeIdOrderState((int)Configuration::get('SB_ORDER_STATUS_SUCCESS'), (int)$order_id, true);

                        }
                        //$history->sendEmail((int)$order_id);
                        try {
                            $history->addWithemail();
                        } catch (Exception $ex){

                        }

                        $history->save();

                        //$swedbank->validateOrder($cart->id, (int)$idOrderStatus, $total, $name, NULL, $mailV, (int)$cart->id_currency, false, $customer->secure_key);
                    }
                    $customer = new Customer((int)$cart->id_customer);
                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $swedbank->id . '&id_order=' . $swedbank->currentOrder . '&key=' . $customer->secure_key);
                } else {
                    $this->errors = $this->module->l('Payment option failed. Please try later or choose another payment option.');
                    $this->redirectWithNotifications('index.php?controller=order&step=3');
                }
            } catch (Exception $exception){
                $config->get('swedbank_debuging') ? $log->logData('Exception: '.print_r($exception, true)) : null;
                $this->errors = $this->module->l('Something went wrong. Please contact merchant to confirm or dismiss your order.');
                $this->redirectWithNotifications('index.php?controller=order&step=3');


            }



        } else {
            $bankType = '';

            $cart = $this->context->cart;
            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
                $this->errors = $this->module->l('Payment Error: please try later.');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
            $authorized = false;
            foreach (Module::getPaymentModules() as $module) {
                if ($module['name'] == 'swedbank_mbbl') {
                    $authorized = true;
                    break;
                }
            }

            if (!$authorized) {
                $this->errors = $this->module->l('This payment method is not available.', 'validation');
                $this->redirectWithNotifications('index.php?controller=order&step=3');
            }

            $address = new Address(intval($this->context->cart->id_address_delivery));
            $address->email = $this->context->customer->email;
            $address->ip = Tools::getRemoteAddr();

            $countries = Country::getCountries($this->context->language->id, true);
            $address->countries = $countries;

            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

            $config = new Configuration();

            $bankTypeBic = explode('_', $_REQUEST[0])[3];
            $bankTypeLng = explode('_', $_REQUEST[0])[4];

            $selectedType = null;
            //-----------------
            $listJ = @$config->get('swedbank_bank_list_json');
            $json = isset($listJ) ? $listJ : '';

            try {
                $json = json_decode($json);
                foreach ($json as $list){
                    if($list->bic === $bankTypeBic && $list->country === $bankTypeLng){
                        $selectedType = $list;
                    }
                }

            } catch (Exception $ex){

            }

            if(!isset($selectedType)){
                foreach ($json as $list){
                    if($list->country === $bankTypeLng){
                        $selectedType = $list;
                        break;
                    }
                }
            }

//----------------

            $cart->save();

            $idOrderStatus = Configuration::get('SB_ORDER_STATUS_AWAITING');
            $customer = new Customer((int)$cart->id_customer);
            $swedbank->validateOrder($cart->id, (int)$idOrderStatus, $total, $selectedType->name->en, NULL, array(), (int)$cart->id_currency, false, $customer->secure_key);

            // $order_id = Order::getOrderByCartId($cart->id);
            $order = new Order(Order::getOrderByCartId($cart->id));
            $ref = $order->reference;
            //-------------------------------------
            require __DIR__ . '/../../includes/mbbl/Protocol/Protocol.php';

                $lng = $_REQUEST[1];

                $protocol = new Protocol(
                    trim($config->get('swedbank_seller_id_lt')), // seller ID (VK_SND_ID)
                    $config->get('swedbank_privatekey_lt'), // private key
                    '', // private key password, leave empty, if not neede
                    $config->get('swedbank_publickey_lt'), // public key
                    $this->context->link->getModuleLink('swedbank_mbbl', 'validation', array(), true) . '?swedbankv2=doneB&order_id=' . $this->context->cart->id . '&pmmm=c&t=' . $_REQUEST[2] . '&lnv=' . $_REQUEST[1] . '&re=' . $ref
                );

                require __DIR__ . '/../../includes/mbbl/Banklink.php';
                if ($lng == 'ee')
                    $lng = 'et';

                $banklink = new Banklink($protocol, '', '', $selectedType->url);

                switch (strtolower($this->context->language->iso_code)) {
                    case 'en':
                        $lnv = 'ENG';
                        break;
                    case 'lt':
                        $lnv = 'LIT';
                        break;
                    case 'ee':
                        $lnv = 'EST';
                        break;
                    case 'et':
                        $lnv = 'EST';
                        break;
                    case 'ru':
                        $lnv = 'RUS';
                        break;
                    default:
                        $lnv = 'ENG';
                }
                //if($lng == 'et'){
                    $ordM = 'Order Nr: ' . $ref;
                    $ref = $ref;
                /*} else{
                    $ordM = 'Order Nr: ' . $ref;
                }*/

                $request = $banklink->getPaymentRequest($ref, $total, $ordM, $lnv);

                include_once __DIR__. '/../../includes/logger.php';
                $log = new Swedbank_Client_Logger();
                $config->get('swedbank_debuging') ? $log->logData(print_r($request->getRequestData(), true)) : null;

//echo $request->getRequestUrl();
                echo '
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <script type="text/javascript">
        function closethisasap() {
            document.forms["redirectpost"].submit();
        }
    </script>
<body onload="closethisasap();">
<form method="POST" name="redirectpost" action="' . $request->getRequestUrl() . '">

    ' . $request->getRequestInputs() . '
    <input type="submit" style="display: none;" value="Pay" />
</form>
</body>
</html>';
                die;




        }
    }


}
