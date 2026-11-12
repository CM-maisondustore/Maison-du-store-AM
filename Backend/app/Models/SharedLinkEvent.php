<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Action sur un lien de document transmis : opened (page), viewed (PDF ouvert), downloaded (PDF téléchargé), expired. */
class SharedLinkEvent extends Model
{
    public const EVENTS = ['opened' => 'Page ouverte', 'viewed' => 'PDF ouvert', 'downloaded' => 'PDF téléchargé', 'expired' => 'Lien expiré'];
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];
    public function message() { return $this->belongsTo(CommercialMessage::class, 'commercial_message_id')->withTrashed(); }

    public static function record(CommercialMessage|int $message, string $event, ?string $userAgent): void
    {
        $ua = (string) $userAgent;
        // Les aperçus automatiques (WhatsApp, Facebook…) ouvrent le lien sans le client : ils ne comptent pas.
        if (preg_match('/WhatsApp|facebookexternalhit|Facebot|TelegramBot|Twitterbot|Slackbot|bot\b|crawler|spider/i', $ua)) return;
        static::create(['commercial_message_id' => is_int($message) ? $message : $message->id, 'event' => $event,
            'device' => preg_match('/ipad|tablet/i', $ua) ? 'tablette' : (preg_match('/mobile|android|iphone/i', $ua) ? 'téléphone' : 'ordinateur'), 'created_at' => now()]);
    }
}
