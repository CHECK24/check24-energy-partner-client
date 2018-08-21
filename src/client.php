<?php

    namespace check24\energy\partner\client;

    /**
     * Class client for CHECK24 Vergleichsportal Energie GmbH partner middleware
     *
     * @author Tobias Albrecht
     * @copyright CHECK24 Vergleichsportal Energie GmbH
     * @version 1.0
     */
    class client {

        const BASE_URL = 'https://partnerproxy.energie.check24.de/partner/';
        const COOKIE_PREFIX = 'C24_';
        const CLIENT_VERSION = 1.3;

        const PRODUCT_POWER = 'strom';
        const PRODUCT_GAS = 'gas';

        private $partner_id;
        private $secret;
        private $tracking_id;

        /**
         * @var request
         */
        private $request;

        /**
         * Client constructor
         *
         * @param integer $partner_id Partner id
         * @param string $secret Secret
         * @param string $tracking_id Tracking ID
         * @param request $request Pass optionally request object. This is used for get cookies, hostname e.g.
         */
        public function __construct($partner_id, $secret, $tracking_id, request $request = null) {

            if (!is_int($partner_id)) {
                throw new \InvalidArgumentException('Invalid partner "' . $partner_id . '"');
            }

            if (!is_string($secret) || strlen($secret) < 40) {
                throw new \InvalidArgumentException('Invalid secret "' . $secret . '"');
            }

            if (!is_string($tracking_id)) {
                throw new \InvalidArgumentException('Invalid tracking id "' . $tracking_id . '"');
            }

            $this->partner_id = $partner_id;
            $this->secret = $secret;
            $this->tracking_id = $tracking_id;

            if ($request === null) {
                $request = new request();
            }

            $this->request = $request;

        }

        /**
         * Get partner id
         *
         * @return int
         */
        public function get_partner_id() {
            return $this->partner_id;
        }

        /**
         * Get tracking id
         *
         * @return string
         */
        public function get_tracking_id() {
            return $this->tracking_id;
        }

        /**
         * Handle
         *
         * @param string $product Product (@see self::PRODUCT_GAS, self::PRODUCT_POWER)
         * @param string $style Style
         * @param array $presets Optional presets (zipcode, totalconsumption e.g.)
         * @return response
         */
        public function handle($product, $style, array $presets = []) {

            if (!in_array($product, [self::PRODUCT_GAS, self::PRODUCT_POWER])) {
                throw new \InvalidArgumentException('Invalid product "' . $product . '"');
            }

            if (!is_string($style)) {
                throw new \InvalidArgumentException('Invalid style "' . $style . '"');
            }

            $ch = $this->create_curl_request(
                self::BASE_URL,
                $product,
                $style,
                $presets
            );

            $http_response = curl_exec($ch);
            
            // If curl request failed

            $json_response = [];

            if ($http_response == '') {
                $json_response = ['header' => ['Content-Type' => 'text/html'], 'cookies' => [], 'data' => '<html><head></head><body>Failed request</body></html>'];
            } else {
                $json_response = json_decode($http_response, true);
            }

            if ($json_response == false) {
                $json_response = ['header' => ['Content-Type' => 'text/html'], 'cookies' => [], 'data' => '<html><head></head><body>Failure while parsing request. try again later</body></html>'];
            }

            $response = $this->create_response();

            $response->set_status_code(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $response->set_header($json_response['header']);
            $response->set_cookies($json_response['cookies']);
            $response->set_data($json_response['data']);

            $response->set_cookies($json_response['cookies']);

            return $response;

        }

        /**
         * Create curl request
         *
         * @param string $url Url
         * @param string $product Product
         * @param string $style Style
         * @param array $presets Presets
         * @return resource
         */
        private function create_curl_request($url, $product, $style, array $presets) {

            // Create curl

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);            
            curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
            curl_setopt($ch, CURLOPT_TCP_NODELAY, true);

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($this->create_api_request_parameter($product, $style, $presets))
            );

            return $ch;

        }

        /**
         * Create api request parameter
         *
         * @param string $product Product
         * @param string $style Style
         * @param array $presets Presets
         * @return array
         */
        private function create_api_request_parameter($product, $style, array $presets) {

            $request = [

                'config' => [
                    'partner_id' => $this->get_partner_id(),
                    'secret' => $this->secret,
                    'current_time' => time(),
                    'tracking_id' => $this->get_tracking_id(),
                    'http_host' => $this->request->get_http_host(),
                    'server_name' => $this->request->get_server_name(),
                    'current_request_uri' => $this->get_current_uri()
                ],

                'customer' => [
                    'ip' => $this->request->get_client_ip(),
                    'user_agent' => $this->request->get_user_agent(),
                    'version' => self::CLIENT_VERSION,
                ],

                'request' => [
                    'product' => $product,
                    'style' => $style,
                    'method' => $this->request->get_request_method(),
                    'get' => $_GET,
                    'post' => $_POST,
                    'cookies' => $this->get_clean_cookies(),
                    'x_header' => $this->request->get_additional_x_header(),
                    'presets' => $presets,
                    'server' => $_SERVER
                ]

            ];

            return $request;

        }

        /**
         * Get clean cookies
         * Means we select only cookies with our prefix
         *
         * @return array
         */
        private function get_clean_cookies() {

            $cookies = $this->request->get_cookies();
            $cookies_clean = [];

            foreach ($cookies AS $name => $value) {

                if (strpos($name, self::COOKIE_PREFIX) === 0) {
                    $cookies_clean[substr($name, strlen(self::COOKIE_PREFIX))] = $value;
                }

            }

            return $cookies_clean;

        }

        /**
         * Create new response object
         *
         * @return response
         */
        protected function create_response() {
            return new response($this->request);
        }

        /**
         * Get current uri
         *
         * @return string
         */
        protected function get_current_uri() {
            return '?';
        }

    }
