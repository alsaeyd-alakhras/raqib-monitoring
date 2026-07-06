<?php

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

$defaultConfig = (new ConfigVariables())->getDefaults();
$defaultFontConfig = (new FontVariables())->getDefaults();

return [
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'default_font_size' => 10.5,
    'default_font'      => 'cairo',
    'margin_left'       => 16,
    'margin_right'      => 16,
    'margin_top'        => 34,
    'margin_bottom'     => 22,
    'margin_header'     => 10,
    'margin_footer'     => 10,
    'directionality'    => 'rtl',
    'autoScriptToLang'  => true,
    'autoLangToFont'    => true,
    'font_path'         => resource_path('fonts/'),
    'font_data'         => $defaultFontConfig['fontdata'] + [
        // Cairo family: static TTF (Tajawal OFL glyphs) for reliable mPDF Arabic shaping
        'cairo' => [
            'R'          => 'Cairo-Regular.ttf',
            'M'          => 'Cairo-SemiBold.ttf',
            'B'          => 'Cairo-Bold.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
        'ibmplexsansarabic' => [
            'R'          => 'IBMPlexSansArabic-Regular.ttf',
            'M'          => 'IBMPlexSansArabic-Medium.ttf',
            'B'          => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
        'tajawal' => [
            'R'          => 'Tajawal-Regular.ttf',
            'M'          => 'Tajawal-Medium.ttf',
            'B'          => 'Tajawal-Bold.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
    ],
    'custom_font_dir'   => resource_path('fonts/'),
    'tempDir'           => storage_path('app/mpdf'),
];
