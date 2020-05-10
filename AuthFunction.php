<?php
/**
 * SNAuth Plugin
 *
 * @copyright  Copyright (c) 2017 newsn (https://newsn.net)
 * @license    GNU General Public License 2.0
 * 
 */
class SNAuth_AuthFunction {
    private $debug=false;
    private $http_info=[];
    private $http_code="";
    private $url="";
    public function oAuthRequest($url, $method = "GET", $parameters = [], $multi = false) {
        switch ($method) {
            case 'GetWithHeader':
                return $this->http($url,'GET', NULL, $parameters);
            case 'GET':
                $url = $url . '?' . http_build_query($parameters);
                return $this->http($url, 'GET');
            default:
                $headers = array();
                if (!$multi && (is_array($parameters) || is_object($parameters))) {
                    $body = http_build_query($parameters);
                } else {
                    $body = self::build_http_query_multi($parameters);
                    $headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
                }
                return $this->http($url, $method, $body, $headers);
        }
    }

    public function http($url, $method, $postfields = NULL, $headers = array()) {
        $this->http_info = array();
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        //https://developer.github.com/v3/#user-agent-required
        curl_setopt($ci, CURLOPT_USERAGENT, "newsn.net oauth client");
        //curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
        //curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ci, CURLOPT_ENCODING, "");
        //curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
        if (version_compare(phpversion(), '5.4.0', '<')) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 1);
        } else {
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 2);
        }
        //curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
        curl_setopt($ci, CURLOPT_HEADER, FALSE);
        switch ($method) {
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, TRUE);
                if (!empty($postfields)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
                    $this->postdata = $postfields;
                }
                break;
            case 'DELETE':
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if (!empty($postfields)) {
                    $url = "{$url}?{$postfields}";
                }
        }
        $headers[] = "Accept: application/json";
        //$headers[]="Accept: application/xml";
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE);
        $response = curl_exec($ci);
        $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        $this->http_info = array_merge($this->http_info, curl_getinfo($ci));
        $this->url = $url;
        if ($this->debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);
            echo "=====headers======\r\n";
            print_r($headers);
            echo '=====request info=====' . "\r\n";
            print_r(curl_getinfo($ci));
            echo '=====response=====' . "\r\n";
            print_r($response);
        }
        curl_close($ci);
        return $response;
    }

    public function object2array(&$object) {
        $object = json_decode(json_encode($object), true);
        return $object;
    }

}
