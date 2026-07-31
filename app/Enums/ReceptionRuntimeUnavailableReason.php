<?php

namespace App\Enums;

/**
 * 接待运行时不可用的原因。
 * 由 LoadReceptionRuntimeAction 在运行时判定下发，RunReceptionTurnJob 据此停止本会话的 AI 接待；
 * 仅在后端运行时内部流转，不进入前端展示。
 */
enum ReceptionRuntimeUnavailableReason: string
{
    /** 会话已被人工接管（inbox_status 非 AiHandling）。 */
    case TakenOver = 'taken_over';

    /** 会话未锁定接待方案版本，或版本已不存在。 */
    case NoPlan = 'no_plan';

    /** 全局模型池中没有可用的接待模型。 */
    case NoModel = 'no_model';
}
