<?php

namespace LaravelPaygent\MDK;

require __DIR__.'/vendor/autoload.php';

use LaravelPaygent\MDK\Exceptions\InvalidArgumentException;
use PaygentModule\System\PaygentB2BModule;

date_default_timezone_set('Asia/Tokyo');

class Paygent
{
    protected $paygent;

    /*
     *  Initialization
     * @param string $env environment [local、production]
     * @param string $merchant_id merchant_id
     * @param string $connect_id
     * @param string $connect_password
     * @param string $pem
     * @param string $crt
     * @param string $telegram_version
     */
    public function __construct($env, $merchant_id, $connect_id, $connect_password, $pem, $crt, $telegram_version = '1.0')
    {
        if (!in_array(strtolower($env), ['local', 'production'])) {
            throw new InvalidArgumentException('Invalid response env: '.$env);
        }

        // env => [local、production], pem, crt
        $this->paygent = new PaygentB2BModule($env, $pem, $crt);
        $this->paygent->init();

        // merchant_id
        $this->paygent->reqPut('merchant_id', $merchant_id);
        // connect id
        $this->paygent->reqPut('connect_id', $connect_id);
        // connect_password
        $this->paygent->reqPut('connect_password', $connect_password);
        // telegram_version
        $this->paygent->reqPut('telegram_version', $telegram_version);
    }

    /**
     * Pay by credit card
     * @param  [array] $input     [description]
     * @return [type]                 [description]
     */
    public function makeCreditCardPayment($input)
    {
        // Send authorize credit card token request
        $result = $this->sendAuthorizePaymentRequest($input);
        // Return response request if fail
        if (!$result['success']) {
            return $result;
        }
        // Define response data of authorize payment request
        $data = $result['data'];
        // Send sales request
        return self::sendSalesPaymentRequest($data['payment_id']);
    }
    
       /*
     * Pay by credit card
     * @param array $params Payment data
     * @param int split_count number of instalments
     * @param string card_token token
     * @param string trading_id order number
     * @param string payment_amount amount
     * @return array
     */
    public function paySendSubcribe($split_count, $card_token, $trading_id, $payment_amount, $cycle,
                                    $customer_id, $timing, $first_executed)
    {
        //
        $customer_check = $this->user_has_stored_data($customer_id);
        if (!$customer_check['status'] and $customer_check['pay_code']!='P026') {
            $customer_card_id = $customer_check['result_array'][0]['customer_card_id'];
        } else {
            $stored_user_card_data = $this->add_stored_user_data($customer_id, $card_token);
            $customer_card_id = $stored_user_card_data['customer_card_id'];
        }

        $payment_class = '1' === $split_count ? 10 : 61;
        $this->paygent->reqPut('payment_class', $payment_class);
        $this->paygent->reqPut('split_count', $split_count);
        $this->paygent->reqPut('card_token', $card_token);
        $this->paygent->reqPut('trading_id', $trading_id);
        $this->paygent->reqPut('customer_id', $customer_id);
        $this->paygent->reqPut('customer_card_id', $customer_card_id);
        $this->paygent->reqPut('amount', $payment_amount);
        $this->paygent->reqPut('cycle', $cycle);
        $this->paygent->reqPut('timing', $timing);
        $this->paygent->reqPut('first_executed', $first_executed);

        // Payment Types
        $this->paygent->reqPut('telegram_kind', '280');
        // send
        $result = $this->paygent->post();

        // 1 request failed, 0 request succeeded
        if (true !== $result) {
            return ['code' => 1, 'result' => $result];
        } else {
            // After the request is successful, directly confirm the payment
            if ($this->paygent->hasResNext()) {
                $res = $this->paygent->resNext();
            }

            $response = [
                'code' => 0,
                'status' => $this->paygent->getResultStatus(),
                'pay_code' => $this->paygent->getResponseCode(), // 0 for success, 1 for failure, others are specific error codes
                'running_id' => $res['running_id'],
                'detail' => $this->iconv_parse($this->paygent->getResponseDetail())
            ];

            return $response;
        }
    }

