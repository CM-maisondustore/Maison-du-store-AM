<?php
namespace App\Services;
use App\Services\Quotes\QuoteFinancials;
use Illuminate\Validation\ValidationException;

/**
 * Mise en page libre des PDF : des blocs placés en millimètres sur une page A4, enregistrés dans options.blocks.
 * Un modèle sans blocs garde le rendu actuel (documents.pdf). Ne calcule aucun montant : il lit ceux du document.
 */
final class DocumentBlocks
{
    public const FORMAT=1;
    public const PAGE_W=210;
    public const PAGE_H=297;
    public const MAX_ITEMS=60;
    public const TYPES=['logo'=>'Logo ou image','text'=>'Texte libre','company'=>'Coordonnées de l’entreprise','title'=>'Titre et numéro','client'=>'Bloc client','object'=>'Objet',
        'lines'=>'Tableau des désignations','total'=>'Total général','payment'=>'Conditions de paiement','commitment'=>'Notre engagement',
        'signature'=>'Signature du client','stamp'=>'Cachet','footer'=>'Pied de page','shape'=>'Ligne ou cadre'];
    /** single : page unique ou dernière page ; continuation : pages suite d'un document trop long. */
    public const PAGES=['single','continuation'];
    /** Polices proposées. Arial et Times New Roman utilisent les polices standard du PDF (Helvetica, Times) : jeu de caractères occidental. */
    public const FONTS=['DejaVu Sans','DejaVu Serif','Arial','Times New Roman'];
    /** Pile CSS lue par Dompdf : il prend la première famille installée. */
    public static function fontStack(?string $font): string
    {
        return match ($font) {
            'Arial' => '"Arial", "Helvetica", sans-serif',
            'Times New Roman' => '"Times New Roman", "Times", serif',
            'DejaVu Serif' => '"DejaVu Serif", serif',
            default => '"DejaVu Sans", sans-serif',
        };
    }
    /** Intitulés modifiables par bloc (vide : intitulé par défaut). */
    public const LABELS=['title'=>'DEVIS','client'=>'Client','object'=>'Objet','lines'=>'Désignation','total'=>'TOTAL GÉNÉRAL','payment'=>'Conditions de paiement',
        'commitment'=>'Notre engagement.','signature'=>'Bon pour accord, signature du client','stamp'=>'Cachet et signature de l’entreprise'];
    /** Lignes des tableaux, dans l'ordre par défaut ; l'éditeur choisit celles affichées et leur ordre. */
    public const ROWS=['designations'=>'Désignations (par ouverture)','fees'=>'Pose, livraison et frais','subtotal'=>'Sous-total (avec taxes ou remise)','tax'=>'Taxes','discount'=>'Remise commerciale'];
    public const RECEIPT_ROWS=['received'=>'Montant reçu','method'=>'Moyen de paiement','reference'=>'Référence du paiement','quote_total'=>'Total du devis','cumulative'=>'Cumul reçu à l’édition','remaining'=>'Reste à payer à l’édition'];
    /** Lignes de séparation d'un bloc (couleur de bordure du bloc). Dans un texte, une ligne « --- » trace aussi une séparation. */
    public const SEPARATORS=['top'=>'Au-dessus du bloc','label'=>'Sous l’intitulé','bottom'=>'Sous le bloc'];
    /**
     * Lignes qui composent les blocs Client et Entreprise. Avec l'option grille, chaque ligne est affichée séparément,
     * dans l'ordre choisi, avec son intitulé, des traits horizontaux entre les lignes et un trait vertical entre intitulé et valeur.
     */
    public const BLOCK_FIELDS=[
        'client'=>['name'=>'Nom','phone'=>'Téléphone','address'=>'Adresse','email'=>'E-mail'],
        'company'=>['name'=>'Entreprise','activities'=>'Activités','address'=>'Adresse','phone'=>'Téléphone','email'=>'E-mail'],
    ];
    /** Blocs automatiques qui acceptent un texte complémentaire, imprimé sous leur contenu. */
    public const COMPLEMENT_TYPES=['company','title','client','object','total','payment','signature','stamp','footer'];
    public const COLUMNS=['quantity'=>'Quantité','unit_price'=>'Prix unitaire','amount'=>'Montant'];
    public const SOURCES=['logo'=>'Logo','signature_image'=>'Signature de l’entreprise','stamp_image'=>'Cachet'];
    public const FIELDS=['{client.civilite}','{client.nom}','{client.telephone}','{devis.numero}','{devis.date}','{total.general}','{acompte}','{reste_a_payer}'];
    public const TEXT_TYPES=['text','commitment'];
    /** Blocs qui remontent juste sous la dernière ligne ; signatures, cachet et pied de page restent à leur place. */
    public const FOLLOWERS=['total','payment','commitment'];
    private const SAPIN='#1F3A2E';

