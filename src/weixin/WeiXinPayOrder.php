<?php
namespace Payments\WeiXin;

class WeiXinPayOrder {

    private $out_trade_no;
    private $body;
    private $total_fee;
    private $trade_type;

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


    public function __construct($out_trade_no,$body,$total_fee,$trade_type,$openid='') {

        $this->out_trade_no = $out_trade_no;
        $this->body= $body;
        $this->total_fee = $total_fee;
        $this->openid = $openid;
        $this->trade_type = $trade_type;
    }

    /**
     * @Title: __set
     * @Description: todo()
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
            throw new Exception("参数错误");
        }

        $timeStamp = time();
        $appId = WeiXinConfig::APPID;
        $nonceStr = WeiXinHelper::createNonceStr();
        $mch_id = WeiXinConfig::MCHID;
        $prepay_id = $unifiedOrderResult['prepay_id'];

        if($this->trade_type==WeiXinConfig::PAY_TYPE_JSAPI){

            $JsApiParams = array(
                'appId'=>$appId,
                'timeStamp'=>"$timeStamp",
                'nonceStr'=>$nonceStr,
                'package'=>"prepay_id=".$prepay_id,
                'signType'=>"MD5",
            );

            $JsApiParams['paySign'] = WeiXinHelper::makeSign($JsApiParams);

            return json_encode($JsApiParams);

        }elseif($this->trade_type==WeiXinConfig::PAY_TYPE_APP){

            $appParams = array(
                'appid'=>$appId,
                'noncestr'=>$nonceStr,
                'package'=>"Sign=WXPay",
                'partnerid'=>$mch_id,
                'prepayid'=>$prepay_id,
                'timestamp'=>"$timeStamp",
            );

            $appParams['sign'] = WeiXinHelper::makeSign($appParams);

            return json_encode($appParams);
        }else{
            return json_encode([]);
        }
    }

    /**
     * @Title: unifiedOrder
     * @Description: todo(统一下单)
     * @author zhouchao
     * @return  array  返回类型
     */
    private function unifiedOrder(){

        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";//微信统一下单接口地址

        $params = array(
            'appid'=>WeiXinConfig::APPID,//公众账号ID
            'mch_id'=>WeiXinConfig::MCHID,//商户号
            'nonce_str'=>WeiXinHelper::createNonceStr(32),//随机字符串
            'notify_url'=>WeiXinConfig::NOTIFY_URL,//通知地址
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
            'trade_type'=>$this->trade_type,//交易类型
            'product_id'=>$this->product_id,//商品ID
            'limit_pay'=>$this->limit_pay,//指定支付方式
            'openid'=>$this->openid,//用户标识
        );

        $params = $this->validatePayParams($params);

        $params['sign'] = WeiXinHelper::makeSign($params);

        $xml = WeiXinHelper::ArrayToXml($params);//生成参数xml

        $response = WeiXinHelper::postXmlCurl($xml,$url);//请求微信接口提交xml

        $startTimeStamp = WeiXinHelper::getMillisecond();//请求开始时间

        $result = WeiXinHelper::checkSign($response);

        WeiXinHelper::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;

    }

    /**
     * @Title: orderQuery
     * @Description: todo(查询订单)
     * @author zhouchao
     * @param $transaction_id
     * @param string $out_trade_no
     * @return  bool|mixed  返回类型
     */
    public static function orderQuery($transaction_id,$out_trade_no=''){

        $url = "https://api.mch.weixin.qq.com/pay/orderquery";

        $params = array(
            'appid'=>WeiXinConfig::APPID,
            'mch_id'=>WeiXinConfig::MCHID,
            'nonce_str'=>WeiXinHelper::createNonceStr(),
        );

        //检测必填参数
        if(empty($transaction_id)&&empty($out_trade_no)){
            throw new Exception("订单查询接口中，out_trade_no、transaction_id至少填一个！");
        }elseif(empty($transaction_id)){
            $params['out_trade_no'] = $out_trade_no;
        }else{
            $params['transaction_id'] = $transaction_id;
        }

        $params['sign'] = WeiXinHelper::makeSign($params);

        $xml = WeiXinHelper::ArrayToXml($params);

        $startTimeStamp = WeiXinHelper::getMillisecond();//请求开始时间

        $response = WeiXinHelper::postXmlCurl($xml,$url);//请求微信查单接口提交xml

        $result = WeiXinHelper::checkSign($response);

        WeiXinHelper::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

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
        if($params['trade_type'] == WeiXinConfig::PAY_TYPE_JSAPI && empty($params['openid'])){
            throw new \Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }

        return $params;
    }



}

