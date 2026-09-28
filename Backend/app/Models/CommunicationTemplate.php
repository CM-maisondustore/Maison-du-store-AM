<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommunicationTemplate extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['options'=>'array', 'version'=>'integer'];
}
