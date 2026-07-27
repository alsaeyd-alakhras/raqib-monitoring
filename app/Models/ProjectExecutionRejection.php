<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExecutionRejection extends Model
{
    protected $fillable = [
        'project_execution_id',
        'rejection_reason',
        'gap_owner',
        'return_target',
        'return_target_person_id',
        'workflow_status_before',
        'workflow_status_after',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'rejected_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProjectExecution::class, 'project_execution_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function returnTargetPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'return_target_person_id');
    }
}
