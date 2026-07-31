<?php

use App\Support\SecretMasker;

test('短密钥完全隐藏且长密钥仅显示首尾', function () {
    expect(SecretMasker::forDisplay('ABCDEFGHIJ'))->toBe('**********')
        ->and(SecretMasker::forDisplay('12345678abcdefghijkl87654321'))
        ->toBe('12345678********87654321')
        ->and(SecretMasker::forDisplay('  '))->toBeNull();
});
