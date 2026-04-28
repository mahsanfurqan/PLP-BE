<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnonymousReport extends Model
{
    protected $fillable = [
        'student_name',
        'incident_description',
        'incident_date',
        'source',
    ];

    protected $casts = [
        'incident_date' => 'date',
    ];

    public function coordinatorNotifications()
    {
        return $this->hasMany(AnonymousReportNotification::class);
    }
}
