<?php
namespace App\Services;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Configuration Maison du Store AM des modèles : PDF (style, en-tête, blocs) et messages e-mail / WhatsApp.
 * Reprend le format validé du devis instantané : une seule page, désignations puis total juste en dessous,
 * conditions de paiement, « Notre engagement », signatures, slogan en pied de page. Aucune note.
 * Chaque application publie une nouvelle version ; les versions PDF précédentes restent restaurables dans Mise en page.
 */
final class MdsTemplates
{
    public const VERSION=1;
    public const PHONES='07 12 44 93 19 / 01 73 82 63 76';
    public const SLOGAN='L’excellence au service de votre intérieur.';
    public const COMMITMENT='Dès réception de votre acompte, nous lançons la confection sur mesure et nous nous engageons à mener votre chantier jusqu’à la pose finale, avec une finition soignée et conforme au devis validé. Les tissus étant coupés à vos mesures, l’acompte n’est plus remboursable une fois la confection lancée.';
    private const GOLD='#D4A11A'; private const GOLD_SOFT='#FAF1D6'; private const GOLD_LINE='#E3C46A'; private const INK='#151515';

    /** Style et identité communs aux devis, factures et reçus. */
    public static function style(): array
    {
        return ['renderer_version'=>2,'accent'=>self::GOLD,'secondary_color'=>self::GOLD_SOFT,'text_color'=>self::INK,'muted_color'=>'#666666','stripe_color'=>'#E4E0D8',
            'font_family'=>'DejaVu Sans','font_size'=>9,'activities'=>'Rideaux sur mesure · Stores · Motorisation · Décoration intérieure',
            'show_logo'=>true,'show_name'=>true,'show_activities'=>false,'show_title'=>true,'show_cover'=>false,'striped_rows'=>false,'table_borders'=>true,
            'show_conditions'=>true,'show_client_signature'=>true,'show_company_signature'=>true,'show_company_details'=>true,'show_page_number'=>true,
            'table_header'=>'gold','total_style'=>'gold','subtotal_style'=>'light','discount_style'=>'light','deposit_style'=>'light','balance_style'=>'light',
            'issuer_name'=>'Maison du Store AM','issuer_address'=>'Cocody, Marché Saint-Jean, 2ᵉ étage, magasin 341, Abidjan',
            'issuer_registration'=>'RCCM : CI-ABJ-03-2026-B13-09023 · NCC : 2604078 L','issuer_rccm'=>'','issuer_ncc'=>'',
            'issuer_phone'=>self::PHONES,'issuer_email'=>'contact@maisondustoream.com','issuer_website'=>'www.maisondustoream.com',
            'footer'=>self::SLOGAN,'signature'=>'','commitment_text'=>self::COMMITMENT,'mds_templates'=>self::VERSION];
    }

