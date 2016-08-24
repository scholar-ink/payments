<?php
namespace Payments\WeiXin;

/**
 * @property integer $app_id
 * @property string $app_secret
 * @property string $mch_id
 * @property string $key
 * @property string $notify_url
 * @property integer $ssl_cert_path
 * @property integer $ssl_key_path
 * @property integer $pay_type
 * @property integer $report_level
 */
class Config
{

    protected $attributes = [
        'app_id',
        'app_secret',
        'mch_id',
        'key',
        'notify_url',
        'ssl_cert_path',
        'ssl_key_path',
        'pay_type',
        'report_level',
    ];

    /**
     *
     * $options = [
            'app_id'    => 'wx93916f5d17e2d889',
            'app_secret'    => '76ea1955d0d8c8d660e9a90b9b0e0f54',
            'mch_id'    => '1337548901',
            'key'    => '8b5d84f998714434114688a4e195d2fc',
            'notify_url'    => '8b5d84f998714434114688a4e195d2fc',
            'key'    => '8b5d84f998714434114688a4e195d2fc',
            'pay_type'    => 'JSAPI',
            'report_level'     => 0,
        ];
     *
     */
    /**
     * @param array $items
     */
    public function __construct(array $items = []) {

        foreach ($items as $key => $value) {

            $this->attributes[$key] = $value;

        }

    }

}

