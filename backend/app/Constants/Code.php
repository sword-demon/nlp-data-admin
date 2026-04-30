<?php

declare(strict_types=1);

namespace App\Constants;

class Code
{
    // 通用
    public const SUCCESS = 0;
    public const ERROR = 1;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const VALIDATION_ERROR = 422;
    public const SERVER_ERROR = 500;

    // 认证相关 (10001-10099)
    public const USER_NOT_FOUND = 10001;
    public const INVALID_CREDENTIALS = 10002;
    public const USER_ALREADY_EXISTS = 10003;
    public const EMAIL_ALREADY_EXISTS = 10004;
    public const TOKEN_INVALID = 10005;
    public const TOKEN_EXPIRED = 10006;
    public const TOKEN_MISSING = 10007;

    // AI 相关 (10101-10199)
    public const AI_PROVIDER_MISSING = 10101;
    public const AI_PROVIDER_ERROR = 10102;
    public const AI_STREAM_ERROR = 10103;

    // 创作工坊 (10201-10299)
    public const WORKSHOP_NOT_FOUND = 10201;
    public const WORKSHOP_FORBIDDEN = 10202;           // 非本人文章
    public const WORKSHOP_INVALID_STATE = 10203;        // 状态机非法转换
    public const WORKSHOP_AGENT_FAILED = 10204;         // Agent 执行失败
    public const WORKSHOP_PARSE_ERROR = 10205;          // Agent 输出解析失败
    public const WORKSHOP_TITLE_INDEX_INVALID = 10206;  // 用户选择的 title_index 越界
    public const WORKSHOP_CONTEXT_MISSING = 10207;      // Redis 上下文丢失

    // 配图 (10301-10399)
    public const IMAGE_STRATEGY_UNKNOWN = 10301;   // 未知策略类型
    public const IMAGE_STRATEGY_DISABLED = 10302;  // 策略被配置禁用或配置缺失
    public const IMAGE_FETCH_FAILED = 10303;       // 策略 fetch 阶段失败
    public const IMAGE_UPLOAD_FAILED = 10304;      // OSS 上传失败
    public const IMAGE_ALL_STRATEGIES_FAILED = 10305; // 所有 fallback 链均失败
    public const IMAGE_STRATEGY_FORBIDDEN = 10306;  // 用户 VIP 等级无权使用该策略

    // VIP / 支付 (10401-10499)
    public const QUOTA_EXCEEDED = 10401;       // 配额耗尽
    public const VIP_PLAN_NOT_FOUND = 10402;   // 套餐不存在
    public const VIP_INVALID_PLAN = 10403;     // 非法的 plan_type
    public const PAY_CREATE_FAILED = 10404;    // 创建支付订单失败
    public const PAY_ORDER_NOT_FOUND = 10405;  // 订单不存在
    public const PAY_SIGN_INVALID = 10406;     // 支付签名校验失败
    public const PAY_CALLBACK_INVALID = 10407; // 回调参数异常
    public const PAY_INVALID_PAY_TYPE = 10408; // 非法的支付方式
}
