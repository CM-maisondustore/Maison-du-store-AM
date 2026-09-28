<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommunicationCheck extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['checked_at'=>'datetime', 'successful'=>'boolean'];
}
