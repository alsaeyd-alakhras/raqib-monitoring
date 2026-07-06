<table class="signatures-table" width="100%">
    <tr>
        <td>
            <div class="sig-role">المراقب الميداني</div>
            <div class="sig-name">{{ $project->monitorPerson?->name ?? '—' }}</div>
            <div class="sig-line">التوقيع: ........................</div>
        </td>
        <td>
            <div class="sig-role">مدير المشروع</div>
            <div class="sig-name">{{ $project->projectManager?->name ?? '—' }}</div>
            <div class="sig-line">التوقيع: ........................</div>
        </td>
        <td>
            <div class="sig-role">مدير الرقابة والمتابعة</div>
            <div class="sig-name">&nbsp;</div>
            <div class="sig-line">التوقيع: ........................</div>
        </td>
    </tr>
</table>

<div class="confidential-footer">
    وحدة الرقابة والمتابعة — هذا التقرير سري للاستخدام الداخلي فقط — نسخة لمدير ملف المشروع
</div>
