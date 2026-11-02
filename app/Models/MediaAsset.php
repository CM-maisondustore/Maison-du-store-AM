<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MediaAsset extends Model
{
    protected $fillable = ['mime', 'width', 'height', 'bytes', 'sha256', 'payload'];
    protected $hidden = ['payload'];
    public function url(): string { return route('media.show', [$this->id, $this->sha256]); }
}
