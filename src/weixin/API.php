<?php
/**
 * Created by PhpStorm.
 * User: zhouchao
 * Date: 16/8/11
 * Time: 上午9:42
 */

namespace Payments\WeiXin;

use \Exception;

class API {

    const PAY_TYPE_JSAPI = "JSAPI";
    const PAY_TYPE_NATIVE = "NATIVE";
    const PAY_TYPE_APP = "APP";

    /**
     * @var Config
     */
    protected $config ;

    public function __construct($config)
    {
        $this->config = $config;
    }

    //API
    const API_PREPARE_ORDER = 'https://api.mch.weixin.qq.com/pay/unifiedorder';//统一下单接口地址

    const API_QUERY = 'https://api.mch.weixin.qq.com/pay/orderquery';//订单查询

    const API_REPORT = "https://api.mch.weixin.qq.com/payitil/report";//数据上报



    /**
     * @Title: report
     * @Description: todo(上报)
     * @author zhouchao
     * @param $inputObj
     * @param int $timeOut
     * @return  mixed  返回类型
     */
    private function report($params, $timeOut = 1)
    {
        //检测必填参数
        if(empty($params['interface_url'])) {
            throw new Exception("接口URL，缺少必填参数interface_url！");
        }
        if(empty($params['return_code'])) {
            throw new Exception("返回状态码，缺少必填参数return_code！");
        }
        if(empty($params['return_code'])) {
            throw new Exception("业务结果，缺少必填参数result_code！");
        }
        if(empty($params['user_ip'])) {
            throw new Exception("访问接口IP，缺少必填参数user_ip！");
        }
        if(empty($params['execute_time_'])) {
            throw new Exception("接口耗时，缺少必填参数execute_time_！");
        }
        $params['appid'] = $this->config->app_id;//公众账号ID
        $params['mch_id'] = $this->config->mch_id;//公众账号ID
        $params['mch_id'] = $_SERVER['REMOTE_ADDR'];//终端ip
        $params['time'] = date("YmdHis");//商户上报时间
        $params['nonce_str'] = Helper::createNonceStr();//随机字符串

        $params['sign'] = self::makeSign($params);

        $xml = Helper::ArrayToXml($params);

        $response = Helper::postXmlCurl($xml,API::API_REPORT,false,30);

        return $response;
    }

    /**
     * @Title: reportCostTime
     * @Description: todo(上报数据， 上报的时候将屏蔽所有异常流程)
     * @author nipeiquan
     * @param $url
     * @param $startTimeStamp
     * @param $data
     * @return  void  返回类型
     */
    public function reportCostTime($url, $startTimeStamp, $data){

        if($this->config->report_level == 0){//关闭上报

            return;

        }

        //如果仅失败上报
        if($this->config->report_level == 1 &&
            array_key_exists("return_code", $data) &&
            $data["return_code"] == "SUCCESS" &&
            array_key_exists("result_code", $data) &&
            $data["result_code"] == "SUCCESS")
        {
            return;
        }

        $endTimeStamp = Helper::getMillisecond();

        $params = array(
            'execute_time_'=>$endTimeStamp - $startTimeStamp,//执行时间
            'interface_url'=>$url,//上报对应的接口的完整URL
        );


        //返回状态码
        if(array_key_exists("return_code", $data)){
            $params['return_code'] = $data['return_code'];
        }
        //返回信息
        if(array_key_exists("return_msg", $data)){
            $params['return_msg'] = $data["return_msg"];
        }
        //业务结果
        if(array_key_exists("result_code", $data)){
            $params['result_code'] = $data["result_code"];
        }
        //错误代码
        if(array_key_exists("err_code", $data)){
            $params['err_code'] = $data["err_code"];
        }
        //错误代码描述
        if(array_key_exists("err_code_des", $data)){
            $params['err_code_des'] = $data["err_code_des"];
        }
        //商户订单号
        if(array_key_exists("out_trade_no", $data)){
            $params['out_trade_no'] = $data["out_trade_no"];
        }
        //设备号
        if(array_key_exists("device_info", $data)){
            $params['device_info'] = $data["device_info"];
        }

        try{
            $this->report($params);
        } catch (Exception $e){
            //不做任何处理
        }

    }

    /**
     * @Title: checkSign
     * @Description: todo(验证签名)
     * @author zhouchao`
     * @param $xml
     * @return  bool|mixed  返回类型
     */
    public function checkSign($xml){

        $params = Helper::XmlToArray($xml);

        if($params['return_code'] != 'SUCCESS'){
            return $params;
        }

        if(empty($params['sign'])){
            throw new Exception("数据异常");
        }

        $sign = $this->makeSign($params);

        if($sign!=$params['sign']){
            throw new Exception("签名错误！");
        }

        return $params;

    }

    /**
     * @Title: makeSign
     * @Description: todo(生成签名)
     * @author zhouchao
     * @param $params
     * @return  string  返回类型
     */
    public function makeSign($params){
        //签名步骤一：按字典序排序参数
        ksort($params);
        $string = Helper::toUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$this->config->key;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }


}