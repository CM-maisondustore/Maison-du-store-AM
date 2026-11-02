<?php
namespace App\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Auth, DB};
class Audit
{
    public static function record(string $action, Model $subject): void
    {
        DB::table('audit_events')->insert(['user_id' => Auth::id(), 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'created_at' => now()]);
    }
}
