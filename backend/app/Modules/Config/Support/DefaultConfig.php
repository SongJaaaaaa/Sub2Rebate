<?php

namespace App\Modules\Config\Support;

class DefaultConfig
{
    public static function items(): array
    {
        return [
            [
                'key' => 'milestone.amount',
                'group' => 'milestone',
                'name' => '里程碑金额',
                'type' => 'decimal',
                'value' => '100',
                'tips' => '新人累计充值每达到该金额，触发一次直接上级奖励',
                'sort' => 10,
            ],
            [
                'key' => 'milestone.reward_amount',
                'group' => 'milestone',
                'name' => '里程碑奖励金额',
                'type' => 'decimal',
                'value' => '15',
                'tips' => '每次里程碑触发时，直接上级获得的返利金额',
                'sort' => 20,
            ],
            [
                'key' => 'milestone.max_times',
                'group' => 'milestone',
                'name' => '里程碑次数上限',
                'type' => 'int',
                'value' => 2,
                'tips' => '同一个新人最多触发多少次里程碑奖励',
                'sort' => 30,
            ],
            [
                'key' => 'milestone.only_direct',
                'group' => 'milestone',
                'name' => '只奖励直接上级',
                'type' => 'bool',
                'value' => true,
                'tips' => '开启后，里程碑阶段只奖励直接上级',
                'sort' => 40,
            ],
            [
                'key' => 'rebate.pool_ratio',
                'group' => 'rebate',
                'name' => '返利池比例',
                'type' => 'decimal',
                'value' => '0.15',
                'tips' => '返利池比例，充值 100 元返利池为 15 元',
                'sort' => 50,
            ],
            [
                'key' => 'rebate.mode',
                'group' => 'rebate',
                'name' => '分发模式',
                'type' => 'string',
                'value' => 'decay',
                'tips' => '分发模式：decay 衰减系数模式',
                'sort' => 60,
            ],
            [
                'key' => 'rebate.decay_factor',
                'group' => 'rebate',
                'name' => '衰减系数',
                'type' => 'decimal',
                'value' => '0.4',
                'tips' => '衰减系数，每增加一级权重乘以该值，越小则上级集中度越高',
                'sort' => 70,
            ],
            [
                'key' => 'rebate.normalize',
                'group' => 'rebate',
                'name' => '归一化',
                'type' => 'bool',
                'value' => true,
                'tips' => '归一化后将返利池全部分配给有效上级',
                'sort' => 80,
            ],
            [
                'key' => 'payment.cny_to_credit_rate',
                'group' => 'payment',
                'name' => '人民币额度换算比例',
                'type' => 'decimal',
                'value' => '1',
                'tips' => '充值 1 人民币默认换算为多少 API 额度',
                'sort' => 90,
            ],
            [
                'key' => 'payment.qr_enabled',
                'group' => 'payment',
                'name' => '二维码充值开关',
                'type' => 'bool',
                'value' => true,
                'tips' => '是否启用支付宝二维码充值',
                'sort' => 91,
            ],
            [
                'key' => 'payment.epay_enabled',
                'group' => 'payment',
                'name' => 'Epay在线支付开关',
                'type' => 'bool',
                'value' => false,
                'tips' => '是否启用 Epay（当面付）在线支付通道，需在 .env 配置 EPAY_GATEWAY/PID/KEY',
                'sort' => 95,
            ],
            [
                'key' => 'payment.alipay_qr_url',
                'group' => 'payment',
                'name' => '支付宝二维码地址',
                'type' => 'string',
                'value' => '',
                'tips' => '支付宝收款二维码图片 URL，可填 CDN、对象存储或站内静态文件地址，也可保存 data URL',
                'sort' => 92,
            ],
            [
                'key' => 'payment.alipay_display_name',
                'group' => 'payment',
                'name' => '支付宝收款展示名',
                'type' => 'string',
                'value' => '',
                'tips' => '充值页展示的收款方名称，例如 张三(支付宝)',
                'sort' => 93,
            ],
            [
                'key' => 'payment.qr_note',
                'group' => 'payment',
                'name' => '二维码充值提示',
                'type' => 'string',
                'value' => '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。',
                'tips' => '充值页展示给用户的付款提示文案',
                'sort' => 94,
            ],
            [
                'key' => 'payment.order_expire_minutes',
                'group' => 'payment',
                'name' => '充值订单有效期',
                'type' => 'int',
                'value' => 15,
                'tips' => '二维码充值订单有效期，超时后需要重新创建订单',
                'sort' => 95,
            ],
            [
                'key' => 'withdraw.min_amount',
                'group' => 'withdraw',
                'name' => '最低提现金额',
                'type' => 'decimal',
                'value' => '50',
                'tips' => '最低提现金额',
                'sort' => 100,
            ],
            [
                'key' => 'withdraw.review_mode',
                'group' => 'withdraw',
                'name' => '提现审核模式',
                'type' => 'string',
                'value' => 'manual',
                'tips' => '审核模式：manual 人工审核',
                'sort' => 110,
            ],
            [
                'key' => 'withdraw.daily_limit',
                'group' => 'withdraw',
                'name' => '每日提现次数',
                'type' => 'int',
                'value' => 1,
                'tips' => '每日提现次数上限',
                'sort' => 120,
            ],
            [
                'key' => 'withdraw.freeze_days',
                'group' => 'withdraw',
                'name' => '返利冻结天数',
                'type' => 'int',
                'value' => 0,
                'tips' => '新获得返利冻结天数，0 为不冻结',
                'sort' => 130,
            ],
            [
                'key' => 'risk.blacklist_enabled',
                'group' => 'risk',
                'name' => '启用黑名单',
                'type' => 'bool',
                'value' => true,
                'tips' => '是否启用黑名单',
                'sort' => 140,
            ],
            [
                'key' => 'risk.duplicate_check',
                'group' => 'risk',
                'name' => '防重复发放检查',
                'type' => 'bool',
                'value' => true,
                'tips' => '是否启用返利事件防重复发放检查',
                'sort' => 150,
            ],
        ];
    }

    public static function byKey(): array
    {
        $items = [];
        foreach (self::items() as $item) {
            $items[$item['key']] = $item;
        }

        return $items;
    }
}