<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    public function workJob(): BelongsTo
    {
        return $this->belongsTo(WorkJob::class);
    }
    
    protected $fillable = [
        'amount',
        'cell_amount',
        'manually_modified',
        'work_job_id',
        'client_id',
        'year',
        'month',
        'frequency',
        'recurring',
        'stops_recurrence',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cell_amount' => 'decimal:2',
            'manually_modified' => 'boolean',
            'recurring' => 'boolean',
            'stops_recurrence' => 'boolean',
        ];
    }

}
