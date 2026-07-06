@php $c = config('brand.colors'); $img = config('brand.img'); $ct = config('brand.contact'); @endphp
{{-- Cover uses full-bleed baked background image (mPDF has no CSS gradients).
     Render this as its OWN mPDF page with zero margins, then AddPage() for body. --}}
<div style="
    position: absolute; top:0; left:0; width:210mm; height:297mm;
    background-image: url('{{ public_path($img['cover_bg']) }}');
    background-image-resize: 6;  /* mPDF: stretch to box */
">
    {{-- brand header block (top-right) --}}
    <table style="position:absolute; top:16mm; right:18mm; width:70mm;">
        <tr><td style="text-align:right; color:{{ $c['primary'] }}; font-size:12pt; font-weight:700;">{{ $ct['name_ar'] }}</td></tr>
        <tr><td style="text-align:right; color:{{ $c['navy'] }}; font-size:11pt; font-weight:600;">{{ $ct['name_en'] }}</td></tr>
    </table>

    {{-- logo badge --}}
    <div style="position:absolute; top:14mm; right:82mm; width:20mm; height:20mm;
                background:{{ $c['navy'] }}; border-radius:6mm;">
        <img src="{{ public_path($img['logo_white']) }}" style="width:13mm; margin:3.5mm;">
    </div>

    {{-- title block --}}
    <div style="position:absolute; top:104mm; right:20mm; width:150mm; text-align:right;">
        <span style="background:{{ $c['light'] }}; color:{{ $c['primary'] }}; font-size:9.5pt;
                     font-weight:600; padding:4px 16px; border-radius:20px;">
            @yield('eyebrow', 'وثيقة معمارية · UX / UI · V2.0')
        </span>
        <div style="font-size:34pt; font-weight:700; line-height:1.3; color:{{ $c['navy'] }}; margin-top:8mm;">
            @yield('title')
        </div>
        <div style="font-size:13pt; font-weight:500; color:#33475b; line-height:1.8; margin-top:6mm;">
            @yield('subtitle')
        </div>
        <div style="width:60mm; height:1.2mm; background:{{ $c['primary'] }}; margin-top:10mm; border-radius:2px;"></div>
    </div>

    {{-- author + version --}}
    <table style="position:absolute; bottom:44mm; right:20mm; width:150mm;">
        <tr>
            <td style="width:78mm; background:{{ $c['bg_soft'] }}; border:1px solid #e2ecf7;
                       border-radius:10px; padding:5mm 7mm;">
                <div style="font-size:9pt; color:{{ $c['secondary'] }}; font-weight:600;">إعداد</div>
                <div style="font-size:13pt; color:{{ $c['navy'] }}; font-weight:700;">@yield('author', 'م. يارا نائل عيسى الحداد')</div>
                <div style="font-size:10pt; color:{{ $c['muted'] }};">@yield('date', 'يوليو 2026')</div>
            </td>
            <td style="width:8mm;"></td>
            <td style="background:{{ $c['navy'] }}; border-radius:10px; padding:5mm 9mm; text-align:center;">
                <div style="font-size:20pt; color:#fff; font-weight:700;">@yield('version','V2.0')</div>
                <div style="font-size:8.5pt; color:{{ $c['light'] }};">الإصدار</div>
            </td>
        </tr>
    </table>

    {{-- footer contact bar (image gradient) --}}
    <div style="position:absolute; bottom:0; left:0; width:210mm; height:16mm;
                background-image:url('{{ public_path($img['footer_bar']) }}'); background-image-resize:6;">
        <table width="100%" style="color:#eaf2fc; font-size:9pt; margin-top:5mm;">
            <tr>
                <td style="text-align:center;"><b style="color:#fff;">Tel:</b> {{ $ct['tel'] }}</td>
                <td style="text-align:center;"><b style="color:#fff;">Gmail:</b> {{ $ct['email'] }}</td>
                <td style="text-align:center;"><b style="color:#fff;">LinkedIn:</b> {{ $ct['linkedin'] }}</td>
            </tr>
        </table>
    </div>
</div>
