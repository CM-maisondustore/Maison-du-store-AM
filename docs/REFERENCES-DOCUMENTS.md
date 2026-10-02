# Références des documents

Les nouveaux devis utilisent `AM-AAAA-MM-JJ-ST-112` (stores) ou `AM-AAAA-MM-JJ-RV-113` (rideaux et voilages). Un compteur commun en base commence à 112, augmente pour chaque nouveau devis et ne se réinitialise pas au changement de jour, mois ou année. Le numéro comporte au moins trois chiffres et continue après 999.

La date est celle du devis saisie lors de sa création, ou le jour de création en heure d’Abidjan. Un devis mixte prend le code de sa catégorie principale. La référence attribuée est conservée si la date ou la catégorie sont ensuite modifiées.

La facture émise reprend la référence du devis avec `-FAC`. Les reçus sont numérotés dans l’ordre de leur première édition, pour chaque devis : `-REC-01`, `-REC-02`, etc. Une réimpression reprend le numéro et le PDF archivés ; l’annulation d’un paiement ne libère pas son numéro de reçu. Ces suffixes ne consomment pas un nouveau numéro dans la série des devis.

Les références et fichiers existants ne sont pas renumérotés. Une nouvelle facture ou un nouveau reçu lié à un ancien devis reprend sa référence historique avec le suffixe correspondant. Les nouveaux numéros sont utilisés par les PDF, les listes et les modèles de messages qui affichent la référence enregistrée.

La migration `2026_10_02_000001_document_references.php` élargit le champ référence et crée `document_sequences`. L’attribution utilise une transaction et un verrou de ligne pour sérialiser les créations simultanées. Aucun seeder ne remet le compteur à zéro. Le rollback applicatif conserve la table et sa valeur ; ne jamais réinitialiser la série en production.
