<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'client',
        'amount',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsPaid(): void
    {
        if ($this->status === 'paid' && $this->paid_at !== null) {
            return;
        }

        $this->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();
    }
}
