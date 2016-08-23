<?php
namespace Payments\WeiXin;

use \Exception;

class WeiXinPayOrder {

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var API
     */
    protected $api;

    private $out_trade_no;
    private $body;
    private $total_fee;

    /**
     * @var 下面的都是不必填数据
     */
    private $detail;
    private $attach = 'attach';
    private $fee_type;
    private $time_start;
    private $time_expire;
    private $goods_tag;
    private $product_id;
    private $limit_pay;
    private $openid;


    public function __construct($config,$out_trade_no,$body,$total_fee) {

        $this->config = $config;

        $this->api = new API($config);

        $this->out_trade_no = $out_trade_no;
        $this->body= $body;
        $this->total_fee = $total_fee;
    }

    /**
     * @Title: __set
     * @Description: todo(__set)
     * @author zhouchao
     * @param $name
     * @param $value
     * @return  void  返回类型
     */
    public function __set($name,$value){
        $this->$name = $value;
    }

    public function getParameters(){

        $unifiedOrderResult = $this->unifiedOrder();//统一下单

        //生成支付参数
        if(!array_key_exists("appid", $unifiedOrderResult)
            || !array_key_exists("prepay_id", $unifiedOrderResult)
            || $unifiedOrderResult['prepay_id'] == "")
        {
            throw new \Exception("参数错误");
        }

        $timeStamp = time();
        $appId = $this->config->app_id;
        $nonceStr = Helper::createNonceStr();
        $mch_id = $this->config->mch_id;
        $prepay_id = $unifiedOrderResult['prepay_id'];

        if($this->config->pay_type==API::PAY_TYPE_JSAPI){

            $JsApiParams = array(
                'appId'=>$appId,
                'timeStamp'=>"$timeStamp",
                'nonceStr'=>$nonceStr,
                'package'=>"prepay_id=".$prepay_id,
                'signType'=>"MD5",
            );

            $JsApiParams['paySign'] = $this->api->makeSign($JsApiParams);

            return json_encode($JsApiParams);

        }elseif($this->config->pay_type==API::PAY_TYPE_APP){

            $appParams = array(
                'appid'=>$appId,
                'noncestr'=>$nonceStr,
                'package'=>"Sign=WXPay",
                'partnerid'=>$mch_id,
                'prepayid'=>$prepay_id,
                'timestamp'=>"$timeStamp",
            );

            $appParams['sign'] = $this->api->makeSign($appParams);

            return json_encode($appParams);
        }else{
            return json_encode([]);
        }
    }

    /**
     * @Title: notify
     * @Description: todo(回调通知)
     * @author zhouchao
     * @param $successCallBack //回到函数
     * @return  void  返回类型
     */
    public function notify($successCallBack){

        //1.验证签名
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];

        $params = $this->api->checkSign($xml);

        //2.查询订单

