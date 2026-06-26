<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class opd_appointment extends Model
{
    use HasFactory;

    protected $table = 'opd_appointment';
    protected $primaryKey = 'id';
    protected $connection = 'opddb';
    public $timestamps = false;

    protected $fillable = [
        'pisid',
        'patientno',
        'lastname',
        'firstname',
        'middlename',
        'suffix',
        'gender',
        'email',
        'address',
        'contact',
        'alternativemobileno',
        'datesked',
        'birthday',
        'timesked',
        'service',
        'queue',
        'counterq',
        'newold',
        'reservationcode',
        'edify',
        'status',
        'addedby',
        'dateadded',
        'province',
        'cstat',
        'religion',
        'nationality',
        'pob',
        'occupation',
        'street',
        'barangay',
        'city',
        'chiefc',
        'text',
        'resched',
        'erpatienthistory',
        'opdpatienthistoryid',
        'inpatienthistoryid',
        'jasmc',
        'purpose',
        'guardianname',
        'referralID',
    ];
}
