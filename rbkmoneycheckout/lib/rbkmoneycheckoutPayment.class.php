<?php


/**
 * @author RBKmoney
 * @name RBKmoneyCheckout
 * @description RBKmoney payment module
 * @link https://developers.webasyst.ru/cookbook/plugins/payment-plugins/
 *
 * Plugin settings parameters must be specified in file lib/config/settings.php
 * @property-read string $shop_id
 * @property-read string $api_key
 * @property-read string $webhook_key
 * @property-read string $cart_checkboks
 * @property-read string $payform_path_logo
 * @property-read string $payform_button_label
 * @property-read string $payform_description
 * @property-read string $payform_company_name
 * @property-read string $payform_button_css
 *
 * @see https://rbkmoney.github.io/docs/
 * @see https://rbkmoney.github.io/api/
 * @see https://rbkmoney.github.io/webhooks-events-api/
 */
class rbkmoneycheckoutPayment extends waPayment implements waIPayment
{
    /**
     * URL for interaction
     */
    const CHECKOUT_URL = 'https://checkout.rbk.money/checkout.js';
    const COMMON_API_URL = 'https://api.rbk.money/v2/';

    /**
     * Create invoice settings
     */
    const CREATE_INVOICE_TEMPLATE_DUE_DATE = 'Y-m-d\TH:i:s\Z';
    const CREATE_INVOICE_DUE_DATE = '+1 days';

    /**
     * Constants for Callback
     */
    const SIGNATURE = 'HTTP_CONTENT_SIGNATURE';
    const SIGNATURE_ALG = 'alg';
    const SIGNATURE_DIGEST = 'digest';
    const SIGNATURE_PATTERN = '|alg=(\S+);\sdigest=(.*)|i';

    /**
     * HTTP CODE
     */
    const HTTP_CODE_OK = 200;
    const HTTP_CODE_CREATED = 201;
    const HTTP_CODE_MOVED_PERMANENTLY = 301;
    const HTTP_CODE_BAD_REQUEST = 400;
    const HTTP_CODE_INTERNAL_SERVER_ERROR = 500;

    /**
     * Openssl verify
     */
    const OPENSSL_VERIFY_SIGNATURE_IS_CORRECT = 1;
    const OPENSSL_VERIFY_SIGNATURE_IS_INCORRECT = 0;
    const OPENSSL_VERIFY_ERROR = -1;
    const OPENSSL_SIGNATURE_ALG = OPENSSL_ALGO_SHA256;

    /**
     * Order ID
     * @var string
     */
    private $order_id;

    /**
     * @var string
     */
    private $pattern = '/^(\w[\w\d]+)_([\w\d]+)_(.+)$/';

    /**
     * Template
     * @var string
     */
    private $template = '%s_%s_%s';

    /**
     * Returns array of ISO3 codes of enabled currencies (from settings) supported by payment gateway.
     *
     * @return string[]
     */
    public function allowedCurrency()
    {
        return array_keys(waCurrency::getAll());
    }

    /**
     * Generates payment form HTML code.
     *
     * Payment form can be displayed during checkout or on order-viewing page.
     * Form "action" URL can be that of the payment gateway or of the current page (empty URL).
     * In the latter case, submitted data are passed again to this method for processing, if needed;
     * e.g., verification, saving, forwarding to payment gateway, etc.
     * @param array $payment_form_data Array of POST request data received from payment form
     * (if no "action" URL is specified for the form)
     * @param waOrder $order_data Object containing all available order-related information
     * @param bool $auto_submit Whether payment form data must be automatically submitted (useful during checkout)
     * @return string Payment form HTML
     * @throws waException
     */
    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
        // using order wrapper class to ensure use of correct data object
        $order = waOrder::factory($order_data);

