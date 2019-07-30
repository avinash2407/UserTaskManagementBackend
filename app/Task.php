<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $timestamps = true;
    protected $fillable = [
        'title', 'description','created_by','assigned_to','created_at','due_date','status'
    ];
    public function fromuser()
    {
        return $this->belongsTo('App\User', 'created_by');
    }
    public function touser()
    {
        return $this->belongsTo('App\User', 'assigned_to');
    }
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    // protected $hidden = [
      // 'password',
    // ];
}
