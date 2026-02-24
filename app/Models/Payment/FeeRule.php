<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fee_rules';

    protected $fillable = [
        'code',
        'module',
        'sub_module',
        'trigger_action',
        'trigger_condition',
        'payment_type',
        'gfs_id',
        'amount',
        'currency',
        'active',
        'effective_from',
        'effective_to',
        'frequency',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function gfs(): BelongsTo
    {
        return $this->belongsTo(Gfs::class);
    }
}
