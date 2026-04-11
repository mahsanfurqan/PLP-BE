<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PendaftaranPlp extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran_plps'; // Explicitly defining the table name

    protected $fillable = [
        'user_id',
        'keminatan_id',
        'nilai_plp_1',
        'nilai_micro_teaching',
        'pilihan_smk_1',
        'pilihan_smk_2',
        'penempatan',
    ];

    /**
     * Relationship with User (the one who registers)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with Keminatan
     */
    public function keminatan()
    {
        return $this->belongsTo(Keminatan::class);
    }

    /**
     * Relationship with SMK (First Choice)
     */
    public function pilihanSmk1()
    {
        return $this->belongsTo(Smk::class, 'pilihan_smk_1');
    }

    /**
     * Relationship with SMK (Second Choice)
     */
    public function pilihanSmk2()
    {
        return $this->belongsTo(Smk::class, 'pilihan_smk_2');
    }

    /**
     * Relationship with SMK (Final Placement)
     */
    public function penempatanSmk()
    {
        return $this->belongsTo(Smk::class, 'penempatan');
    }

}
