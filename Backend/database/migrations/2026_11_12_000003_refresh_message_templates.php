<?php
use App\Models\CommunicationTemplate;
use App\Services\{CommunicationTemplates, MdsTemplates};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Nouveaux modèles e-mail et WhatsApp (ton professionnel, emojis discrets, liens vers le site).
 * Seuls les modèles encore identiques à la version précédente sont remplacés : un texte personnalisé est conservé.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            foreach (CommunicationTemplate::whereIn('channel', ['email', 'whatsapp'])->lockForUpdate()->get() as $t) {
                if (!array_key_exists($t->key, CommunicationTemplates::LABELS)) continue;
                [$oldSubject, $oldBody] = self::legacy($t->key, $t->channel);
                if (trim($t->subject) !== trim($oldSubject) || trim($t->body) !== trim($oldBody)) continue;
                [$subject, $body] = MdsTemplates::message($t->key, $t->channel);
                $t->update(['subject' => $subject, 'body' => $body, 'version' => $t->version + 1]);
            }
        });
    }

    public function down(): void {}

    private static function legacy(string $key, string $channel='email'): array
    {
        $wa=$channel==='whatsapp';
        $sign=$wa ? "{{entreprise}}" : "Cordialement,\n{{entreprise}}\n".\App\Services\MdsTemplates::PHONES."\n".\App\Services\MdsTemplates::SLOGAN;
        return match($key) {
            'quote_received'=>['Votre demande de devis {{reference}} est bien reçue',
                "Bonjour {{prenom}},\n\nMerci pour votre demande de devis".($wa ? ' *{{reference}}*' : ' {{reference}}').". Un conseiller vous contacte rapidement pour convenir de la prise de mesures et préparer votre devis sur mesure.\n\n$sign"],
            'quote'=>['Votre devis {{reference}} – {{montant}} F CFA',
                "Bonjour {{prenom}},\n\nMerci pour votre confiance. ".($wa ? "Voici votre devis *{{reference}}* d’un montant de *{{montant}} F CFA*." : "Vous trouverez en pièce jointe votre devis {{reference}} d’un montant de {{montant}} F CFA.")
                ."\n\n{{conditions_paiement}}\n\nDès réception de votre acompte, nous lançons la confection sur mesure et nous vous accompagnons jusqu’à la pose finale.\n\n"
                .($wa ? "Le devis complet est accessible avec le lien ci-dessous. Pour le valider ou poser une question, répondez simplement à ce message." : "Pour valider le devis ou poser une question, répondez simplement à ce message.")
                ."\n\n$sign"],
            'invoice'=>['Votre facture {{reference}}',
                "Bonjour {{prenom}},\n\n".($wa ? "Voici votre facture *{{reference}}* d’un montant de *{{montant}} F CFA*. Elle est accessible avec le lien ci-dessous." : "Vous trouverez en pièce jointe votre facture {{reference}} d’un montant de {{montant}} F CFA.")
                ."\n\nMerci pour votre confiance. Nous restons à votre disposition pour toute question.\n\n$sign"],
            'appointment'=>['Votre rendez-vous du {{date}} à {{heure}}',
                "Bonjour {{prenom}},\n\nVotre rendez-vous du ".($wa ? '*{{date}} à {{heure}}*' : '{{date}} à {{heure}}')." (heure d’Abidjan) est {{statut}}.\nAdresse : {{adresse}}\n\nEn cas d’empêchement, prévenez-nous en répondant à ce message.\n\n$sign"],
            'reminder'=>['Rappel : votre rendez-vous du {{date}} à {{heure}}',
                "Bonjour {{prenom}},\n\nPetit rappel : nous avons rendez-vous le ".($wa ? '*{{date}} à {{heure}}*' : '{{date}} à {{heure}}')." (heure d’Abidjan).\nAdresse : {{adresse}}\n\nEn cas d’empêchement, prévenez-nous en répondant à ce message.\n\n$sign"],
            'promotion'=>['Les nouveautés Maison du Store AM',
                "Bonjour {{prenom}},\n\n[Présentez l’offre ici : produit, avantage, date limite.]\n\nPour en profiter, répondez simplement à ce message.\nPour ne plus recevoir nos offres, répondez STOP.\n\n$sign"],
            default=>['Votre projet Maison du Store AM', "Bonjour {{prenom}},\n\n[Votre message]\n\n$sign"],
        };
    }
};
