@php $c = config('brand.colors'); $img = config('brand.img'); $ct = config('brand.contact'); @endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">
<style>
    /* ===== WebUra PDF identity (mPDF-safe) ===== */
    * { box-sizing: border-box; }
    body {
        font-family: ibmplexsansarabic, sans-serif;
        color: {{ $c['ink'] }};
        font-size: 10.8pt;
        line-height: 1.9;
        direction: rtl;
        text-align: right;
    }

    /* --- section heading with numbered badge (table = mPDF flex substitute) --- */
    .sec-head { width: 100%; border-bottom: 2px solid {{ $c['border'] }}; margin-bottom: 5mm; }
    .sec-head td { vertical-align: middle; padding-bottom: 3mm; }
    .sec-badge {
        background: {{ $c['navy'] }}; color: #fff; font-weight: 700;
        font-size: 9.5pt; border-radius: 7px; padding: 4px 10px; white-space: nowrap;
    }
    .sec-title { font-size: 14.5pt; font-weight: 700; color: {{ $c['navy'] }}; padding-right: 8px; }

    h3 {
        font-size: 11.6pt; font-weight: 600; color: {{ $c['primary'] }};
        margin: 6mm 0 2.5mm; padding-right: 10px;
        border-right: 4px solid {{ $c['secondary'] }};
    }
    p { margin: 0 0 3mm; }
    p.lead {
        font-weight: 600; color: {{ $c['navy'] }};
        background: {{ $c['bg_soft'] }}; border-right: 3px solid {{ $c['secondary'] }};
        border-radius: 8px; padding: 3mm 4mm;
    }

    /* --- bullet lists (mPDF renders ul; use color via ::marker fallback = colored text) --- */
    ul { margin: 0 0 3.5mm; padding-right: 6mm; }
    li { margin-bottom: 2.4mm; }
    ul.sub { padding-right: 5mm; margin: 2mm 0; }

    b, strong { color: {{ $c['navy'] }}; font-weight: 600; }

    table.data { width: 100%; border-collapse: collapse; margin: 3mm 0 5mm; }
    table.data th {
        background: {{ $c['primary'] }}; color: #fff; font-weight: 600;
        padding: 2.5mm 3mm; font-size: 9.8pt; text-align: right;
    }
    table.data td { padding: 2.4mm 3mm; border-bottom: 1px solid {{ $c['border'] }}; font-size: 10pt; }
    table.data tr:nth-child(even) td { background: {{ $c['bg_soft'] }}; }
</style>
</head>
<body>

{{-- running footer: contact bar --}}
<htmlpagefooter name="wf">
    <table width="100%" style="border-top:1px solid {{ $c['border'] }}; font-size:8pt; color:{{ $c['muted'] }};">
        <tr>
            <td width="33%" style="text-align:right;">{{ $ct['name_ar'] }} · {{ $ct['name_en'] }}</td>
            <td width="34%" style="text-align:center; color:{{ $c['primary'] }};">{{ $ct['email'] }}</td>
            <td width="33%" style="text-align:left;">صفحة {PAGENO} / {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>
<sethtmlpagefooter name="wf" value="on" />

@yield('content')

</body>
</html>
