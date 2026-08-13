<div class="row mb-4">
    <div class="col-md-6">
        <x-form.input
            name="recipient_name"
            label="اسم المستلم"
            :value="$recipientName ?? ''"
        />
    </div>
    <div class="col-md-6">
        <x-form.input
            type="tel"
            name="recipient_phone"
            label="رقم الجوال"
            :value="$recipientPhone ?? ''"
            inputmode="numeric"
            pattern="[0-9]*"
            placeholder="059xxxxxxx"
        />
    </div>
</div>
