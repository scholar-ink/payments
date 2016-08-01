<?php
namespace Payments\WeiXin;

class WeiXinPay {

    /**
     * @Title: getParameters
     * @Description: todo(得到支付参数)
     * @author zhouchao
     * @param $out_trade_no
     * @param $body
     * @param $total_fee
     * @return  string  返回类型
     */
    public static function getParameters($out_trade_no,$body,$total_fee){

        $order = new WeiXinPayOrder($out_trade_no,$body,$total_fee,WeiXinConfig::PAY_TYPE_APP,$openid='');

        return $order->getParameters();

    }

    /**
     * @Title: notify
     * @Description: todo()
     * @author zhouchao
     * @param $successCallBack
     * @return  void  返回类型
     */
    public static function notify($successCallBack){

        //1.验证签名
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];

        $params = WeiXinHelper::checkSign($xml);

        //2.查询订单

        $result = WeiXinPayOrder::orderQuery($params['transaction_id']);

        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {

            $response = array(
                'return_code'=>'SUCCESS',
                'return_msg'=>'OK'
            );

        }else{

            $response = array(
                'return_code'=>'FAIL',
                'return_msg'=>$result['return_msg']
            );
        }


        //3.更新自己系统的订单数据

        /**
         * $result  数据格式
         * [
        'return_code' => 'SUCCESS',
        'return_msg' => 'OK',
        'appid' => 'appid',
        'mch_id' => 'mch_id',
        'nonce_str' => 'nonce_str',
        'sign' => 'sign',
        'result_code' => 'SUCCESS',
        'openid' => 'openid',
        'is_subscribe' => 'N',
        'trade_type' => 'APP',
        'bank_type' => 'CFT',
        'total_fee' => '1',
        'fee_type' => 'CNY',
        'transaction_id' => 'transaction_id',
        'out_trade_no' => 'out_trade_no',
        'attach' => 'attach',
        'time_end' => '20160422172302',
        'trade_state' => 'SUCCESS',
        'cash_fee' => '1',
        ]
         *
         */
        call_user_func($successCallBack,$result);


        //4.告诉微信支付
        echo  WeiXinHelper::ArrayToXml($response);

    }

}