        $result = $this->orderQuery($params['transaction_id']);

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
        echo  Helper::ArrayToXml($response);

    }

    /**
     * @Title: orderQuery
     * @Description: todo(查询订单)
     * @author zhouchao
     * @param $transaction_id
     * @param string $out_trade_no
     * @return  bool|mixed  返回类型
     */
    private function orderQuery($transaction_id,$out_trade_no=''){

        $url = "https://api.mch.weixin.qq.com/pay/orderquery";

        $params = array(
            'appid'=>$this->config->app_id,
            'mch_id'=>$this->config->mch_id,
            'nonce_str'=>Helper::createNonceStr(),
        );

        //检测必填参数
        if(empty($transaction_id)&&empty($out_trade_no)){
            throw new Exception("订单查询接口中，out_trade_no、transaction_id至少填一个！");
        }elseif(empty($transaction_id)){
            $params['out_trade_no'] = $out_trade_no;
        }else{
            $params['transaction_id'] = $transaction_id;
        }

        $params['sign'] = $this->api->makeSign($params);

        $xml = Helper::ArrayToXml($params);

        $startTimeStamp = Helper::getMillisecond();//请求开始时间

        $response = Helper::postXmlCurl($xml,$url);//请求微信查单接口提交xml

        $result = $this->api->checkSign($response);

        $this->api->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     * @Title: unifiedOrder
     * @Description: todo(统一下单)
     * @author zhouchao
     * @return  array  返回类型
     */
    private function unifiedOrder(){

        $url = API::API_PREPARE_ORDER;//微信统一下单接口地址

        $params = array(
            'appid'=>$this->config->app_id,//公众账号ID
            'mch_id'=>$this->config->mch_id,//商户号
            'nonce_str'=>Helper::createNonceStr(32),//随机字符串
            'notify_url'=>$this->config->notify_url,//通知地址
            'body'=>$this->body,//商品描述
            'detail'=>$this->detail,//商品详情
            'attach'=>$this->attach,//附加数据
            'out_trade_no'=>$this->out_trade_no,//商户订单号
            'fee_type'=>$this->fee_type,//货币类型
            'total_fee'=>$this->total_fee,//总金额
            'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],//终端IP
            'time_start'=>$this->time_start,//交易起始时间
            'time_expire'=>$this->time_expire,//交易结束时间
            'goods_tag'=>$this->goods_tag,//商品标记
            'trade_type'=>$this->config->pay_type,//交易类型
            'product_id'=>$this->product_id,//商品ID
            'limit_pay'=>$this->limit_pay,//指定支付方式
            'openid'=>$this->openid,//用户标识
        );

        $params = $this->validatePayParams($params);

        $params['sign'] = $this->api->makeSign($params);

        $xml = Helper::ArrayToXml($params);//生成参数xml

        $response = Helper::postXmlCurl($xml,$url);//请求微信接口提交xml

        $startTimeStamp = Helper::getMillisecond();//请求开始时间

        $result = $this->api->checkSign($response);

        $this->api->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;

    }

    /**
     * @Title: applyRefund
     * @Description: todo(申请退款)
     * @author nipeiquan
     * @param $out_trade_no
     * @param $total_fee
     * @param $refund_fee
     * @return  void  返回类型
     */
    public function applyRefund($out_trade_no,$total_fee,$refund_fee){

        $url = API::API_REFUND;//微信申请退款接口地址



        $params = array(
            'appid'=>$this->config->app_id,//公众账号ID
            'mch_id'=>$this->config->mch_id,//商户号
            'nonce_str'=>Helper::createNonceStr(32),//随机字符串
            'out_trade_no'=>$out_trade_no,//订单号
            'out_refund_no'=>$this->out_trade_no,//商户退款单号
            'total_fee'=>$total_fee,//总金额
            'refund_fee'=>$refund_fee,//退款金额
            'op_user_id'=>$this->config->mch_id,//用户标识
        );

        $params = $this->validatePayParams($params);

        $params['sign'] = $this->api->makeSign($params);

        $xml = Helper::ArrayToXml($params);//生成参数xml

        $response = Helper::postXmlCurl($xml,$url);//请求微信接口提交xml

        $result = $this->api->checkSign($response);

        return $result;
    }

    /**
     * @Title: validatePayParams
     * @Description: todo(验证支付参数)
     * @author zhouchao
     * @param $params
     * @return  array  返回类型
     */
    private function validatePayParams($params){

        $params = array_filter($params);

        //检测必填参数

        if(empty($params['out_trade_no'])){
            throw new \Exception("缺少统一支付接口必填参数out_trade_no！");
        } elseif(empty($params['body'])){
            throw new \Exception("缺少统一支付接口必填参数body！");
        } elseif(empty($params['total_fee'])){
            throw new \Exception("缺少统一支付接口必填参数total_fee！");
        } elseif(empty($params['trade_type'])){
            throw new \Exception("缺少统一支付接口必填参数trade_type！");
        }

        //关联参数
        if($params['trade_type'] == API::PAY_TYPE_JSAPI && empty($params['openid'])){
            throw new \Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }

        return $params;
    }



}

