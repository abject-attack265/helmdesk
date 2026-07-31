<?php

use App\Services\LocalePreference;

test('locale 匹配支持区域变体且排除空值与异语言', function (): void {
    // 大小写、下划线/连字符、同语言区域变体均视为匹配
    expect(LocalePreference::matches('zh_CN', 'zh-CN'))->toBeTrue()
        ->and(LocalePreference::matches('EN-us', 'en'))->toBeTrue()
        ->and(LocalePreference::matches('ja-JP', 'ja'))->toBeTrue()
        // 空值或不同语言不匹配
        ->and(LocalePreference::matches('', 'zh-CN'))->toBeFalse()
        ->and(LocalePreference::matches('  ', 'en'))->toBeFalse()
        ->and(LocalePreference::matches('zh-CN', 'en'))->toBeFalse();
});