    /** Valide le JSON envoyé par l'éditeur. Chaîne vide : retour au rendu actuel. */
    public static function normalize(?string $json): ?array
    {
        if ($json===null || trim($json)==='') return null;
        $fail=fn(string $message)=>throw ValidationException::withMessages(['blocks'=>$message]);
        try { $data=json_decode($json,true,16,JSON_THROW_ON_ERROR); } catch (\JsonException) { $fail('La mise en page reçue est illisible. Rechargez la page.'); }
        if (!is_array($data) || !is_array($data['items'] ?? null) || !array_is_list($data['items'])) $fail('La mise en page reçue est illisible. Rechargez la page.');
        if (count($data['items'])>self::MAX_ITEMS) $fail('60 blocs au maximum par modèle.');
        $items=[]; $ids=[];
        foreach ($data['items'] as $n=>$item) {
            $label='Bloc '.($n+1);
            if (!is_array($item) || !array_key_exists($item['type'] ?? null,self::TYPES)) $fail($label.' : type de bloc inconnu.');
            $type=$item['type'];
            $id=is_string($item['id'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/D',$item['id']) && !isset($ids[$item['id']]) ? $item['id'] : $type.'-'.($n+1);
            $ids[$id]=true;
            $number=function (string $key,float $min,float $max) use ($item,$label,$fail) {
                $value=$item[$key] ?? null;
                if (!is_int($value) && !is_float($value)) $fail($label.' : position ou taille invalide.');
                return round(max($min,min($max,(float)$value)),1);
            };
            $x=$number('x',0,self::PAGE_W-1); $y=$number('y',0,self::PAGE_H-1);
            $w=$number('w',$type==='shape' ? 0.2 : 2,self::PAGE_W-$x); $h=$number('h',$type==='shape' ? 0.2 : 2,self::PAGE_H-$y);
            $pages=array_values(array_intersect(self::PAGES,is_array($item['pages'] ?? null) ? $item['pages'] : []));
            if (!$pages) $fail(self::TYPES[$type].' : choisissez au moins une page où il apparaît.');
            $color=fn(string $key,?string $default)=>is_string($item[$key] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/D',$item[$key]) ? strtoupper($item[$key]) : $default;
            $block=['id'=>$id,'type'=>$type,'x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h,'pages'=>$pages,
                'align'=>in_array($item['align'] ?? '',['left','center','right'],true) ? $item['align'] : 'left',
                'locked'=>($item['locked'] ?? false)===true,
                'font'=>in_array($item['font'] ?? '',self::FONTS,true) ? $item['font'] : 'DejaVu Sans',
                'size'=>max(6,min(28,(int)($item['size'] ?? 9))),
                'color'=>$color('color','#111111'),'background'=>$color('background',null),
                'border_width'=>round(max(0,min(3,(float)($item['border_width'] ?? 0))),1),'border_color'=>$color('border_color','#C9A037')];
            // Options facultatives : absentes, le bloc garde exactement son rendu habituel.
            $opt=function (string $key,float $min,float $max,int $decimals=1) use ($item) {
                $value=$item[$key] ?? null;
                return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? round(max($min,min($max,(float)$value)),$decimals) : null;
            };
            foreach ([['line_height',0.9,2.5,2],['padding',0,8,1],['letter_spacing',0,3,1],['radius',0,6,1]] as [$key,$min,$max,$dec]) if (($v=$opt($key,$min,$max,$dec))!==null) $block[$key]=$v;
            if (in_array($item['valign'] ?? '',['middle','bottom'],true)) $block['valign']=$item['valign'];
            if (in_array($item['border_style'] ?? '',['dashed','dotted'],true)) $block['border_style']=$item['border_style'];
            if (($item['uppercase'] ?? false)===true) $block['uppercase']=true;
            if ($c=$color('label_color',null)) $block['label_color']=$c;
            if ($type!=='shape') {
                $block['bold']=($item['bold'] ?? false)===true; $block['italic']=($item['italic'] ?? false)===true;
                $block['separators']=array_values(array_intersect(array_keys(self::SEPARATORS),is_array($item['separators'] ?? null) ? $item['separators'] : []));
            }
            if (in_array($type,self::TEXT_TYPES,true) || in_array($type,self::COMPLEMENT_TYPES,true)) {
                $text=$item['text'] ?? '';
                if (!is_string($text) || mb_strlen($text)>2000) $fail(self::TYPES[$type].' : 2 000 caractères au maximum.');
                if (in_array($type,self::TEXT_TYPES,true) || trim($text)!=='') $block['text']=$text;
            }
            if (isset(self::LABELS[$type])) {
                $label=$item['label'] ?? '';
                if (!is_string($label) || mb_strlen($label)>120) $fail(self::TYPES[$type].' : intitulé de 120 caractères au maximum.');
                if (trim($label)!=='') $block['label']=trim($label);
            }
            if ($type==='lines') {
                $block['group']=($item['group'] ?? true)!==false;
                $block['follow']=($item['follow'] ?? true)!==false;
                // Colonnes dans l'ordre choisi, intitulés de colonnes, en-tête et lignes du tableau.
                $block['columns']=array_values(array_unique(array_filter(is_array($item['columns'] ?? null) ? $item['columns'] : array_keys(self::COLUMNS),fn($c)=>is_string($c) && isset(self::COLUMNS[$c]))));
                $labels=[];
                foreach (['designation','quantity','unit_price','amount'] as $col) {
                    $text=$item['column_labels'][$col] ?? null;
                    if (is_string($text) && trim($text)!=='') $labels[$col]=mb_substr(trim($text),0,40);
                }
                if ($labels) $block['column_labels']=$labels;
                foreach (['hide_header','no_row_lines','stripes'] as $flag) if (($item[$flag] ?? false)===true) $block[$flag]=true;
                foreach (['header_bg','header_color'] as $key) if ($c=$color($key,null)) $block[$key]=$c;
                // Lignes affichées, dans l'ordre choisi ; absent : toutes, dans l'ordre par défaut.
                $known=[...array_keys(self::ROWS),...array_keys(self::RECEIPT_ROWS)];
                $block['row_order']=is_array($item['row_order'] ?? null) ? array_values(array_unique(array_filter($item['row_order'],fn($k)=>is_string($k) && in_array($k,$known,true)))) : $known;
            }
            if (isset(self::BLOCK_FIELDS[$type])) {
                if (is_array($item['fields'] ?? null)) $block['fields']=array_values(array_unique(array_filter($item['fields'],fn($k)=>is_string($k) && isset(self::BLOCK_FIELDS[$type][$k]))));
                foreach (['field_labels','grid_h','grid_v'] as $flag) $block[$flag]=($item[$flag] ?? false)===true;
            }
            if ($type==='logo') $block['source']=array_key_exists($item['source'] ?? '',self::SOURCES) ? $item['source'] : 'logo';
            if ($type==='shape') $block['shape']=($item['shape'] ?? '')==='line' ? 'line' : 'frame';
            $items[]=$block;
        }
        return ['format'=>self::FORMAT,'items'=>$items];
    }

    /**
     * Mise en page de départ, proche du rendu actuel et tenue dans les marges du modèle :
     * en-tête repris sur les pages suite, client et objet, tableau, puis total, conditions, signatures et pied de page.
     */
    public static function starter(array $margins=PdfLetterhead::MARGINS): array
    {
        $both=['single','continuation']; $single=['single'];
        $left=(float)$margins['left']; $top=(float)$margins['top']; $right=self::PAGE_W-(float)$margins['right']; $bottom=self::PAGE_H-(float)$margins['bottom'];
        $width=$right-$left; $half=round(($width-4)/2,1);
        $footer=$bottom-12; $signature=$footer-30; $commitment=$signature-16; $payment=$commitment-25; $total=$payment-14; $lines=$top+54;
        $block=fn(string $id,string $type,array $box,array $pages,array $extra=[])=>[...['id'=>$id,'type'=>$type,'x'=>round($box[0],1),'y'=>round($box[1],1),'w'=>round($box[2],1),'h'=>round($box[3],1),'pages'=>$pages,
            'align'=>'left','locked'=>false,'font'=>'DejaVu Sans','size'=>9,'color'=>'#111111','background'=>null,'border_width'=>0,'border_color'=>'#C9A037'],...$extra];
        return ['format'=>self::FORMAT,'items'=>[
            $block('logo','logo',[$left,$top,24,22],$both,['source'=>'logo']),
            $block('company','company',[$left+27,$top,$width-27-75,24],$both,['size'=>8]),
            $block('title','title',[$right-73,$top,73,24],$both,['align'=>'right','size'=>8]),
            $block('client','client',[$left,$top+28,$half,22],$single,['background'=>'#F5ECD4']),
            $block('object','object',[$right-$half,$top+28,$half,22],$single,['border_width'=>0.3,'border_color'=>'#DDDDDD']),
            $block('lines','lines',[$left,$lines,$width,max(20,$total-2-$lines)],$single,['size'=>8,'columns'=>array_keys(self::COLUMNS),'group'=>true,'follow'=>true]),
            $block('lines-suite','lines',[$left,$top+28,$width,max(20,$footer-2-($top+28))],['continuation'],['size'=>8,'columns'=>array_keys(self::COLUMNS),'group'=>true,'follow'=>true]),
            $block('total','total',[$right-80,$total,80,11],$single,['align'=>'right','size'=>11,'color'=>'#FFFFFF','background'=>self::SAPIN]),
            $block('payment','payment',[$left,$payment,$width,24],$single,['size'=>8]),
            $block('commitment','commitment',[$left,$commitment,$width,13],$single,['size'=>8,'text'=>'']),
            $block('signature','signature',[$left,$signature,$half,26],$single,['size'=>8,'border_width'=>0.3,'border_color'=>'#DDDDDD']),
            $block('stamp','stamp',[$right-$half,$signature,$half,26],$single,['size'=>8,'border_width'=>0.3,'border_color'=>'#DDDDDD']),
            $block('footer','footer',[$left,$footer,$width,12],$both,['align'=>'center','size'=>7,'color'=>'#555555']),
        ]];
    }

    /** Hauteur d'une ligne du tableau, en millimètres : les pages se calculent sans mesurer le texte. */
    public static function rowHeight(array $block): float { return round(max(4.2,$block['size']*0.3528*1.3+1.4),2); }
    public static function capacity(array $block): int { return max(1,(int)floor(($block['h']-self::rowHeight($block))/self::rowHeight($block))); }

    /** Lignes du tableau : toutes les désignations, puis les frais (pose, livraison), puis sous-total, taxes et remise. */
    public static function rows(array $vm,array $block): array
    {
        $money=fn($value)=>number_format((int)$value,0,',',' ');
        $units=['m'=>'m','m2'=>'m²','flat'=>'forfait','piece'=>'pce','opening'=>'ouv.','lot100'=>'lot de 100'];
        $line=fn(array $l,string $prefix='')=>['kind'=>'line','label'=>$prefix.($l['label'] ?? $l['description'] ?? 'Prestation'),
            'quantity'=>trim(($l['displayQuantity'] ?? $l['quantity'] ?? '').' '.($units[$l['unit'] ?? ''] ?? '')),
            'unit_price'=>!empty($l['pending']) ? 'À définir' : $money($l['unitPrice'] ?? $l['unit_price'] ?? 0),
            'amount'=>!empty($l['pending']) ? 'À définir' : $money($l['displayAmount'] ?? $l['amount'] ?? 0)];
        $rows=[];
        $order=$block['row_order'] ?? [...array_keys(self::ROWS),...array_keys(self::RECEIPT_ROWS)];
        if (($vm['kind'] ?? '')==='receipt') {
            $values=['received'=>$money($vm['receipt']['amount'] ?? 0),'method'=>$vm['receipt']['method'] ?? '','reference'=>($vm['receipt']['reference'] ?? '') ?: '—',
                'quote_total'=>$money($vm['totals']['total'] ?? 0),'cumulative'=>$money($vm['payment_plan']['received'] ?? 0),'remaining'=>$money($vm['remaining_amount'] ?? 0)];
            foreach ($order as $key) if (isset(self::RECEIPT_ROWS[$key])) $rows[]=['kind'=>'sum','label'=>self::RECEIPT_ROWS[$key],'amount'=>$values[$key]];
            return $rows;
        }
        $totals=$vm['totals'] ?? [];
        foreach ($order as $key) switch ($key) {
            case 'designations':
                $groups=$vm['opening_groups'] ?? [];
                foreach ($groups as $room=>$openings) {
                    if ($room!=='' && $block['group']) $rows[]=['kind'=>'room','label'=>$room];
                    foreach ($openings as $opening) {
                        if ($block['group']) $rows[]=['kind'=>'opening','label'=>$opening['label'].' · '.$opening['dimensions'].(($opening['quantity'] ?? 1)>1 ? ' · '.$opening['quantity'].' exemplaires' : '')];
                        foreach ($opening['lines'] as $l) $rows[]=$line($l,$block['group'] ? '' : $opening['label'].' — ');
                        if ($block['group']) $rows[]=['kind'=>'subtotal','label'=>'Sous-total '.$opening['label'],'amount'=>$money($opening['subtotal'])];
                    }
                }
                if (!$groups) foreach ($vm['lines'] ?? [] as $l) $rows[]=$line($l);
                break;
            case 'fees': foreach ($vm['fee_rows'] ?? [] as $fee) $rows[]=$line($fee); break;
            case 'subtotal': if (!empty($totals['tax']) || !empty($totals['discountAmount'])) $rows[]=['kind'=>'sum','label'=>'Sous-total','amount'=>$money($totals['subtotal'] ?? 0)]; break;
            case 'tax': if (!empty($totals['tax'])) $rows[]=['kind'=>'sum','label'=>'Taxes','amount'=>$money($totals['tax'])]; break;
            case 'discount': if (!empty($totals['discountAmount'])) $rows[]=['kind'=>'sum','label'=>'Remise commerciale','amount'=>'−'.$money($totals['discountAmount'])]; break;
        }
        return $rows;
    }

    /**
     * Pages du document : les pages suite d'abord (en-tête repris, désignations), puis la page unique ou dernière avec le reste.
     * Les blocs placés sous le tableau remontent juste sous la dernière ligne quand « follow » est actif.
     */
    public static function pages(array $vm): array
    {
        $items=$vm['style']['blocks']['items'] ?? [];
        $on=fn(string $page)=>array_values(array_filter($items,fn($b)=>in_array($page,$b['pages'],true)));
        $single=$on('single'); $continuation=$on('continuation');
        $linesSingle=collect($single)->firstWhere('type','lines'); $linesCont=collect($continuation)->firstWhere('type','lines') ?? $linesSingle;
        if (!$linesSingle) return [['kind'=>'single','items'=>array_map(fn($b)=>[...$b,'rows'=>[]],$single)]];
        $rows=self::rows($vm,$linesSingle); $capSingle=self::capacity($linesSingle); $capCont=self::capacity($linesCont);
        $chunks=[];
        while (count($rows)>$capSingle) {
            // La dernière page garde au moins une ligne : la pose et la livraison, puis le total juste en dessous.
            $take=min($capCont,count($rows)-1);
            // Un intitulé d'ouverture ne reste jamais seul en bas de page.
            while ($take>1 && in_array($rows[$take-1]['kind'],['opening','room'],true)) $take--;
            $chunks[]=array_splice($rows,0,$take);
        }
        $pages=[];
        foreach ($chunks as $chunk) $pages[]=['kind'=>'continuation','items'=>array_map(fn($b)=>[...$b,'rows'=>$b['type']==='lines' ? $chunk : []],$continuation ?: [$linesCont])];
        // Hauteur réellement occupée par le tableau sur la dernière page ; l'écart prévu sous le tableau est conservé.
        $used=round(self::rowHeight($linesSingle)*(count($rows)+1)+1,1);
        $shift=$linesSingle['follow'] ? max(0,$linesSingle['h']-$used) : 0;
        $bottom=$linesSingle['y']+$linesSingle['h'];
        $pages[]=['kind'=>'single','items'=>array_map(function ($b) use ($rows,$shift,$bottom,$linesSingle,$used) {
            if ($b['id']===$linesSingle['id']) return [...$b,'rows'=>$rows,'h'=>min($b['h'],$used)];
            if ($shift>0 && in_array($b['type'],self::FOLLOWERS,true) && $b['y']>=$bottom-0.05) $b['y']=round($b['y']-$shift,1);
            return [...$b,'rows'=>[]];
        },$single)];
        return $pages;
    }

    /** Données insérées dans un texte libre, toujours échappées à l'affichage. */
    public static function fields(array $vm): array
    {
        $money=fn($value)=>DocumentViewModel::money($value);
        $totals=$vm['totals'] ?? [];
        return ['{client.civilite}'=>CommunicationTemplates::civility($vm['customer']['civility'] ?? null),'{client.nom}'=>trim(($vm['customer']['firstName'] ?? '').' '.($vm['customer']['lastName'] ?? '')),'{client.telephone}'=>$vm['customer']['phone'] ?? '',
            '{devis.numero}'=>$vm['quote_reference'] ?? $vm['number'] ?? '','{devis.date}'=>$vm['date'] ?? '','{total.general}'=>$money($totals['total'] ?? 0),
            '{acompte}'=>$money($totals['deposit'] ?? $vm['payment_plan']['deposit'] ?? 0),'{reste_a_payer}'=>$money($vm['remaining_amount'] ?? 0)];
    }

    /** Échéances prévues, acompte reçu et reste à payer, à partir des montants enregistrés. */
    public static function payment(array $vm): array
    {
        $totals=$vm['totals'] ?? []; $money=fn($value)=>DocumentViewModel::money($value); $lines=[];
        if (!empty($totals['schedule'])) {
            foreach ($totals['schedule'] as $step) $lines[]=QuoteFinancials::quantity($step['percent']).' % — '.$step['label'].' : '.$money($step['amount']);
        } elseif (isset($totals['depositPercent'],$totals['deposit'])) {
            $lines[]='Acompte de '.QuoteFinancials::quantity($totals['depositPercent']).' % avant le démarrage des travaux : '.$money($totals['deposit']);
            $lines[]='Solde de '.QuoteFinancials::quantity($totals['balancePercent']).' % à la fin de la prestation : '.$money($totals['balance']);
        } elseif (!empty($vm['payment_plan']['mode'])) $lines[]=$vm['payment_plan']['mode']==='full' ? 'Paiement intégral.' : 'Avance de '.$money($vm['payment_plan']['deposit'] ?? 0).' puis solde.';
        $lines[]='Acompte reçu : '.$money($vm['payment_plan']['received'] ?? 0).' · reste à payer : '.$money($vm['remaining_amount'] ?? 0);
        $extra=($vm['style']['show_conditions'] ?? false) ? trim($vm['style']['issuer_payment_terms'] ?? '') : '';
        return ['lines'=>$lines,'extra'=>$extra];
    }
}