    /*
     * post-pay request
     * @param string $trading_id
     * @param string $payment_amount
     * @param string $shop_order_date YmdHis
     * @param string $customer_name_kanji 
     * @param string $customer_name_kana 
     * @param string $customer_email
     * @param string $customer_zip_code zip_code 2740065
     * @param string $customer_address
     * @param string $customer_tel  090-4500-9650
     * @param array $goods_list
     * @param array $goods_list[goods[0]] 
     * @param array $goods_list[goods_price[0]] 
     * @param array $goods_list[goods_amount[0]] 
     */
    public function afterPaySend($trading_id, $payment_amount, $shop_order_date, $customer_name_kanji, $customer_name_kana,
                                 $customer_email, $customer_zip_code, $customer_address, $customer_tel, $goods_list)
    {
        // Payment Type
        $this->paygent->reqPut('telegram_kind', '220');

        $this->paygent->reqPut('trading_id', $trading_id);
        $this->paygent->reqPut('payment_amount', $payment_amount);
        $this->paygent->reqPut('shop_order_date', $shop_order_date);
        $this->paygent->reqPut('customer_name_kanji', $this->iconv_parse2(preg_replace('/\\s+/', '', $this->makeSemiangle($customer_name_kanji))));
        $this->paygent->reqPut('customer_name_kana', $this->iconv_parse2(preg_replace('/\\s+/', '', $this->makeSemiangle($customer_name_kana))));
        $this->paygent->reqPut('customer_email', $this->makeSemiangle($customer_email));
        $this->paygent->reqPut('customer_zip_code', $this->makeSemiangle($customer_zip_code));
        $this->paygent->reqPut('customer_address', $this->iconv_parse2($this->makeSemiangle($customer_address)));
        $this->paygent->reqPut('customer_tel', $this->makeSemiangle($customer_tel));

        foreach ($goods_list as $key => $value) {
            $this->paygent->reqPut('goods['.$key.']', $this->iconv_parse2($this->makeSemiangle($value['goods'])));
            $this->paygent->reqPut('goods_price['.$key.']', $value['goods_price']);
            $this->paygent->reqPut('goods_amount['.$key.']', $value['goods_amount']);
        }

        // ask
        $result = $this->paygent->post();

        if (true !== $result) {
            return ['code' => 1, 'result' => $result];
        } else {
            // request succeeded
            if (!$this->paygent->hasResNext()) {
                return ['code' => 1, 'result' => $result];
            }
            $res = $this->paygent->resNext();

            $response = [
                'code' => 0,
                'status' => $this->paygent->getResultStatus(),
                'pay_code' => $this->paygent->getResponseCode(), // 0 for success, 1 for failure, others are specific error codes
                'payment_id' => $res['payment_id'],
                'detail' => $this->iconv_parse($this->paygent->getResponseDetail()),
            ];

            return $response;
        }
    }

    /*
     * Postpay Cancellation
     * @param string $trading_id
     * @param string $payment_id
     * @return array
     */
    public function afterPayCancel($trading_id = null, $payment_id = null)
    {
        // Payment Types
        $this->paygent->reqPut('telegram_kind', '221');
        // In the case of all transmissions, use the order number
        isset($trading_id) && null != $trading_id ? $this->paygent->reqPut('trading_id', $trading_id) : $this->paygent->reqPut('payment_id', $payment_id);
        $result = $this->paygent->post();

        if (true !== $result) {
            return ['code' => 1, 'result' => $result];
        } else {
            if (!$this->paygent->hasResNext()) {
                return ['code' => 1, 'result' => $result];
            }

            $response = [
                'code' => 0,
                'status' => $this->paygent->getResultStatus(),
                'pay_code' => $this->paygent->getResponseCode(),
                'detail' => $this->iconv_parse($this->paygent->getResponseDetail()),
            ];

            return $response;
        }
    }

    /*
     * Post payment confirmation
     * @param string $delivery_company_code
     * @param string $delivery_slip_no
     * @param string $trading_id 
     * @param string $payment_id
     * @return array
     */
    public function afterPayConfirm($delivery_company_code, $delivery_slip_no, $trading_id = null, $payment_id = null)
    {
        $this->paygent->reqPut('telegram_kind', 222);
        $this->paygent->reqPut('delivery_company_code', intval($delivery_company_code));
        $this->paygent->reqPut('delivery_slip_no', $delivery_slip_no);

        isset($trading_id) && null != $trading_id ? $this->paygent->reqPut('trading_id', $trading_id) : $this->paygent->reqPut('payment_id', $payment_id);

        $result = $this->paygent->post();

        if (true !== $result) {
            return ['code' => 1, 'result' => $result];
        } else {
            if (!$this->paygent->hasResNext()) {
                return ['code' => 1, 'result' => $result];
            }
            $response = [
                'code' => 0,
                'status' => $this->paygent->getResultStatus(),
                'pay_code' => $this->paygent->getResponseCode(),
                'detail' => $this->iconv_parse($this->paygent->getResponseDetail()),
            ];

            return $response;
        }
    }

