<form action="{{ route('dashboard.projects.executions.assign-monitor', [$project, $execution]) }}" method="post" class="row g-2 align-items-end">
    @csrf
    <div class="col-md-5">
        <label class="form-label" for="monitor_person_id">المراقب</label>
        <select name="monitor_person_id" id="monitor_person_id" class="form-select" required>
            <option value="">— اختر —</option>
            @foreach ($monitors as $monitor)
                <option value="{{ $monitor->id }}" @selected(old('monitor_person_id', $execution->monitor_person_id) == $monitor->id)>{{ $monitor->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="monitoring_date">تاريخ المراقبة</label>
        <input type="date" name="monitoring_date" id="monitoring_date" class="form-control" value="{{ old('monitoring_date', $execution->monitoring_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">تعيين المراقب</button>
    </div>
</form>
