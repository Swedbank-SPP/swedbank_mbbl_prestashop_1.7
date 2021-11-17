<?php


class SwedbankLogfileModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $fileName = $this->module->getLocalPath().'var/logs/swedbank.log';
        $this->sendHttpHeaders($fileName);
        die;
    }

    /**
     * Send HTTP header to force file download
     *
     * @param string $fileName
     */
    private function sendHttpHeaders($fileUrl)
    {
        if (ob_get_level() && ob_get_length() > 0) {
            ob_clean();
        }

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileUrl) . "\"");
        readfile($fileUrl);
    }
}
