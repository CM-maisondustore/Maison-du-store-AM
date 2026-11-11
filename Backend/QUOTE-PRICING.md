# Chiffrage des devis

Le formulaire complet des devis utilise le moteur `App\Services\Quotes`. Les montants sont réservés à Admin-CM, y compris dans les réponses JSON, PDF et communications. Les agents saisissent les choix techniques et les métrages ; leurs modifications imposent une nouvelle vérification du chiffrage.

## Utilisation

1. Choisir les tissus rideaux et voilages séparément, ou le store, puis la finition et les fournitures de chaque ouverture. Les dimensions du formulaire restent en **centimètres** ; le moteur reçoit des mètres décimaux.
2. Indiquer le nombre d’ouvertures identiques. Le tissu reste en métrage manuel par défaut. Le coefficient est une option : une quantité saisie reste prioritaire. Pour un store, choisir « Quantité manuelle, même en m² » pour remplacer la surface calculée.
3. Dans **Matériaux et finitions**, chaque tissu, rail, reflète et confection peut avoir sa configuration de chiffrage : calcul (largeur × coefficient ou quantité manuelle ; confection au mètre ou au forfait), coefficient et base si quantité vide (tissu principal, ou tissu + voilage). Elle est proposée quand l’option est choisie sur une ouverture ; l’agent peut la modifier, et les devis enregistrés gardent leurs réglages. Vide = réglages habituels ci-dessous.
4. Une quantité vide reprend la largeur pour les rails, le métrage principal pour reflète/confection, ou une unité pour kit moteur/pose/accroches. Reflète et confection peuvent reprendre l’ensemble des deux tissus. Les accroches sont comptées en lots de 100. La confection accepte aussi un forfait ou un montant manuel.
5. Un **prix vide reprend le catalogue**, zéro signifie offert. Les prix personnalisés sont propres au devis et restent après recalcul. La pose rideau reste à définir. Le rail supplémentaire permet de joindre un rail manuel à un système motorisé ; son coût est distinct du kit moteur.
6. Choisir les frais globaux, par ouverture ou par trajet. Une pose globale et une pose par ouverture ne peuvent pas se cumuler. La pose/livraison combinée exclut les deux frais séparés. Des tarifs de livraison, transport, déplacement et pose/livraison peuvent être ajoutés au catalogue avec l’unité **Forfait**, puis sélectionnés dans ces frais.
7. Définir une remise en pourcentage, un montant fixe ou un total négocié. Le total brut et la remise restent visibles ; aucune ligne de fourniture n’est artificiellement modifiée.
8. Cliquer sur **Calculer depuis le catalogue**, vérifier le détail, puis enregistrer. Les modifications des paramètres invalident le calcul précédent. L’affichage groupé ou individuel ne change aucun montant.

L’acompte vaut par défaut 70 % du total définitif, arrondi au franc le plus proche (demi-franc vers le haut). Le solde est la différence exacte. Les paiements reçus sont enregistrés séparément, après confirmation. La mise en fabrication exige un devis accepté et l’acompte reçu ; un dossier ne peut être soldé qu’après réception du total.

## Architecture et conservation

- `QuoteCalculator`, `OpeningCalculator`, `QuoteTotals` et `QuoteValidator` forment le moteur pur, sans dépendance à HTTP, à la base ou au PDF. `brick/math` évite les calculs monétaires en virgule flottante.
- `CatalogueCalculator` et `PricingConfig` adaptent les options stockées en base et les ajustements du formulaire. Les tarifs historiques administrateur ne sont pas remplacés par les exemples de prix. Les nouveaux choix « Voilage lourd » et « Kit moteur rideau » sont ajoutés sans modifier les options existantes.
- Le résultat est enregistré dans `quotes.configuration.calculation.financial`, avec les paramètres, origines des valeurs, détails, frais, remise et échéancier. Le jeton signé lie le résultat aux paramètres vérifiés ; modifier les lignes finales nécessite un recalcul.
- `QuoteFinancials` fournit les montants enregistrés au site, aux factures, PDF et messages. Les factures et archives conservent leurs instantanés. Les anciens devis utilisent leur calcul historique jusqu’à une conversion explicite par le formulaire complet ; aucune migration ne recalcule les dossiers existants.
- Les valeurs XOF sont entières. Chaque ligne est arrondie au franc puis multipliée par le nombre d’exemplaires pour que le détail affiché s’additionne exactement. Les quantités décimales ne sont pas arrondies avant le calcul. L’ancienne taxe facultative est conservée : fournitures + frais + taxe = brut ; remise sur le brut ; acompte sur le définitif.
- Le workflow (`QuoteWorkflow`) est distinct du moteur financier. Il suit brouillon, validation/envoi, accord client, attente/réception de l’acompte, fabrication, pose, solde et clôture. Il ne déclenche aucun message par lui-même.

Variables supplémentaires des modèles e-mail/WhatsApp : `{{solde}}`, `{{remise}}`, `{{total_avant_remise}}`, `{{conditions_paiement}}`. Les modèles déjà personnalisés restent inchangés.

## Vérification

```shell
php vendor/bin/phpunit --filter 'QuoteCalculatorTest|QuotePricingIntegrationTest'
```

Les tests vérifient notamment 241 875 F CFA de fournitures, 30 000 F CFA de frais, 271 875 F CFA de total, 190 313 F CFA d’acompte et 81 562 F CFA de solde ; six stores à 405 000 F CFA ; remise de 10 % ; total négocié de 579 000 à 515 000 F CFA ; métrage de 6,625 m ; droits agents, instantanés, paiements et blocage de fabrication.
