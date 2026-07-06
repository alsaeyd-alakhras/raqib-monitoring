# WebUra branded PDF — usage (mPDF)

## 1. Install
```bash
composer require carlos-meneses/laravel-mpdf
php artisan vendor:publish --provider="Mccarlosen\LaravelMpdf\LaravelMpdfServiceProvider"
```

## 2. Drop files
- `config/brand.php`, `config/pdf.php`  → **merge** pdf.php into the published one
- `resources/fonts/IBMPlexSansArabic-*.ttf`
- `public/img/brand/*`  (cover_bg, footer_bar, accent_bar, logos)
- `resources/views/pdf/*`
- `app/Services/BrandPdfService.php`

Create temp dir once:
```bash
mkdir -p storage/app/mpdf && chmod -R 775 storage/app/mpdf
```

## 3. Controller
```php
use App\Services\BrandPdfService;

public function export(BrandPdfService $pdf)
{
    $cover = [
        'title'    => 'مقترح التحليل الهندسي <span style="color:#376092">المحدث</span>',
        'subtitle' => 'لمنصة ومساعد المهاجرين واللاجئين الذكي الشامل في المملكة المتحدة.',
        'author'   => 'م. يارا نائل عيسى الحداد',
        'date'     => 'يوليو 2026',
        'version'  => 'V2.0',
    ];

    $sections = [
        ['num' => 'أولاً', 'title' => 'مكونات شريط التنقل السفلي',
         'body' => '<ul><li>الرئيسية (Home): ...</li><li>الخريطة (Map): ...</li></ul>'],
        ['num' => 'ثانياً', 'title' => 'الميزات الأخرى',
         'body' => '<p class="lead">فقرة مميزة.</p><table class="data"><tr><th>البند</th><th>الوصف</th></tr><tr><td>x</td><td>y</td></tr></table>'],
    ];

    return $pdf->stream($cover, 'pdf.report', ['sections' => $sections], 'webura-report.pdf');
}
```

## Notes (mPDF constraints baked in)
- **No CSS gradients / flexbox** → cover uses `cover_bg.png` + `footer_bar.png`; layout uses `<table>`.
- Title supports inline HTML (`{!! !!}` / `@yield` unescaped) for the colored word.
- RTL + Arabic shaping handled in `config/pdf.php` (`directionality=rtl`, `useOTL`).
- Colored bullets: mPDF ignores `::marker` color; if you need blue dots, use a small `<img>` or a `▪` span (see helper below).
- Footer runs on all body pages via `<htmlpagefooter>`; cover has its own baked bar.

### Optional: colored bullet helper
```php
// in a blade partial
function bullet($t, $c = '#376092') {
    return '<span style="color:'.$c.';">&#9679;</span> '.$t;
}
```
