<?php


use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
//use DB;
//use ModuleAdminController;
//use OrderState;
//use Collection;
//use Tools;
//use Validate;

if (!defined('_PS_VERSION_')) {
    exit;
}


class Swedbank_mbbl extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;


    public function __construct()
    {
        $this->name = 'swedbank_mbbl';
        $this->tab = 'payments_gateways';
        $this->version = '3.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Darius Augaitis';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Swedbank MBBL');
        $this->description = $this->l('Swedbank ecommerce payment module with Swedbank bank link payment initiation');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function hookActionGetExtraMailTemplateVars(array &$params)
    {


        $template = $params['template'];
        if ('payment' != $template) {
            return;
        }

        $idOrder = $params['template_vars']['{id_order}'];
        $order = new Order($idOrder);

        if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
            return;
        }

        $params['extra_template_vars']['{swedbank_html_block}'] = '';
        $params['extra_template_vars']['{swedbank_txt_block}'] = '';

        include_once __DIR__ . '/src/Entity/SwedbankCardPaymentData.php';

        $cardData = new PrestaShopCollection('SwedbankCardPaymentData');
        $cardData->where('id_order', '=', $order->id);
        $cardData = $cardData->getFirst();

        if (!$cardData instanceof SwedbankCardPaymentData) {
            return;
        }
        $tplVars = [
            'pan' => $cardData->pan,
            'expiry_date' => $cardData->expiry_date,
            'authorization_code' => $cardData->authorization_code,
            'merchant_reference' => $cardData->merchant_reference,
            'fullfil_date' => $cardData->fulfill_date,
        ];

        $this->context->smarty->assign($tplVars);

        $params['extra_template_vars']['{swedbank_html_block}'] = $this->context->smarty->fetch(
            $this->getLocalPath().'views/templates/hook/actionGetExtraMailTemplateVars.tpl'
        );
        $params['extra_template_vars']['{swedbank_txt_block}'] = $this->context->smarty->fetch(
            $this->getLocalPath().'views/templates/hook/actionGetExtraMailTemplateVars.txt'
        );
        //file_put_contents('mail.txt', print_r($params, true));
    }

    public function install()
    {
        $installSqlFiles = glob(__DIR__.'/sql/install/*.sql');

        if (!empty($installSqlFiles)) {

            foreach ($installSqlFiles as $sqlFile) {
                $sqlStatements = Tools::file_get_contents($sqlFile);
                $sqlStatements = str_replace('PREFIX_', _DB_PREFIX_, $sqlStatements);
                $sqlStatements = str_replace('ENGINE_TYPE', _MYSQL_ENGINE_, $sqlStatements);

                if (!Db::getInstance()->execute($sqlStatements)) {
                    file_put_contents('aaa.txt', 'Failed to execute SQL in file `%s`');
                    throw new Exception(sprintf('Failed to execute SQL in file `%s`', $sqlFile));
                }
            }

        }

        $this->addOrderState('Awaiting payment');
        $this->addOrderState('Payment requires investigation');

        if (!parent::install()  || !$this->registerHook('paymentOptions') || !$this->registerHook('actionGetExtraMailTemplateVars')) {
            return false;
        }
        return true;
    }

    public function addOrderState($name)
    {
        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);

        // check if order state exist
        foreach ($states as $state) {
            if (in_array($name, $state)) {
                $state_exist = true;
                break;
            }
        }

        // If the state does not exist, we create it.
        if (!$state_exist) {
            // create new order state
            $order_state = new OrderState();
            $order_state->color = '#00ffff';
            $order_state->send_email = false;
            $order_state->module_name = 'Swedbank';
            //$order_state->template = '';
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[ $language['id_lang'] ] = $name;

            // Update object
            $order_state->add();

        }

        return true;
    }

    public function hookPaymentOptions()
    {

        $payment_options = [];

        if (!$this->active || !$this->isConfigured()) {
            return [];
        }

        $cartCurrency = new Currency($this->context->cart->id_currency);
        if ('EUR' != $cartCurrency->iso_code) {
            return [];
        }

        $lnv = '';

        switch (strtolower($this->context->language->iso_code)) {
            case 'en':
                $lnv = 'en';
                break;
            case 'lt':
                $lnv = 'lt';
                break;
            case 'ee':
                $lnv = 'et';
                break;
            case 'et':
                $lnv = 'et';
                break;
            case 'ru':
                $lnv = 'ru';
                break;
            default:
                $lnv = 'en';
        }

        $desk = '';

        if(!empty(Configuration::get('swedbank_seller_id_lt')) && !empty(Configuration::get('swedbank_privatekey_lt')) && !empty(Configuration::get('swedbank_publickey_lt')) ) {

            //$option = $this->getLTMBBLSwedbankOption('lt');
            //-----------------------------------

            //swedbank_bank_list_json
            $json = Tools::getValue('swedbank_bank_list_json', Configuration::get('swedbank_bank_list_json'));
            $pList = [];

            try {

                $json = json_decode($json);
                if(!empty($json)){
                    foreach ($json as $list){
                        if(Configuration::get('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status')){

                            $pList[$list->country][$list->bic] = ['icon'=>$list->logo, 'title'=>$list->name->$lnv, 'id'=>'swedbank_mbbl_v2_'.$list->bic."_".$list->country];


                        }
                    }
                }

            } catch (Exception $ex){

            }

            if(!empty($pList)){
                foreach ($pList as $key => $value){
                   // $gateways['swedbank_mbbl_v2_'.$key] = clone $gateways['swedbank_mbbl_v2'];
                    //$gateways['swedbank_mbbl_v2_'.$key]->icon = false;
                    //$gateways['swedbank_mbbl_v2_'.$key]->id = 'swedbank_mbbl_v2_'.$key;
                    //$gateways['swedbank_mbbl_v2_'.$key]->title = 'Banklink '.(count($pList) > 1 ? '('.$key.')' : '');

                    $i = 1;
                    $desk = '<ul>';
                    $furl = '';
                    foreach ($value as $item){
                        if($i == 1){
                            $furl = $item['id'];
                        }
                        $desk .= '<li style="display: block"><a data-url="'.$this->context->link->getModuleLink($this->name, 'validation', array($item['id'], 'lt', 0), true).'" id="'.$item['id'].'" style="border: 2px solid #fff; border-radius: 8px;padding: 8px; display: inline-block; cursor: pointer; margin-bottom: 8px;" onclick=" var el = this.parentNode.parentNode.parentNode.getElementsByTagName(\'a\'); Array.prototype.forEach.call(el, function(el){ el.classList.remove(\'SWborder\') }); this.classList.add(\'SWborder\'); var Iid = this.parentNode.parentNode.parentNode.id.split(\'-\');  document.getElementById(\'pay-with-payment-option-\'+Iid[2]+\'-form\').firstChild.nextSibling.action = this.dataset.url;" ><img src="'.$item['icon'].'" alt="'.$item['title'].'" height="24px" style="height: 24px" > </a></li>';
                        $i++;
                    }
                    $desk .= '</ul>
<style> .SWborder{border-color: #eba134!important;}</style>

';
                    $option = new PaymentOption();
                    $option->setCallToActionText('Banklink '.(count($pList) > 1 ? '('.$key.')' : ''))
                        ->setAction($this->context->link->getModuleLink($this->name, 'validation', array($furl, 'lt', 0), true))
                        ->setAdditionalInformation($desk);

                    if ($option) {
                        $payment_options[] = $option;
                    }

                    //$gateways['swedbank_mbbl_v2_'.$key]->description = $desk;

                }
            }

            //$swedbank = Configuration::get('swedbank_'.$lang.'_swedbank_mbbl_status');

            //if($swedbank){

            //var_dump($desk); die;



                //return $cardpayment;
            //}

            //--------------------------


        }

        return $payment_options;
    }

    //

    /**
     * Check if module is configured
     *
     * @return bool
     */
    public function isConfigured()
    {

        if(!Configuration::get('swedbank_status'))
            return false;

        $euro = Currency::getIdByIsoCode('EUR', $this->context->shop->id);

        if (!$euro) {
            return false;
        } else return  true;

        //return $lt || $lv || $ee  ? true : false;
    }

    protected function generateForm()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }

        $years = [];
        for ($i = 0; $i <= 10; $i++) {
            $years[] = date('Y', strtotime('+'.$i.' years'));
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'months' => $months,
            'years' => $years,
        ]);

        return $this->context->smarty->fetch($this->getLocalPath().'/views/templates/front/payment_form.tpl');
    }

    //------------------------------------------- ADMIN PART -------------------------------------------------------

    public function getContent()
    {

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->context->smarty->assign([
            'notificationUrl' => $this->context->link->getModuleLink(
                $this->name,
                'notification',
                [
                    'secret_key' => Tools::encrypt($this->name)
                ]
            ),
            'cronTaskUrl' => $this->context->link->getModuleLink('swedbank', 'cronjob', array(), true).'?swedbankToken='.
                Tools::encrypt($this->name)
        ]);

        //$this->_html .=$this->display(__FILE__, 'infos.tpl');
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function renderForm()
    {

        $allStates = OrderState::getOrderStates($this->context->language->id);
        $paidFlagStates = $this->getPaidFlagStates($allStates);
        $excludePaidStates = $this->getNonPaidFlagStates($allStates);

        $debugFile = $this->context->link->getModuleLink('swedbank', 'logfile', array(), true);
        $this->context->smarty->assign(
            'termsAndConditionsLink',
            $this->getPathUri().'pdf/terms_and_conditions.pdf'
        );

        $this->context->smarty->assign('debugFileUrl', $debugFile);

        $debugFileHtml = $this->context->smarty->fetch(
            $this->getLocalPath().'views/templates/front/log-file.tpl'
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('General', array(), 'Modules.Swedbank.Admin'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'label' =>
                            $this->context->smarty->fetch($this->getLocalPath().'/views/templates/front/terms-and-conditions-link.tpl'),
                        'name' => 'swedbank_status',
                        'type' => 'switch',
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'activate_on',
                                'value' => true,
                                'label' => $this->l('Yes', 'swedbank')
                            ),
                            array(
                                'id' => 'activate_off',
                                'value' => false,
                                'label' => $this->l('No', 'swedbank')
                            )
                        )
                    ),
                    array(
                        'label' => $this->l('Enable debug mode', 'swedbank'),
                        'type' => 'switch',
                        'name' => 'swedbank_debuging',
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'activate_on',
                                'value' => true,
                                'label' => $this->l('Yes', 'swedbank')
                            ),
                            array(
                                'id' => 'activate_off',
                                'value' => false,
                                'label' => $this->l('No', 'swedbank')
                            )
                        ),
                        'desc' => $debugFileHtml,
                    )
                    ,
                   array(
                        'label' => $this->l('Order status - successful payment', 'swedbank'),
                        'name' => 'SB_ORDER_STATUS_SUCCESS',
                        'type' => 'select',
                        'disabled' => false,
                        'options' => array(
                            'query' => $paidFlagStates,
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ),
                    ),
                   array(
                        'label' => $this->l('Order status - awaiting payment', 'swedbank'),
                        'name' => 'SB_ORDER_STATUS_AWAITING',
                        'type' => 'select',
                        'disabled' => false,
                        'options' => array(
                            'query' => $excludePaidStates,
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ),
                    )

                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );



        $fields_form_lithuania_mbbl = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Swedbank Multi Bank Payment Initiation', array(), 'swedbank'),
                    'icon' => 'icon-cogs'

                ),

                'input' => array(


                    array(
                        'label' => $this->l('Country', 'swedbank'),
                        'name' => 'swedbank_contract_country',
                        'type' => 'select',
                        'disabled' => false,
                        'options' => array(
                            'query' => [['id_option' => 'LT', 'name' => 'LT'], ['id_option' => 'LV', 'name' => 'LV'], ['id_option' => 'EE', 'name' => 'EE']],
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                    ),

                    array(
                        'type' => 'text',
                        'label' => $this->trans('Seller ID', array(), 'swedbank'),
                        'name' => 'swedbank_seller_id_lt',
                    ),

                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Private key', array(), 'swedbank'),
                        'name' => 'swedbank_privatekey_lt',
                    )
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        //---------------------------------------------------------

            if(!empty(Configuration::get('swedbank_seller_id_lt'))) {

                $url = "https://banklink.swedbank.com/public/api/v1/agreements/" . Configuration::get('swedbank_contract_country') . "/" . Configuration::get('swedbank_seller_id_lt') . "/providers";
//var_dump($url);
                $json = file_get_contents($url);
               // print_r($json);
                $certificate = file_get_contents('https://banklink.swedbank.com/public/resources/bank-certificates/009');

                //Configuration::updateValue('swedbank_bank_list', $json);
                Configuration::updateValue('swedbank_publickey_lt', $certificate);



        try {
            Configuration::updateValue('swedbank_bank_list_json', $json);
            $json = json_decode($json);
            if(!empty($json)) {
                foreach ($json as $list) {
                    $fields_form_lithuania_mbbl['form']['input'][] =  array(
                        'label' => $this->l($list->name->en . ' ('.$list->country.')', 'swedbank'),
                        'type' => 'switch',
                        'name' => 'swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status',
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'activate_on',
                                'value' => true,
                                'label' => $this->l('Yes', 'swedbank')
                            ),
                            array(
                                'id' => 'activate_off',
                                'value' => false,
                                'label' => $this->l('No', 'swedbank')
                            )
                        )
                    );
                    $reiksme = Tools::getValue('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status');
                    if(!isset($reiksme)){
                        Configuration::updateValue('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status', 0);
                    }

                    Configuration::updateValue('swedbank_'.$list->bic . "_" . $list->country, json_encode($list));

                }
            }
        } catch (Exception $ex){

        }
            }

