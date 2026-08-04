<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\ResolvesPerPage;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')
            ->orderBy('created_at', 'DESC')
            ->paginate($this->resolvePerPage($request, 10))
            ->withQueryString();

        return view('dashboard.pages.logs', compact('logs'));
    }

    public function getLogs(Request $request){
        $log = ActivityLog::findOrFail($request->id);
        $log->created_at_diff = Carbon::parse($log->created_at)->diffForHumans();
        $log->old_data = json_decode($log->old_data);
        $log->new_data = json_decode($log->new_data);
        return response()->json($log);
    }
}