        $description = str_replace('#', '№', mb_substr($order->description, 0, 255, "UTF-8"));
        $cart_checkboks= trim($this->cart_checkboks);
        if (trim($this->cart_checkboks)==1)
            { 
                $data = array(
                    'shopID' => $this->shop_id,
                    'amount' => $this->prepareAmount($order['amount']),
                    'metadata' => $this->prepareMetadata($order),
                    'dueDate' => $this->prepareDueDate(),
                    'currency' => $order->currency,
                    'product' => $description,
                    'cart' => $this->prepareCart($order),
                    'description' => '',
                            );
            }
        else 
            { 
                $data = array(
                'shopID' => $this->shop_id,
                'amount' => $this->prepareAmount($order['amount']),
                'metadata' => $this->prepareMetadata($order),
                'dueDate' => $this->prepareDueDate(),
                'currency' => $order->currency,
                'product' => $description,
                'description' => '',
                );
            }

        $url = $this->getEndpointUrl() . 'processing/invoices';
        $headers = $this->prepareHeaders($this->api_key);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($curl);
        $info = curl_getinfo($curl);

        if ($info['http_code'] != static::HTTP_CODE_CREATED) {
            $logs = array(
                'request' => array(
                    'method' => 'POST',
                    'url' => $url,
                    'headers' => $headers,
                    'data' => $data,
                ),
                'response' => array(
                    'info' => $info,
                    'body' => $body,
                ),
                'error_message' => 'Произошла ошибка при создании инвойса'
            );
            $this->logger($logs);
            throw new waException('Что-то пошло не так! Мы уже знаем и работаем над этим!');
        }

        $transaction_data = array(
            'order_id' => $order->id,
        );

        $response = json_decode($body, true);

        $dataCheckout = array();
        $companyName = $this->payform_company_name;
        if (!empty($companyName)) {
            $dataCheckout["data-name"] = $companyName;
        }

        $payFormDescription = $this->payform_description;
        if (!empty($payFormDescription)) {
            $dataCheckout["data-description"] = $payFormDescription;
        }

        $payFormPayButtonLabel = $this->payform_button_label;
        if (!empty($payFormPayButtonLabel)) {
            $dataCheckout["data-pay-button-label"] = $payFormPayButtonLabel;
        }

        $payFormLabel = $this->payform_label;
        if (!empty($payFormLabel)) {
            $dataCheckout["data-label"] = $payFormLabel;
        }

        $contact = $order->getContactField('email');
        if (!empty($contact)) {
            $dataCheckout["data-email"] = $contact;
        }

        $dataCheckout["data-invoice-id"] = ifset($response["invoice"]["id"]);
        $dataCheckout["data-invoice-access-token"] = ifset($response["invoiceAccessToken"]["payload"]);

