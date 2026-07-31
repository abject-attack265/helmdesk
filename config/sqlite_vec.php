<?php

$platform = match (PHP_OS_FAMILY) {
    'Linux' => 'linux',
    'Darwin' => 'macos',
    'Windows' => 'windows',
    default => null,
};

$architecture = match (strtolower((string) php_uname('m'))) {
    'aarch64', 'arm64' => 'arm64',
    'x86_64', 'amd64' => 'amd64',
    default => null,
};

$extensions = [
    'linux-arm64' => 'linux-arm64/vec0.so',
    'linux-amd64' => 'linux-amd64/vec0.so',
    'macos-arm64' => 'macos-arm64/vec0.dylib',
    'windows-amd64' => 'windows-amd64/vec0.dll',
];

$key = $platform !== null && $architecture !== null
    ? $platform.'-'.$architecture
    : null;

return [
    'path' => $key !== null && isset($extensions[$key])
        ? base_path('bootstrap/sqlite_vec/'.$extensions[$key])
        : null,
];
