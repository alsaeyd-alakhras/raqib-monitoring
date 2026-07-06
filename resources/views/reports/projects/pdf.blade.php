@php
    $c = config('brand.colors');
    $ct = config('brand.contact');
    $img = config('brand.img');
    $logoPath = public_path($img['logo']);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8" />
    <title>تقرير جاهزية مشروع {{ $project->project_number }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: cairo, sans-serif;
            color: {{ $c['ink'] }};
            font-size: 10.5pt;
            line-height: 1.65;
            direction: rtl;
            text-align: right;
        }

        .hdr-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .hdr-table td { vertical-align: middle; padding: 0; }
        .hdr-logo { width: 20mm; height: auto; max-height: 16mm; }
        .hdr-name-ar { font-size: 11pt; font-weight: bold; color: {{ $c['navy'] }}; line-height: 1.4; }
        .hdr-name-en { font-size: 8.5pt; font-weight: bold; color: {{ $c['primary'] }}; line-height: 1.4; }
        .hdr-system { font-size: 7.8pt; color: {{ $c['muted'] }}; line-height: 1.4; }
        .hdr-accent {
            height: 1.4mm;
            margin-top: 2.5mm;
            background-color: {{ $c['primary'] }};
        }

        .ftr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            color: {{ $c['muted'] }};
            border-top: 1px solid {{ $c['border'] }};
            padding-top: 2mm;
        }
        .ftr-table td { vertical-align: middle; padding-top: 1.5mm; }

        .report-hero {
            background: {{ $c['bg_soft'] }};
            border: 1px solid {{ $c['border'] }};
            border-right: 4px solid {{ $c['primary'] }};
            margin-bottom: 7mm;
        }
        .report-hero td { padding: 4.5mm 5mm; vertical-align: middle; }
        .report-hero-title { font-size: 13pt; font-weight: bold; color: {{ $c['navy'] }}; }
        .report-hero-subtitle { font-size: 9pt; font-weight: bold; color: {{ $c['primary'] }}; margin-top: 1mm; direction: ltr; unicode-bidi: embed; text-align: left; }
        .report-hero-code { font-size: 14pt; font-weight: bold; color: {{ $c['navy'] }}; margin: 2mm 0; direction: ltr; unicode-bidi: embed; }
        .report-hero-meta { font-size: 8.8pt; color: {{ $c['muted'] }}; line-height: 1.5; }

        .section-block {
            page-break-inside: avoid;
            margin-bottom: 4mm;
        }
        .section-block-first { margin-top: 2mm; }

        .sec-head { width: 100%; border-bottom: 2px solid {{ $c['border'] }}; margin: 0 0 4mm; }
        .sec-head td { vertical-align: middle; padding-bottom: 2.5mm; }
        .sec-badge {
            background: {{ $c['navy'] }};
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
            padding: 3px 9px;
            white-space: nowrap;
            direction: ltr;
            unicode-bidi: embed;
        }
        .sec-title { font-size: 12.5pt; font-weight: bold; color: {{ $c['navy'] }}; padding-right: 6px; }

        .block-title {
            font-size: 9.8pt;
            font-weight: bold;
            color: {{ $c['primary'] }};
            margin: 0;
            padding: 1mm 8px 0 0;
            border-right: 3px solid {{ $c['secondary'] }};
        }
        .block-title-gap { height: 6mm; line-height: 6mm; font-size: 1pt; margin: 0; padding: 0; }
        .kv-card { margin-bottom: 4mm; }
        .row-pair-cell { padding-bottom: 2mm; }

        table.kv {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1mm;
            margin-bottom: 0;
            border: 1px solid {{ $c['border'] }};
        }
        table.kv th {
            width: 38%;
            background: {{ $c['bg_soft'] }};
            color: {{ $c['navy'] }};
            font-weight: bold;
            font-size: 9pt;
            padding: 2.8mm 3mm;
            border-bottom: 1px solid {{ $c['border'] }};
            border-left: 1px solid {{ $c['border'] }};
            vertical-align: top;
            text-align: right;
            word-wrap: break-word;
        }
        table.kv td {
            padding: 2.8mm 3mm;
            border-bottom: 1px solid {{ $c['border'] }};
            font-size: 9.5pt;
            vertical-align: top;
            word-wrap: break-word;
            line-height: 1.55;
        }
        table.kv tr:nth-child(even) td { background: #fafbfd; }
        table.kv tr:nth-child(even) th { background: #eef3fa; }
        table.kv tr:last-child th,
        table.kv tr:last-child td { border-bottom: none; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 1mm 0 0;
            border: 1px solid {{ $c['border'] }};
        }
        table.data th {
            background: {{ $c['primary'] }};
            color: #ffffff;
            font-weight: bold;
            padding: 2.8mm 3mm;
            font-size: 9.2pt;
            text-align: right;
            border-bottom: 1px solid {{ $c['navy'] }};
        }
        table.data td {
            padding: 2.8mm 3mm;
            border-bottom: 1px solid {{ $c['border'] }};
            font-size: 9.5pt;
            vertical-align: top;
            line-height: 1.55;
        }
        table.data tr:nth-child(even) td { background: {{ $c['bg_soft'] }}; }
        table.data tr:last-child td { border-bottom: none; }

        .cell-wrap { word-wrap: break-word; }
        .text-empty { color: {{ $c['muted'] }}; }
        .text-muted { color: {{ $c['muted'] }}; font-size: 8.8pt; }
        .num-ltr { direction: ltr; unicode-bidi: embed; }

        .chip {
            display: inline-block;
            background: {{ $c['bg_soft'] }};
            border: 1px solid {{ $c['border'] }};
            padding: 1px 5px;
            font-size: 8.8pt;
            margin-left: 2px;
            line-height: 1.4;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            font-size: 8.8pt;
            font-weight: bold;
        }
        .badge-ready { background: #d1fae5; color: #047857; }
        .badge-partial { background: #fef3c7; color: #b45309; }
        .badge-not-ready { background: #fee2e2; color: #b91c1c; }
        .badge-muted { background: #eef1f6; color: {{ $c['muted'] }}; }
        .badge-info { background: {{ $c['light'] }}; color: {{ $c['primary'] }}; }
        .badge-warn { background: #fff3e0; color: #b45309; }

        .checklist-group { margin-bottom: 4mm; page-break-inside: avoid; }
        .checklist-group-title {
            font-size: 10pt;
            font-weight: bold;
            color: {{ $c['primary'] }};
            margin: 0 0 2mm;
            padding: 1.5mm 8px;
            background: {{ $c['bg_soft'] }};
            border-right: 3px solid {{ $c['secondary'] }};
        }

        .status-banner {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            padding: 4mm 5mm;
            margin: 5mm 0;
            font-size: 10.5pt;
            font-weight: bold;
            color: #047857;
            text-align: center;
            page-break-inside: avoid;
        }
        .status-banner-warn { background: #fffbeb; border-color: #fcd34d; color: #b45309; }
        .status-banner-danger { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }

        .signatures-table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        .signatures-table td {
            width: 33%;
            vertical-align: top;
            padding: 3mm;
            border: 1px solid {{ $c['border'] }};
            text-align: center;
        }
        .sig-role { font-size: 9pt; font-weight: bold; color: {{ $c['navy'] }}; margin-bottom: 2mm; }
        .sig-name { font-size: 9.5pt; margin-bottom: 8mm; }
        .sig-line { border-top: 1px solid {{ $c['border'] }}; padding-top: 2mm; font-size: 8.5pt; color: {{ $c['muted'] }}; }

        .confidential-footer {
            margin-top: 4mm;
            padding: 3mm;
            background: {{ $c['navy'] }};
            color: #ffffff;
            font-size: 8.5pt;
            text-align: center;
        }
    </style>
</head>
<body>

<htmlpageheader name="docHeader">
    <table class="hdr-table">
        <tr>
            <td width="24mm">
                @if (file_exists($logoPath))
                    <img src="{{ $logoPath }}" class="hdr-logo" alt="">
                @endif
            </td>
            <td style="text-align:right;">
                <div class="hdr-name-ar">{{ $ct['name_ar'] }}</div>
                <div class="hdr-name-en">{{ $ct['name_en'] }}</div>
                <div class="hdr-system">وحدة الرقابة والمتابعة — {{ $ct['system'] }}</div>
            </td>
        </tr>
    </table>
    <div class="hdr-accent"></div>
</htmlpageheader>
<sethtmlpageheader name="docHeader" value="on" show-this-page="1" />

<htmlpagefooter name="docFooter">
    <table class="ftr-table">
        <tr>
            <td width="40%" style="text-align:right;">{{ $ct['name_ar'] }}</td>
            <td width="35%" style="text-align:center; color:{{ $c['primary'] }}; font-weight:bold;">{{ $project->project_number }}</td>
            <td width="25%" style="text-align:left; direction:ltr; unicode-bidi:embed;">صفحة {PAGENO} / {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>
<sethtmlpagefooter name="docFooter" value="on" />

@include('reports.projects.partials._content')

</body>
</html>
