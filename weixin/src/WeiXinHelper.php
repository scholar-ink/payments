<?php

namespace Payments\WeiXin;

class WeiXinHelper {

    /**
     * @Title: createNonceStr
     * @Description: todo(产生的随机字符串)
     * @author zhouchao
     * @param int $length
     * @return  string  返回类型
     */
    public static function createNonceStr($length = 32) {

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * @Title: toUrlParams
     * @Description: todo(拼接签名地址 key=value)
     * @author zhouchao
     * @param $urlObj
     * @return  string  返回类型
     */
    public static function toUrlParams($urlObj){

        $buff = "";

        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){

                $buff .= $k . "=" . $v . "&";

            }
        }

        $buff = trim($buff, "&");

        return $buff;
    }

    /**
     * @Title: formatBizQueryParaMap
     * @Description: todo(格式化参数)
     * @author zhouchao
     * @param $paraMap
     * @param $urlEncode
     * @return  string  返回类型
     */
    public static function formatBizQueryParaMap($paraMap, $urlEncode){

        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if($urlEncode)
            {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';

        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /**
     * @Title: httpGet
     * @Description: todo(发起Get请求)
     * @author zhouchao
     * @param $url
     * @return  mixed  返回类型
     */
    public static function httpGet($url) {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    /**
     * @Title: postXmlCurl
     * @Description: todo(以post请求提交Xml)
     * @author zhouchao
     * @param $xml
     * @param $url
     * @param bool $useCert
     * @param int $second
     * @return  mixed  返回类型
     */
    public static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new Exception("curl出错，错误码:$error");
        }
    }

    /**
     * @Title: ArrayToXml
     * @Description: todo(数组转成xml)
     * @author zhouchao
     * @param $data
     * @return  string  返回类型
     */
    public static function ArrayToXml($data){

        if(is_array($data)){

            $xml = "<xml>";
            foreach ($data as $key=>$val)
            {
                if (is_numeric($val)){
                    $xml.="<".$key.">".$val."</".$key.">";
                }else{
                    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
                }
            }
            $xml.="</xml>";
            return $xml;

        }else{
            return "<xml></xml>";
        }
    }

    public static function XmlToArray($xml){
        if(!$xml){
            throw new Exception("xml数据异常！");
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

    }

    /**
     * @Title: checkSign
     * @Description: todo(验证签名)
     * @author zhouchao
     * @param $xml
     * @return  bool|mixed  返回类型
     */
    public static function checkSign($xml){

        $params = WeiXinHelper::XmlToArray($xml);

        if($params['return_code'] != 'SUCCESS'){
            return $params;
        }

        if(empty($params['sign'])){
            throw new Exception("数据异常");
        }

        $sign = self::makeSign($params);

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
    public static function makeSign($params){
        //签名步骤一：按字典序排序参数
        ksort($params);
        $string = WeiXinHelper::toUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".WeiXinConfig::KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
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
    public static function reportCostTime($url, $startTimeStamp, $data){

        if(WeiXinConfig::REPORT_LEVENL == 0){//关闭上报

            return;
        }
        //如果仅失败上报
        if(WeiXinConfig::REPORT_LEVENL == 1 &&
            array_key_exists("return_code", $data) &&
            $data["return_code"] == "SUCCESS" &&
            array_key_exists("result_code", $data) &&
            $data["result_code"] == "SUCCESS")
        {
            return;
        }

        $endTimeStamp = self::getMillisecond();

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
            self::report($params);
        } catch (Exception $e){
            //不做任何处理
        }

    }

    /**
     * @Title: report
     * @Description: todo(上报)
     * @author zhouchao
     * @param $inputObj
     * @param int $timeOut
     * @return  mixed  返回类型
     */
    public static function report($params, $timeOut = 1)
    {
        $url = "https://api.mch.weixin.qq.com/payitil/report";
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
        $params['appid'] = WeiXinConfig::APPID;//公众账号ID
        $params['mch_id'] = WeiXinConfig::MCHID;//公众账号ID
        $params['mch_id'] = $_SERVER['REMOTE_ADDR'];//终端ip
        $params['time'] = date("YmdHis");//商户上报时间
        $params['nonce_str'] = self::createNonceStr();//随机字符串

        $params['sign'] = self::makeSign($params);

        $xml = self::ArrayToXml($params);

        $response = self::postXmlCurl($xml,$url,false,30);

        return $response;
    }

    /**
     * @Title: get_php_file
     * @Description: todo(得到cache的php文件)
     * @author zhouchao
     * @param $filename
     * @return  string  返回类型
     */
    public static function get_php_file($filename) {

        return trim(substr(file_get_contents(__DIR__.'/cache/'.$filename), 15));

    }

    /**
     * @Title: set_php_file
     * @Description: todo(设置cache的php文件)
     * @author zhouchao
     * @param $filename
     * @param $content
     * @return  void  返回类型
     */
    public static function set_php_file($filename, $content) {

        $fp = fopen(__DIR__.'/cache/'.$filename, "w");

        fwrite($fp, "<?php exit();?>" . $content);

        fclose($fp);
    }

    /**
     * 获取毫秒级别的时间戳
     */
    public static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode ( " ", microtime () );
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode( ".", $time );
        $time = $time2[0];
        return $time;
    }



}