<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GepgBillConfig extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'gepg_bill_configs';

    protected $fillable = [
        'code',
        'app_code',
        'profile_code',
        'active',
        'sp_grp_code',
        'sys_code',
        'sp_code',
        'bill_typ',
        'pay_typ',
        'coll_cent_code',
        'cust_id_typ',
        'bill_gen_by',
        'bill_appr_by',
        'bill_pay_opt',
        'pay_plan',
        'pay_lim_typ',
        'pay_lim_amt',
        'min_pay_amt',
        'sub_sp_code',
        'use_item_ref_on_pay',
        'coll_sp',
        'coll_psp',
        'bill_ttl_days',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'bill_typ' => 'integer',
        'pay_typ' => 'integer',
        'cust_id_typ' => 'integer',
        'bill_pay_opt' => 'integer',
        'pay_plan' => 'integer',
        'pay_lim_typ' => 'integer',
        'pay_lim_amt' => 'decimal:2',
        'min_pay_amt' => 'decimal:2',
        'bill_ttl_days' => 'integer',
    ];
}
