<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gfs extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'gfs';

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function feeRules(): HasMany
    {
        return $this->hasMany(FeeRule::class);
    }
}
