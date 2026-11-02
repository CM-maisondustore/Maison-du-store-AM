<?php
namespace App\Services;
use App\Models\{CommunicationRule,CommercialMessage,Quote,Invoice,Appointment,CommunicationCheck};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
class CommunicationAutomation
{
    public function source(CommercialMessage $m): Quote|Invoice|Appointment|null
    {
        return $m->quote_id ? $m->quote : ($m->invoice_id ? $m->invoice : ($m->appointment_id ? $m->appointment : null));
    }
    public function fingerprint(Quote|Invoice|Appointment $record): string
    {
        $values = $record instanceof Appointment ? [$record->details,$record->date,$record->time,$record->status]
            : ($record instanceof Quote ? [$record->contact,$record->status,$record->lines,$record->tax_basis_points,$record->valid_until,$record->deposit_amount,$record->dimensions,$record->configuration,$record->delivery_date,$record->installation_date]
            : [$record->snapshot,$record->number,$record->issued_at?->toIso8601String(),$record->due_date?->format('Y-m-d')]);
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    public function validateMessage(CommercialMessage $m): void
    {
        if ($m->kind==='promotion') {
            $expected=$m->channel==='whatsapp' ? preg_replace('/\D/','',$m->contact?->phone ?? '') : mb_strtolower($m->contact?->email ?? '');
            $actual=$m->channel==='whatsapp' ? preg_replace('/\D/','',$m->recipient) : mb_strtolower($m->recipient);
            if (!$m->contact?->marketing_consent_at || !$expected || $expected!==$actual) throw ValidationException::withMessages(['mail'=>'Un consentement commercial actif est requis pour ce destinataire.']);
        }
        if ($m->contact?->archived_at) throw ValidationException::withMessages(['mail'=>'Le dossier client est archivé.']);
        $record=$this->source($m);
        if (($m->quote_id || $m->invoice_id || $m->appointment_id) && !$record) throw ValidationException::withMessages(['mail'=>'Le dossier lié est introuvable.']);
        if (!$record) return;
        if ($m->channel==='whatsapp' && (($record instanceof Quote && !in_array($record->status,['approved','accepted'])) || ($record instanceof Invoice && !$record->issued_at))) throw ValidationException::withMessages(['mail'=>'Validez le devis ou émettez la facture avant de partager le message.']);
        $customer=$record instanceof Quote ? $record->contact : ($record instanceof Invoice ? $record->snapshot['customer'] : $record->details);
        $expected=$m->channel==='whatsapp' ? preg_replace('/\D/','',$customer['phone'] ?? '') : mb_strtolower($customer['email'] ?? '');
        $recipient=$m->channel==='whatsapp' ? preg_replace('/\D/','',$m->recipient) : mb_strtolower($m->recipient);
        if ($expected!==$recipient) throw ValidationException::withMessages(['mail'=>'Les coordonnées du client ont changé. Préparez un nouveau brouillon.']);
        if ($m->source_fingerprint && !hash_equals($m->source_fingerprint,$this->fingerprint($record))) throw ValidationException::withMessages(['mail'=>'Le devis ou le rendez-vous a changé depuis la préparation. Préparez un nouveau brouillon.']);
        if (in_array($m->automation_event,['appointment','reminder']) && ($record->status!=='confirmed' || Carbon::parse($record->date.' '.$record->time)->isPast())) throw ValidationException::withMessages(['mail'=>'Ce rendez-vous n’est plus à confirmer ou rappeler.']);
    }
    private function candidates(CommunicationRule $rule)
    {
        $since=$rule->activated_at ?? now();
        $query=match($rule->key) {
            'quote_received'=>Quote::where('source','site')->where('created_at','>=',$since),
            'quote'=>Quote::whereIn('status',['approved','accepted'])->where('approved_at','>=',$since),
            'invoice'=>Invoice::whereNotNull('issued_at')->where('issued_at','>=',$since),
            default=>Appointment::where('status','confirmed')->where('date','>=',today()->toDateString()),
        };
        if (in_array($rule->key,['quote_received','quote','invoice'])) {
            $column=$rule->key==='invoice' ? 'invoice_id' : 'quote_id';
            $query->whereNotIn('id',CommercialMessage::where('automation_event',$rule->key)->whereNotNull($column)->select($column));
        } elseif ($rule->key==='appointment') $query->where('confirmed_at','>=',$since);
        else $query->where('date','<=',now()->addHours($rule->delay_hours)->toDateString());
        return $query->orderBy('id')->cursor();
    }
    public function process(int $limit=100): array
    {
        $counts=['drafts'=>0,'sent'=>0,'blocked'=>0];
        CommunicationCheck::updateOrCreate(['key'=>'scheduler'],['successful'=>true,'checked_at'=>now()]);
        foreach (CommunicationRule::whereIn('mode',['draft','automatic'])->get() as $rule) {
            foreach ($this->candidates($rule) as $record) {
                if ($counts['drafts'] >= $limit) break 2;
                if ($record instanceof Appointment) {
                    $at=Carbon::parse($record->date.' '.$record->time);
                    if ($at->isPast() || ($rule->key==='reminder' && $at->gt(now()->addHours($rule->delay_hours)))) continue;
                }
                $contact=$record instanceof Invoice ? $record->contact : $record->customer;
                if (!$contact || $contact->archived_at) continue;
                $customer=$record instanceof Quote ? $record->contact : ($record instanceof Invoice ? $record->snapshot['customer'] : $record->details);
                if (!filter_var($customer['email'] ?? '',FILTER_VALIDATE_EMAIL)) continue;
                $slot=$record instanceof Appointment ? $record->date.' '.$record->time : '';
                $key=hash('sha256',$rule->key.':'.$record->id.':'.$slot);
                $created=DB::transaction(function () use($rule,$record,$contact,$customer,$key) {
                    $current=CommunicationRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
                    if ($current->mode==='off' || CommercialMessage::where('automation_key',$key)->exists()) return false;
                    $field=$record instanceof Quote ? 'quote_id' : ($record instanceof Invoice ? 'invoice_id' : 'appointment_id');
                    CommercialMessage::create([...CommunicationTemplates::draft($rule->key,CommunicationTemplates::context($record)),
                        $field=>$record->id,'contact_id'=>$contact->id,'sender'=>config('mail.from.address'),'recipient'=>$customer['email'],
                        'kind'=>match($rule->key){'quote'=>'devis','invoice'=>'facture','appointment','reminder'=>'rendez_vous',default=>'commercial'},
                        'automation_key'=>$key,'automation_event'=>$rule->key,'source_fingerprint'=>$this->fingerprint($record),'auto_send'=>$current->mode==='automatic']);
                    return true;
                });
                if ($created) $counts['drafts']++;
            }
        }
        if (app(MailConnection::class)->verified()) {
            foreach (CommercialMessage::where('direction','outgoing')->where('channel','email')->where('status','draft')->where('auto_send',true)->orderBy('id')->limit($limit)->get() as $message) {
                if (!CommunicationRule::where('key',$message->automation_event)->where('mode','automatic')->exists()) continue;
                try { app(CommercialMail::class)->send($message,true); $counts['sent']++; }
                catch (\Throwable $e) {
                    $message->refresh();
                    if ($message->status==='draft') $message->update(['auto_send'=>false]);
                    $counts['blocked']++;
                    if (!app(MailConnection::class)->verified()) break;
                }
            }
        }
        return $counts;
    }
}
