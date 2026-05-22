<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditInvoice extends Model
{
    protected $table = 'audit_invoice';
    protected $primaryKey = 'id_audit';
    public $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_audit',
        'id_invoice',
        'document_employee',
        'action_type',
        'action_date',
        'previous_status',
        'new_status',
        'previous_total',
        'new_total',
    ];

    protected $casts = [
        'action_date'    => 'datetime',
        'previous_total' => 'decimal:2',
        'new_total'      => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'id_invoice', 'id_invoice');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'document_employee', 'document_employee');
    }
}