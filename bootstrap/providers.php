<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TranslationServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TranslationServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