        $view = wa()->getView();
        $view->assign(
            array(
                'form_url' => $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data),
                'checkout_url' => static::CHECKOUT_URL,
                'checkout_params' => $this->prepareCheckoutParams($dataCheckout),
                'order_id' => $order->id,
                'payform_button_css' => $this->payform_button_css,
                'auto_submit' => false,
            )
        );

        // using plugin's own template file to display payment form
        return $view->fetch($this->path . '/templates/payment.html');
    }

    /**
     * Prepare checkout params
     *
     * @param array $dataCheckout
     * @return string
     */
    private function prepareCheckoutParams(array $dataCheckout)
    {
        $separator = '';
        $checkoutParams = '';
        foreach ($dataCheckout as $key => $value) {
            $checkoutParams .= $separator . $key . '="' . $value . '"';
            $separator = ' ';
        }

        return $checkoutParams;
    }

    /**
     * Converts raw transaction data received from payment gateway to acceptable format.
     *
     * @param array $transaction_raw_data Raw transaction data
     * @return array $transaction_data Formalized data
     * @throws waException
     */
    protected function formalizeData($transaction_raw_data)
    {
        $transaction_data = parent::formalizeData($transaction_raw_data);

        $fields = array(
            'invoiceId' => ifset($transaction_raw_data['invoice']['id']),
            'amount' => ifset($transaction_raw_data['invoice']['amount']),
            'currency' => ifset($transaction_raw_data['invoice']['currency']),
        );

        $view_data = array();
        foreach ($fields as $field => $description) {
            $view_data[] = $field . ': ' . $description;
        }

        if ($fields['amount'] <= 0) {

            $logs = array(
                'request' => array(
                    'method' => 'POST',
                    'transaction_data' => $transaction_data,
                    'fields' => $fields,
                    'error_message' => 'Amount is missing',
                ),
            );
            $this->logger($logs);

            throw new waPaymentException($logs['error_message']);
        }

        $transaction_data = array_merge($transaction_data, array(
            'type' => null,
            'native_id' => $fields['invoiceId'],
            'amount' => $fields['amount'] / 100,
            'currency_id' => $fields['currency'],
            'result' => 1,
            'order_id' => $this->order_id,
            'view_data' => implode("\n", $view_data),
        ));

        $allowedEventTypes = array('InvoicePaid', 'InvoiceCancelled');
        if (in_array($transaction_raw_data['eventType'], $allowedEventTypes)) {
            $invoiceStatus = $transaction_raw_data['invoice']['status'];

            if ($invoiceStatus == 'cancelled') {
                $transaction_data['state'] = self::STATE_CANCELED;
                $transaction_data['type'] = self::OPERATION_CANCEL;
            }

            if ($invoiceStatus == 'paid') {
                $transaction_data['state'] = self::STATE_CAPTURED;
                $transaction_data['type'] = self::OPERATION_CAPTURE;
            }

        }

        return $transaction_data;
    }

    /**
     * @return string Payment gateway's callback URL
     */
    private function getEndpointUrl()
    {
        return static::COMMON_API_URL;
    }

    /**
     * Plugin initialization for processing callbacks received from payment gateway.
     *
     * To process callback URLs of the form /payments.php/paypal/*,
     * corresponding app and id must be determined for correct initialization of plugin settings.
     * @param array $request Request data array ($_REQUEST)
     * @return waPayment
     * @throws waPaymentException
     */
    protected function callbackInit($request)
    {
        $content = file_get_contents('php://input');
        $data = json_decode($content, TRUE);
        $orderId = isset($data['invoice']['metadata']['order_id']) ? $data['invoice']['metadata']['order_id'] : "";

        // parsing data to obtain order id as well as ids of corresponding app and plugin setup instance responsible
        // for callback processing
        if (preg_match($this->pattern, ifset($orderId), $matches)) {
            $this->app_id = $matches[1];
            $this->merchant_id = $matches[2];
            $this->order_id = $matches[3];
        } else {

            $logs = array(
                'request' => $request,
                'content' => $content,
                'orderId' => $orderId,
                'error_message' => 'Invalid invoice number',
            );

            $this->logger($logs);

            throw new waPaymentException($logs['error_message']);
        }

        // calling parent's method to continue plugin initialization
        return parent::callbackInit($request);
    }

    /**
     * Actual processing of callbacks from payment gateway.
     *
     * Request parameters are checked and app's callback handler is called, if necessary.
     * Plugin settings are already initialized and available.
     * IPN (Instant Payment Notification)
     * @throws waPaymentException
     * @param array $request Request data array ($_REQUEST) received from gateway
     * @return array Associative array of optional callback processing result parameters:
     *     'redirect' => URL to redirect user upon callback processing
     *     'template' => path to template to be used for generation of HTML page displaying callback processing results;
     *                   false if direct output is used
     *                   if not specified, default template displaying message 'OK' is used
     *     'header'   => associative array of HTTP headers ('header name' => 'header value') to be sent to user's
     *                   browser upon callback processing, useful for cases when charset and/or content type are
     *                   different from UTF-8 and text/html
     *
     *     If a template is used, returned result is accessible in template source code via $result variable,
     *     and method's parameters via $params variable
     */
    protected function callbackHandler($request)
    {
        $logs = array(
            'request' => array(
                'method' => 'POST',
                'data' => $request,
            ),
        );

        if (empty(waRequest::server(static::SIGNATURE))) {
            $message = 'Webhook notification signature missing';
            $logs['error_message'] = $message;
            return $this->outputWithLogger($message, $logs);
        }

        $paramsSignature = $this->getParametersContentSignature(waRequest::server(static::SIGNATURE));
        if (empty($paramsSignature[static::SIGNATURE_ALG])) {
            $message = 'Missing required parameter ' . static::SIGNATURE_ALG;
            $logs['error_message'] = $message;
            return $this->outputWithLogger($message, $logs);
        }

        if (empty($paramsSignature[static::SIGNATURE_DIGEST])) {
            $message = 'Missing required parameter ' . static::SIGNATURE_DIGEST;
            $logs['error_message'] = $message;
            return $this->outputWithLogger($message, $logs);
        }

        $signature = $this->urlsafeB64decode($paramsSignature[static::SIGNATURE_DIGEST]);
        $content = file_get_contents('php://input');
        $publicKey = trim($this->webhook_key);
        $logs['content'] = $content;
        if (!$this->verificationSignature($content, $signature, $publicKey)) {
            $message = 'Webhook notification signature mismatch';
            $logs['error_message'] = $message;
            return $this->outputWithLogger($message, $logs);
        }


        $data = json_decode($content, TRUE);
        $currentShopId = $this->shop_id;
        if ($data['invoice']['shopID'] != $currentShopId) {
            $message = 'Shop ID is missing';
            $logs['error_message'] = $message;
            return $this->outputWithLogger($message, $logs);
        }

        $orderId = ifset($data['invoice']['metadata']['order_id'], "");
        if (empty($orderId)) {
            $message = 'Order ID is missing';
            $logs['error_message'] = $message;
            return $this->outputWithLogger($message, $logs);
        }

        $transaction_data = $this->formalizeData($data);
        switch (ifset($transaction_data['state'])) {
            case self::STATE_CAPTURED:
                $callback_method = self::CALLBACK_PAYMENT;
                break;
            case self::STATE_CANCELED:
                $callback_method = self::CALLBACK_CANCEL;
                break;
            default:
                $callback_method = null;
        }

        if ($callback_method) {
            $transaction_data = $this->saveTransaction($transaction_data, $request);
            $this->execAppCallback($callback_method, $transaction_data);
        }

        return array(
            'template' => false, // this plugin generates response without using a template
        );
    }

    /**
     * Prepare headers
     *
     * @param $apiKey
     * @return array
     */
    private function prepareHeaders($apiKey)
    {
        $headers = array();
        $headers[] = 'X-Request-ID: ' . uniqid();
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'Content-type: application/json; charset=utf-8';
        $headers[] = 'Accept: application/json';
        return $headers;
    }

    /**
     * Prepare due date
     *
     * @return string
     */
    private function prepareDueDate()
    {
        date_default_timezone_set('UTC');
        return date(static::CREATE_INVOICE_TEMPLATE_DUE_DATE, strtotime(static::CREATE_INVOICE_DUE_DATE));
    }

    /**
     * Prepare amount
     *
     * @param $amount
     * @return mixed
     * @throws waException
     */
    private function prepareAmount($amount)
    {
        if (empty($amount)) {

            $logs = array(
                'amount' => $amount,
                'error_message' => 'Сумма заказа меньше или равна нулю'
            );
            $this->logger($logs);

            throw new waException('Ошибка в сумме заказа. Пожалуйста, сообщите нам об этом.');
        }
        return $amount * 100;
    }

    /**
     * Prepare metadata
     *
     * @param $order
     * @return array
     */
    private function prepareMetadata($order)
    {
        $info = wa()->getAppInfo($this->app_id);
        return array(
            'cms' => sprintf('Webasyst %s', $this->app_id),
            'cms_version' => $info['version'],
            'module' => $this->id,
            'order_id' => sprintf($this->template, $this->app_id, $this->merchant_id, $order->id),
        );
    }

    /**
     * Prepare Cart
     *
     * @param $order
     * @return array
     */
    private function prepareCart($order)
    {
        $items = $this->prepareItemsForCart($order);
        $shipping = $this->prepareShippingForCart($order);
        return array_merge($shipping, $items);
    }

    /**
     * Prepare items for cart
     *
     * @param $order
     * @return array
     */
    private function prepareItemsForCart($order)
    {
        $lines = array();
        foreach ($order->items as $product) {
            $item = array();

            $item['product'] = $product['name'];
            $item['quantity'] = (int)$product['quantity'];

            $amount = $product['price'] - ifset($product['discount'], 0.0);
            if ($amount <= 0) {
                continue;
            }

            $price = number_format($amount, 2, '.', '');
            $item['price'] = $this->prepareAmount($price);

            if (!empty($product['tax_rate'])) {
                $taxMode = array(
                    'type' => 'InvoiceLineTaxVAT',
                    'rate' => $this->getTaxRate($product['tax_rate']),
                );

                $item['taxMode'] = $taxMode;
            }

            $lines[] = $item;
        }

        return $lines;
    }

    /**
     * Prepare shipping for cart
     *
     * @param $order
     * @return array
     */
    private function prepareShippingForCart($order)
    {
        $lines = array();
        if ($order->shipping > 0) {
            $item = array();

            $item['product'] = $order->shipping_name;
            $item['quantity'] = 1;

            $price = number_format($order->shipping, 2, '.', '');
            $item['price'] = $this->prepareAmount($price);


            if (!empty($order->shipping_tax_rate)) {
                $taxMode = array(
                    'type' => 'InvoiceLineTaxVAT',
                    'rate' => $this->getTaxRate($order->shipping_tax_rate),
                );

                $item['taxMode'] = $taxMode;
            }

            $lines[] = $item;
        }

        return $lines;
    }

    /**
     * Get tax rate
     *
     * @param $rate
     * @return null|string
     */
    private function getTaxRate($rate)
    {
        switch ($rate) {
            // НДС чека по ставке 0%;
            case 0:
                return '0%';
                break;
            // НДС чека по ставке 10%;
            case 10:
                return '10%';
                break;
            // НДС чека по ставке 18%;
            case 18:
                return '18%';
                break;
            default: # — без НДС;
                return null;
                break;
        }
    }

    /**
     * URL safe Base64 decode
     *
     * @param $string
     * @return bool|string
     */
    private function urlsafeB64decode($string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    /**
     * Get parameters content signature
     *
     * @param $contentSignature
     * @return array
     */
    private function getParametersContentSignature($contentSignature)
    {
        preg_match_all(static::SIGNATURE_PATTERN, $contentSignature, $matches, PREG_PATTERN_ORDER);
        $params = array();
        $params[static::SIGNATURE_ALG] = !empty($matches[1][0]) ? $matches[1][0] : '';
        $params[static::SIGNATURE_DIGEST] = !empty($matches[2][0]) ? $matches[2][0] : '';
        return $params;
    }

    /**
     * Verification signature
     *
     * @param string $data
     * @param string $signature
     * @param string $public_key
     * @return bool
     */
    private function verificationSignature($data = '', $signature = '', $public_key = '')
    {
        if (empty($data) || empty($signature) || empty($public_key)) {
            return FALSE;
        }
        $public_key_id = openssl_get_publickey($public_key);
        if (empty($public_key_id)) {
            return FALSE;
        }
        $verify = openssl_verify($data, $signature, $public_key_id, static::OPENSSL_SIGNATURE_ALG);
        return ($verify == static::OPENSSL_VERIFY_SIGNATURE_IS_CORRECT);
    }

    /**
     * Output
     *
     * @param $message
     * @param int $httpCode
     */
    function outputWithLogger($message, &$logs, $httpCode = self::HTTP_CODE_BAD_REQUEST)
    {
        http_response_code($httpCode);
        $this->logger($logs);
        echo json_encode(array('message' => $message));
        return;
    }

    /**
     * Logger
     *
     * @param $logs
     */
    private function logger($logs)
    {
        self::log($this->id, $logs);
    }

}
