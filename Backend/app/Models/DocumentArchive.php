<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentArchive extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['payload'];
    public static function capture(string $pdf, array $owner): self
    {
        return static::create([...$owner, 'payload'=>base64_encode($pdf), 'bytes'=>strlen($pdf), 'sha256'=>hash('sha256',$pdf)]);
    }
    public function contents(): string
    {
        $bytes = base64_decode($this->payload, true);
        if ($bytes === false || strlen($bytes) !== (int)$this->bytes || !hash_equals($this->sha256,hash('sha256',$bytes))) throw new \RuntimeException('Archive integrity failure');
        return $bytes;
    }
}