    /*
     * full-width to half-width
     */
    public function makeSemiangle($str)
    {
        $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
            'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
            'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
            'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
            'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
            'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z',
            '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
            '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
            '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
            '》' => '>',
            '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
            '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
            '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
            '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
            '　' => ' ', '『' => '', '』' => '', '･' => '', );

        return strtr($str, $arr);
    }

    /*
     * transcoding format conversion SHITF_JIS->UTF-8
     * $param string $str
     * return $str
     */
    public function iconv_parse($str)
    {
        return iconv('Shift_JIS', 'UTF-8', $str);
    }

    /*
    * transcoding format conversion UTF-8->SHITF_JIS
    * $param string $str
    * return $str
    */
    public function iconv_parse2($str)
    {
        return iconv('UTF-8', 'Shift_JIS', $str);
    }

    public function getPaygent()
    {
        return $this->paygent;
    }

    private function sendAuthorizePaymentRequest($input = [])
    {
        $paygent = $this->paygent;
        $paygent->reqPut('3dsecure_ryaku', 1);
        $paygent->reqPut('payment_class', 10);
        $paygent->reqPut('card_token', $input['token']);
        $paygent->reqPut('trading_id', $input['trading_id']);
        $paygent->reqPut('payment_amount', $input['payment_amount']);
        $paygent->reqPut('telegram_kind', '020');
        $result = $paygent->post();
        // Log request info and response
        $response = $paygent->resNext();
        self::logPaymentRequestInfo($paygent, null, [
            "response_data" => $response,
        ]);
        // If payment request is fail
        if ($paygent->getResultStatus() == 1) {
            $responseCode = $paygent->getResponseCode();
            $errorMessage = self::iconv_parse($paygent->getResponseDetail());
            return [
                "success" => false,
                'response_code' => $paygent->getResponseCode(),
                'response_message' => self::iconv_parse($paygent->getResponseDetail()),
            ];
        }
        return [
            "success" => true,
            "data" => $response,
        ];
    }

    private function sendSalesPaymentRequest($paymentId)
    {
        $paygent = $this->paygent;
        $paygent->reqPut('telegram_kind', '022');
        $paygent->reqPut('payment_id', $paymentId);
        $result = $paygent->post();
        // Log request info and response
        $response = $paygent->resNext();
        self::logPaymentRequestInfo($paygent, null, [
            "response_data" => $response,
        ]);
        // If payment request is fail
        if ($paygent->getResultStatus() == 1) {
            $responseCode = $paygent->getResponseCode();
            $errorMessage = self::iconv_parse($paygent->getResponseDetail());
            return [
                "success" => false,
                'response_code' => $paygent->getResponseCode(),
                'response_message' => self::iconv_parse($paygent->getResponseDetail()),
            ];
        }
        return [
            "success" => true,
            "data" => $response,
        ];
    }

    /**
     * Log request info and response of the payment request
     * @param  [type] $paygent [description]
     * @param  array  $data    [description]
     * @return [type]          [description]
     */
    private function logPaymentRequestInfo($paygent, $options = [], $data = [])
    {
        $message = ['created_at' => now()->format('Y-m-d H:i:s')];
        $channel = $options['channel'] ?? 'paygent';
        // Add payment request info
        $message['request'] = $paygent->telegramParam;
        // Add payment response info
        $message['response'] = [
            "response_status" => $paygent->getResultStatus(),
            "response_code" => $paygent->getResponseCode(),
            "response_message" => self::iconv_parse($paygent->getResponseDetail()),
            "response_data" => $data['response_data'] ?? [],
        ];
        \Log::channel($channel)->info($message);
    }

    /**
     * Check valid token
     * @param  array  $input [description]
     * @param  string  token
     * @param  string  masked_card_number
     * @param  string  valid_until
     * @param  string  fingerprint
     * @return [type]        [description]
     */
    private function checkValidToken($input = [])
    {
        // Refer to official document of Paygent about token payment
        $token_hash_key = config('services.paygent.token_hash_key');
        $str = "{$input['token']}{$input['masked_card_number']}{$input['valid_until']}{$input['fingerprint']}{$token_hash_key}";
        $hashStr = hash("sha256", $str);
        return !(empty($input['hc']) || $hashStr !== $input['hc']);
    }

    public function makeATM_PaymentRequest($input = [])
    {
        $paygent = $this->paygent;
        $paygent->reqPut('telegram_kind', "010");
        $paygent->reqPut('trading_id', $input['trading_id']);
        $paygent->reqPut('payment_amount', $input['payment_amount']);
        $paygent->reqPut('customer_name', self::iconv_parse2($input['customer_name']));
        $paygent->reqPut('customer_family_name', self::iconv_parse2($input['customer_family_name']));
        $paygent->reqPut('payment_detail', self::iconv_parse2("ファンクラブカイヒ"));
        $paygent->reqPut('payment_detail_kana', self::iconv_parse2("ファンクラブカイヒ"));
        $paygent->reqPut('payment_limit_date', 5);
        $result = $paygent->post();
        // Log request info and response
        $response = $paygent->resNext();
        self::logPaymentRequestInfo($paygent, null, [
            "response_data" => $response,
        ]);
        // If payment request is fail
        if ($paygent->getResultStatus() == 1) {
            $responseCode = $paygent->getResponseCode();
            $errorMessage = self::iconv_parse($paygent->getResponseDetail());
            return [
                "success" => false,
                'response_code' => $paygent->getResponseCode(),
                'response_message' => self::iconv_parse($paygent->getResponseDetail()),
            ];
        }
        return [
            "success" => true,
            "data" => $response,
        ];
    }

    public function makeConvenienceStorePaymentRequest($input = [])
    {
        $paygent = $this->paygent;
        // Default is payment with convenience store number system
        $telegramKind = $input['telegram_kind'] ?? "030";
        $paygent->reqPut('telegram_kind', $telegramKind);
        $paygent->reqPut('trading_id', $input['trading_id']);
        $paygent->reqPut('payment_amount', $input['payment_amount']);
        $paygent->reqPut('customer_tel', str_replace("-", "", $input['customer_tel']));
        $paygent->reqPut('customer_name', self::iconv_parse2($input['customer_name']));
        $paygent->reqPut('customer_family_name', self::iconv_parse2($input['customer_family_name']));
        $paygent->reqPut('payment_limit_date', 5);
        if ($telegramKind == "030") {
            $paygent->reqPut('cvs_company_id', $input['cvs_company_id']);
            $paygent->reqPut('sales_type', 1);
        }
        $result = $paygent->post();
        // Log request info and response
        $response = $paygent->resNext();
        self::logPaymentRequestInfo($paygent, null, [
            "response_data" => $response,
        ]);
        // If payment request is fail
        if ($paygent->getResultStatus() == 1) {
            $responseCode = $paygent->getResponseCode();
            $errorMessage = self::iconv_parse($paygent->getResponseDetail());
            return [
                "success" => false,
                'response_code' => $paygent->getResponseCode(),
                'response_message' => self::iconv_parse($paygent->getResponseDetail()),
            ];
        }
        return [
            "success" => true,
            "data" => $response,
        ];
    }

    public function makeNetBankingPaymentRequest($input = [])
    {
        $paygent = $this->paygent;
        // Default is payment with convenience store number system
        $paygent->reqPut('telegram_kind', "060");
        $paygent->reqPut('trading_id', $input['trading_id']);
        $paygent->reqPut('amount', $input['payment_amount']);
        $paygent->reqPut('customer_name', self::iconv_parse2($input['customer_name']));
        $paygent->reqPut('customer_family_name', self::iconv_parse2($input['customer_family_name']));
        $paygent->reqPut('claim_kana', self::iconv_parse2("ファンクラブカイヒ"));
        $paygent->reqPut('claim_kanji', self::iconv_parse2("ファンクラブ会費"));
        $paygent->reqPut('asp_payment_term', "0050000");
        $result = $paygent->post();
        // Log request info and response
        $response = $paygent->resNext();
        self::logPaymentRequestInfo($paygent, null, [
            "response_data" => $response,
        ]);
        // If payment request is fail
        if ($paygent->getResultStatus() == 1) {
            $responseCode = $paygent->getResponseCode();
            $errorMessage = self::iconv_parse($paygent->getResponseDetail());
            return [
                "success" => false,
                'response_code' => $paygent->getResponseCode(),
                'response_message' => self::iconv_parse($paygent->getResponseDetail()),
            ];
        }
        $response['claim_kana'] = self::iconv_parse($response['claim_kana']);
        $response['claim_kanji'] = self::iconv_parse($response['claim_kanji']);
        return [
            "success" => true,
            "data" => $response,
        ];
    }
}