//echo '<pre>';
  //          print_r($fields_form_lithuania_mbbl); die;

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_lithuania_mbbl));
    }

    private function getPaidFlagStates(array $allStates)
    {
        $result = [];
        foreach ($allStates as $state) {
            if ($state['paid']) {
                $result[] = $state;
            }
        }

        return $result;
    }

    private function getNonPaidFlagStates(array $allStates)
    {
        $result = [];
        foreach ($allStates as $state) {
            if (!$state['paid']) {
                $result[] = $state;
            }
        }

        return $result;
    }

    public function getConfigFieldsValues()
    {

        $ar = array(

            'swedbank_status' => Tools::getValue('swedbank_status', Configuration::get('swedbank_status')),
            'SB_ORDER_STATUS_REQINV' => Tools::getValue('SB_ORDER_STATUS_REQINV', Configuration::get('SB_ORDER_STATUS_REQINV')),
            'SB_ORDER_STATUS_AWAITING' => Tools::getValue('SB_ORDER_STATUS_AWAITING', Configuration::get('SB_ORDER_STATUS_AWAITING')),
            'SB_ORDER_STATUS_SUCCESS' => Tools::getValue('SB_ORDER_STATUS_SUCCESS', Configuration::get('SB_ORDER_STATUS_SUCCESS')),

            'swedbank_debuging' => Tools::getValue('swedbank_debuging', Configuration::get('swedbank_debuging')),
            'swedbank_lt_card_status' => Tools::getValue('swedbank_lt_card_status', Configuration::get('swedbank_lt_card_status')),
            'swedbank_lt_swedbank_status' => Tools::getValue('swedbank_lt_swedbank_status', Configuration::get('swedbank_lt_swedbank_status')),
            'swedbank_lt_seb_status' => Tools::getValue('swedbank_lt_seb_status', Configuration::get('swedbank_lt_seb_status')),
            'swedbank_lt_dnb_status' => Tools::getValue('swedbank_lt_dnb_status', Configuration::get('swedbank_lt_dnb_status')),
            'swedbank_testmode_lt' => Tools::getValue('swedbank_testmode_lt', Configuration::get('swedbank_testmode_lt')),
            'swedbank_testvtid_lt' => Tools::getValue('swedbank_testvtid_lt', Configuration::get('swedbank_testvtid_lt')),
            'swedbank_testpass_lt' => Tools::getValue('swedbank_testpass_lt', Configuration::get('swedbank_testpass_lt')),
            'swedbank_vtid_lt' => Tools::getValue('swedbank_vtid_lt', Configuration::get('swedbank_vtid_lt')),
            'swedbank_pass_lt' => Tools::getValue('swedbank_pass_lt', Configuration::get('swedbank_pass_lt')),
            'swedbank_contract_country' => Tools::getValue('swedbank_contract_country', Configuration::get('swedbank_contract_country')),

            'swedbank_lt_swedbank_mbbl_status' => Tools::getValue('swedbank_lt_swedbank_mbbl_status', Configuration::get('swedbank_lt_swedbank_mbbl_status')),
            'swedbank_lt_seb_mbbl_status' => Tools::getValue('swedbank_lt_seb_mbbl_status', Configuration::get('swedbank_lt_seb_mbbl_status')),
            'swedbank_lt_citadele_mbbl_status' => Tools::getValue('swedbank_lt_citadele_mbbl_status', Configuration::get('swedbank_lt_citadele_mbbl_status')),
            'swedbank_lt_luminor_mbbl_status' => Tools::getValue('swedbank_lt_luminor_mbbl_status', Configuration::get('swedbank_lt_luminor_mbbl_status')),
            'swedbank_lt_siauliu_mbbl_status' => Tools::getValue('swedbank_lt_siauliu_mbbl_status', Configuration::get('swedbank_lt_siauliu_mbbl_status')),
            'swedbank_lt_medicinos_mbbl_status' => Tools::getValue('swedbank_lt_medicinos_mbbl_status', Configuration::get('swedbank_lt_medicinos_mbbl_status')),

            'swedbank_seller_id_lt' => Tools::getValue('swedbank_seller_id_lt', Configuration::get('swedbank_seller_id_lt')),
            'swedbank_privatekey_lt' => Tools::getValue('swedbank_privatekey_lt', Configuration::get('swedbank_privatekey_lt')),
            'swedbank_generate_but_lt' => Tools::getValue('swedbank_generate_but_lt', Configuration::get('swedbank_generate_but_lt')),

        );

        $json = Tools::getValue('swedbank_bank_list_json', Configuration::get('swedbank_bank_list_json'));

        $json = json_decode($json);
        if(!empty($json)) {
            foreach ($json as $list) {
                $ar['swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status'] = Tools::getValue('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status', Configuration::get('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status'));
            }
        }

        return $ar;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('swedbank_status',
                Tools::getValue('swedbank_status'));
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {

            //Configuration::updateValue('swedbank_status', Tools::getValue('swedbank_status'));
            Configuration::updateValue('SB_ORDER_STATUS_REQINV', Tools::getValue('SB_ORDER_STATUS_REQINV'));
            Configuration::updateValue('SB_ORDER_STATUS_AWAITING', Tools::getValue('SB_ORDER_STATUS_AWAITING'));
            Configuration::updateValue('SB_ORDER_STATUS_SUCCESS', Tools::getValue('SB_ORDER_STATUS_SUCCESS'));
            Configuration::updateValue('swedbank_debuging', Tools::getValue('swedbank_debuging'));

            Configuration::updateValue('swedbank_lt_card_status', Tools::getValue('swedbank_lt_card_status'));
            Configuration::updateValue('swedbank_lt_swedbank_status', Tools::getValue('swedbank_lt_swedbank_status'));
            Configuration::updateValue('swedbank_lt_seb_status', Tools::getValue('swedbank_lt_seb_status'));
            Configuration::updateValue('swedbank_lt_dnb_status', Tools::getValue('swedbank_lt_dnb_status'));
            Configuration::updateValue('swedbank_testmode_lt', Tools::getValue('swedbank_testmode_lt'));
            Configuration::updateValue('swedbank_testvtid_lt', Tools::getValue('swedbank_testvtid_lt'));
            Configuration::updateValue('swedbank_testpass_lt', Tools::getValue('swedbank_testpass_lt'));
            Configuration::updateValue('swedbank_vtid_lt', Tools::getValue('swedbank_vtid_lt'));
            Configuration::updateValue('swedbank_pass_lt', Tools::getValue('swedbank_pass_lt'));
            Configuration::updateValue('swedbank_contract_country', Tools::getValue('swedbank_contract_country'));


            Configuration::updateValue('swedbank_lt_swedbank_mbbl_status', Tools::getValue('swedbank_lt_swedbank_mbbl_status'));
            Configuration::updateValue('swedbank_lt_seb_mbbl_status', Tools::getValue('swedbank_lt_seb_mbbl_status'));
            Configuration::updateValue('swedbank_lt_citadele_mbbl_status', Tools::getValue('swedbank_lt_citadele_mbbl_status'));
            Configuration::updateValue('swedbank_lt_luminor_mbbl_status', Tools::getValue('swedbank_lt_luminor_mbbl_status'));
            Configuration::updateValue('swedbank_lt_siauliu_mbbl_status', Tools::getValue('swedbank_lt_siauliu_mbbl_status'));
            Configuration::updateValue('swedbank_lt_medicinos_mbbl_status', Tools::getValue('swedbank_lt_medicinos_mbbl_status'));
            Configuration::updateValue('swedbank_seller_id_lt', Tools::getValue('swedbank_seller_id_lt'));
            Configuration::updateValue('swedbank_privatekey_lt', Tools::getValue('swedbank_privatekey_lt'));

            Configuration::updateValue('swedbank_generate_but_lt', Tools::getValue('swedbank_generate_but_lt'));

            $json = Tools::getValue('swedbank_bank_list_json', Configuration::get('swedbank_bank_list_json'));

            $json = json_decode($json);
            if(!empty($json)) {
                foreach ($json as $list) {
                    Configuration::updateValue('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status', Tools::getValue('swedbank_'.$list->country.'_'.$list->bic.'_mbbl_status'));
                }
            }

//swedbank_ee_lhv_mbbl_status
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    //------------------------------------------- END ADMIN --------------------------------------------------------

    public function initLogTask(){

        $fileName = $this->getLocalPath().'var/logs/swedbank.log';
        $this->sendHttpHeaders($fileName);
        die;
    }

}
