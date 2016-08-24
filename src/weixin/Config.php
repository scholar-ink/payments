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
        'debug'     => true,
        'app_id'    => 'wx3cf0f39249eb0e60',
        'secret'    => 'f1c242f4f28f735d4687abb469072a29',
        'token'     => 'easywechat',
        'log' => [
            'level' => 'debug',
            'file'  => '/tmp/easywechat.log',
            ],
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

