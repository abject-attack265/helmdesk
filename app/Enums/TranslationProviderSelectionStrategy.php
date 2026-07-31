<?php

namespace App\Enums;

/**
 * 翻译供应商选择策略，控制首选引擎类型及手动重译顺序。
 */
enum TranslationProviderSelectionStrategy: string
{
    case MachineFirst = 'machine_first';
    case AiFirst = 'ai_first';
    case Random = 'random';
}
