<?php

// carlos-meneses/laravel-mpdf  →  php artisan vendor:publish then merge this
use Mpdf\Config\FontVariables;
use Mpdf\Config\ConfigVariables;

$defaultConfig      = (new ConfigVariables())->getDefaults();
$defaultFontConfig  = (new FontVariables())->getDefaults();

return [
    'mode'                 => 'utf-8',
    'format'               => 'A4',
    'default_font_size'    => 10.8,
    'default_font'         => 'ibmplexsansarabic',
    'margin_left'          => 18,
    'margin_right'         => 18,
    'margin_top'           => 22,
    'margin_bottom'        => 22,
    'margin_header'        => 0,
    'margin_footer'        => 8,

    // RTL — critical for Arabic
    'directionality'       => 'rtl',
    'autoScriptToLang'     => true,
    'autoLangToFont'       => true,

    // register IBM Plex Sans Arabic (put ttf in resources/fonts or storage/fonts)
    'font_path'            => resource_path('fonts/'),
    'font_data'            => $defaultFontConfig['fontdata'] + [
        'ibmplexsansarabic' => [
            'R'  => 'IBMPlexSansArabic-Regular.ttf',
            'M'  => 'IBMPlexSansArabic-Medium.ttf',
            'B'  => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL'    => 0xFF,   // Arabic shaping
            'useKashida'=> 75,
        ],
    ],

    'custom_font_dir'      => resource_path('fonts/'),
    'tempDir'              => storage_path('app/mpdf'),
];
