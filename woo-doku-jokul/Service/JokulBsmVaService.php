<?php

require_once(DOKU_JOKUL_PLUGIN_PATH . '/Common/JokulConfig.php');
require_once(DOKU_JOKUL_PLUGIN_PATH . '/Common/JokulUtils.php');

class JokulBsmVaService {

    public function generated($config, $params)
    {
        $header = array();
        $data = array(
            "order" => array(
                "invoice_number" => $params['invoiceNumber'],
                "amount" => $params['amount']
            ),
            "virtual_account_info" => array(
                "expired_time" => $params['expiryTime'],
                "reusable_status" => $params['reusableStatus'],
                "info1" => $params['info1'],
                "info2" => $params['info2'],
                "info3" => $params['info3'],
            ),
            "customer" => array(
                "name" => trim($params['customerName']),
                "email" => $params['customerEmail']
            ),
            "additional_info" => array (
                "integration" => array (
                    "name" => "woocommerce-plugin",
                    "version" => "1.0.0"
                )
            )
        );

        $this->jokulUtils = new JokulUtils();

        $regId = $this->jokulUtils->guidv4();
        $targetPath= "/bsm-virtual-account/v2/payment-code";
        $dateTime = gmdate("Y-m-d H:i:s");
        $dateTime = date(DATE_ISO8601, strtotime($dateTime));
        $dateTimeFinal = substr($dateTime,0,19)."Z";

        $this->jokulConfig = new JokulConfig();
        $valueEnv = $config['environment'] === 'true'? true: false;
        $getUrl = $this->jokulConfig -> getBaseUrl($valueEnv);
        $url = $getUrl.$targetPath;

        $dataWords = JokulBsmVaService::generateWords($regId, $dateTimeFinal,$targetPath, $config, $data);
        $header['Client-Id'] = $config['client_id'];
        $header['Request-Id'] = $regId;
        $header['Request-Timestamp'] = $dateTimeFinal;
        $header['Request-Target'] = $targetPath;

        $signature = $this->jokulUtils->generateSignature($header, json_encode($data), $config['shared_key']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Signature:'.$signature,
            'Request-Id:'.$regId,
            'Client-Id:'.$config['client_id'],
            'Request-Timestamp:'.$dateTimeFinal,
        
        ));

        $responseJson = curl_exec($ch);

        curl_close($ch);

        if (is_string($responseJson)) {
            return json_decode($responseJson, true);
        } else {
            print_r($responseJson);
        }
    }

    private function generateWords($regId, $dateTime, $targetPath, $config, $body)
    {
        $clearBody = str_replace(array("\r","\n"),array("\\r","\\n"),json_encode($body));

        $body = base64_encode(hash("sha256", $clearBody, True));
        $words ="Client-Id:".$config['client_id'] ."\n". 
                    "Request-Id:".$regId . "\n".
                    "Request-Timestamp:".$dateTime ."\n". 
                    "Request-Target:".$targetPath ."\n".
                    "Digest:".htmlspecialchars_decode($body); 

        return $words;
    }

}

?>