    /** Logo officiel (cercle doré, maison, « AM »), livré avec le code dans resources/brand. */
    public static function logo(): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('brand/logo-mds.png')));
    }

    /** Options propres à chaque modèle, posées par-dessus le style commun. */
    public static function forType(string $key): array
    {
        return match($key) {
            'invoice'=>['issuer_payment_terms'=>'Paiement selon l’échéancier du devis accepté. Les sommes déjà versées sont déduites du reste à payer.'],
            default=>['issuer_payment_terms'=>''],
        };
    }

    /** Blocs A4 : en-tête doré, client, désignations, total, paiement, engagement, signatures, pied de page. */
    public static function blocks(string $key, array $margins): array
    {
        $layout=DocumentBlocks::starter($margins);
        $left=(float)$margins['left']; $top=(float)$margins['top']; $width=DocumentBlocks::PAGE_W-$left-(float)$margins['right'];
        $right=DocumentBlocks::PAGE_W-(float)$margins['right'];
        $at=array_column($layout['items'],null,'id');
        // En-tête plus haut (28 mm) pour les coordonnées complètes ; bloc paiement agrandi pour l'échéancier en 3 temps.
        $header=28.0; $body=$top+$header+3; $extra=6.0;
        $patch=['logo'=>['w'=>30.0,'h'=>$header],'company'=>['x'=>round($left+33,1),'size'=>7,'color'=>self::INK,'w'=>round($width-33-68,1),'h'=>$header],'title'=>['size'=>7,'x'=>round($right-66,1),'w'=>66.0,'h'=>$header],
            'client'=>['y'=>$body,'h'=>20.0,'background'=>self::GOLD_SOFT,'border_width'=>0.3,'border_color'=>self::GOLD_LINE],
            'object'=>['y'=>$body,'h'=>20.0,'border_width'=>0.3,'border_color'=>self::GOLD_LINE],
            'lines'=>['h'=>round($at['lines']['h']-$extra,1)],
            'lines-suite'=>['y'=>$body,'h'=>round($at['lines-suite']['h']-($body-$at['lines-suite']['y']),1)],
            'total'=>['y'=>round($at['total']['y']-$extra,1),'background'=>self::INK,'color'=>'#FFFFFF','border_width'=>0.4,'border_color'=>self::GOLD],
            'payment'=>['y'=>round($at['payment']['y']-$extra,1),'h'=>round($at['payment']['h']+$extra,1)],
            'commitment'=>['h'=>16.0],
            'signature'=>['border_width'=>0.3,'border_color'=>self::GOLD_LINE],'stamp'=>['border_width'=>0.3,'border_color'=>self::GOLD_LINE],
            'footer'=>['color'=>'#666666']];
        $drop=match($key) { 'invoice'=>['commitment'], 'receipt'=>['payment','commitment'], default=>[] };
        $items=[];
        foreach ($layout['items'] as $item) {
            if (in_array($item['id'],$drop,true)) continue;
            $items[]=[...$item,...($patch[$item['id']] ?? [])];
        }
        // Filet doré sous l'en-tête, repris sur les pages suite.
        $items[]=['id'=>'header-rule','type'=>'shape','shape'=>'line','x'=>round($left,1),'y'=>round($top+$header+1,1),'w'=>round($width,1),'h'=>0.5,'pages'=>['single','continuation'],
            'align'=>'left','locked'=>false,'font'=>'DejaVu Sans','size'=>9,'color'=>'#111111','background'=>null,'border_width'=>0.4,'border_color'=>self::GOLD_LINE];
        return DocumentBlocks::normalize(json_encode(['items'=>$items],JSON_THROW_ON_ERROR));
    }

    /**
     * Messages préremplis : [objet, message]. Ton professionnel et accessible : phrases courtes, une idée par paragraphe,
     * deux ou trois emojis en début de phrase (jamais à la place d'un mot). Les liens vers le site portent la provenance
     * du message (suivi dans Visiteurs). Un message WhatsApp de devis ou de facture reçoit déjà le lien sécurisé du
     * document : il n'ajoute pas de lien vers le site.
     */
    public static function message(string $key, string $channel='email'): array
    {
        $wa=$channel==='whatsapp';
        $b=fn(string $text)=>$wa ? '*'.$text.'*' : $text;
        $documentLink=$wa && in_array($key,['quote','invoice'],true);
        $sign=$wa
            ? "L’équipe {{entreprise}}".($documentLink ? '' : "\n🌐 {{site}}")
            : "Bien cordialement,\nL’équipe {{entreprise}}\n📞 ".self::PHONES."\n🌐 {{site}}\n\n".self::SLOGAN;
        $reply=$wa ? 'répondez simplement à ce message' : 'répondez simplement à cet e-mail';
        return match($key) {
            'quote_received'=>['Votre demande de devis {{reference}} est bien reçue',
                "Bonjour {{civilite}} {{nom}},\n\n✅ Merci, votre demande de devis ".$b('{{reference}}')." est bien reçue.\n\nUn conseiller vous appelle très vite pour convenir de la prise de mesures, chez vous ou à notre showroom. Votre devis sur mesure vous est ensuite envoyé.\n\nEn attendant, découvrez nos réalisations : {{lien_realisations}}\n\n$sign"],
            'quote'=>['Votre devis {{reference}} – {{montant}} F CFA',
                "Bonjour {{civilite}} {{nom}},\n\nMerci pour votre confiance. ".($wa ? "Voici votre devis ".$b('{{reference}}').", d’un montant de ".$b('{{montant}} F CFA')."." : "Vous trouverez en pièce jointe votre devis {{reference}}, d’un montant de {{montant}} F CFA.")
                ."\n\n{{conditions_paiement}}\n\n✂️ Dès réception de votre acompte, nous lançons la confection sur mesure. Nous vous accompagnons ensuite jusqu’à la pose finale.\n\n"
                .($wa ? "📄 Votre devis complet s’ouvre avec le lien ci-dessous. Pour le valider ou poser une question, $reply." : "Pour valider le devis ou poser une question, $reply.\nNos réalisations : {{lien_realisations}}")
                ."\n\n$sign"],
            'invoice'=>['Votre facture {{reference}}',
                "Bonjour {{civilite}} {{nom}},\n\n".($wa ? "📄 Voici votre facture ".$b('{{reference}}').", d’un montant de ".$b('{{montant}} F CFA').". Elle s’ouvre avec le lien ci-dessous." : "Vous trouverez en pièce jointe votre facture {{reference}}, d’un montant de {{montant}} F CFA.")
                ."\n\nMerci pour votre confiance 🙏 Pour toute question, $reply.\n\n$sign"],
            'appointment'=>['Votre rendez-vous du {{date}} à {{heure}}',
                "Bonjour {{civilite}} {{nom}},\n\n📅 Votre rendez-vous du ".$b('{{date}} à {{heure}}')." (heure d’Abidjan) est {{statut}}.\n📍 Adresse : {{adresse}}\n\nEn cas d’empêchement, prévenez-nous : $reply.\n\nPour préparer la visite, parcourez nos stores et rideaux : {{lien_boutique}}\n\n$sign"],
            'reminder'=>['Rappel : votre rendez-vous du {{date}} à {{heure}}',
                "Bonjour {{civilite}} {{nom}},\n\n⏰ Petit rappel : nous avons rendez-vous le ".$b('{{date}} à {{heure}}')." (heure d’Abidjan).\n📍 Adresse : {{adresse}}\n\nEn cas d’empêchement, prévenez-nous : $reply.\n\n$sign"],
            'promotion'=>['Les nouveautés Maison du Store AM',
                "Bonjour {{civilite}} {{nom}},\n\n✨ [Présentez l’offre ici : produit, avantage, date limite.]\n\nPour en profiter, demandez votre devis en ligne : {{lien_devis}}\nVous pouvez aussi $reply.\n\nPour ne plus recevoir nos offres, répondez STOP.\n\n$sign"],
            default=>['Votre projet Maison du Store AM',
                "Bonjour {{civilite}} {{nom}},\n\n[Votre message]\n\nVotre devis en ligne, en quelques minutes : {{lien_devis}}\n\n$sign"],
        };
    }

    /** Publie la configuration sur les modèles existants. Retourne le nombre de modèles mis à jour. */
    public static function apply(): int
    {
        return DB::transaction(function () {
            $count=0;
            $templates=CommunicationTemplate::orderBy('id')->lockForUpdate()->get();
            foreach ($templates->where('channel','pdf') as $template) {
                if (!in_array($template->key,['global','quote','invoice','receipt'],true)) continue;
                $current=[...DocumentLayout::defaults(),...DocumentLayout::presentation($template->key)];
                $letterhead=!empty($current['letterhead_enabled']);
                $margins=[]; foreach (PdfLetterhead::MARGINS as $side=>$default) $margins[$side]=$letterhead ? (float)($current['margin_'.$side.'_mm'] ?? $default) : 15.0;
                // Logo officiel ; signature, cachet, papier à en-tête et réglages de fond conservés.
                $kept=array_intersect_key($current,array_flip([...DocumentAssets::KEYS,'letterhead_enabled','letterhead_opacity']));
                $options=[...self::style(),...self::forType($template->key),...$kept,'logo'=>self::logo(),'inherit_global'=>false,'blocks'=>self::blocks($template->key,$margins)];
                $options=DocumentAssets::store($options);
                foreach ($margins as $side=>$value) $options['margin_'.$side.'_mm']=$value;
                DocumentLayout::revision($template);
                $template->update(['body'=>'','options'=>$options,'layout_draft'=>null,'version'=>$template->version+1,'published_version'=>$template->version+1]);
                DocumentLayout::revision($template);
                $count++;
            }
            foreach ($templates->whereIn('channel',['email','whatsapp']) as $template) {
                if (!array_key_exists($template->key,CommunicationTemplates::LABELS)) continue;
                [$subject,$body]=self::message($template->key,$template->channel);
                if ($template->subject===$subject && $template->body===$body) continue;
                $template->update(['subject'=>$subject,'body'=>$body,'version'=>$template->version+1]);
                $count++;
            }
            if ($templates->isNotEmpty()) Audit::record('modeles_mds_appliques',$templates->first());
            return $count;
        });
    }
}
