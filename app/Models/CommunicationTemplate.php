<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommunicationTemplate extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['options'=>'array', 'layout_draft'=>'array', 'version'=>'integer', 'published_version'=>'integer'];
}
