<?php

class Swedbank_Client_Logger {

    public function __construct() {
        
    }

    public function logData($text) {
        
        $text = preg_replace("/<password>(.*)<\/password>/","<password>********</password>", $text);

        file_put_contents(dirname(__FILE__) . '/../var/logs/swedbank.log', date("Y-m-d H:i:s") . "\n-----\n$text\n\n", FILE_APPEND | LOCK_EX);
    }

}
