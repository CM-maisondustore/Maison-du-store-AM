@php($c = config('site.legal'))
@php($mail = config('site.contact.email'))
<h2>Responsable du traitement</h2>
<p>{{ $c['company'] }}, {{ $c['address'] }}, <a href="mailto:{{ $mail }}">{{ $mail }}</a>. Les traitements décrits ici relèvent de la loi n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel ; @if($c['artci'])ils sont déclarés à l’ARTCI sous le numéro {{ $c['artci'] }}.@else leur déclaration auprès de l’ARTCI, autorité de protection des données en Côte d’Ivoire, est en cours.@endif</p>

<h2>Données collectées et usage</h2>
<div class="legal-table">
    <table>
        <thead><tr><th>Données</th><th>Pourquoi</th><th>Conservation</th></tr></thead>
        <tbody>
            <tr><td>Nom, téléphone, e-mail, adresse du chantier, dimensions des ouvertures</td><td>Établir le devis, organiser la visite du technicien et la pose</td><td>3 ans après le dernier contact sans commande</td></tr>
            <tr><td>Rendez-vous demandés</td><td>Organiser la rencontre au showroom, à domicile ou par téléphone</td><td>3 ans après le dernier contact sans commande</td></tr>
            <tr><td>Devis, commandes, reçus, historique des paiements</td><td>Exécuter la vente, garanties, obligations comptables</td><td>10 ans (loi n° 2013-546, art. 40)</td></tr>
            <tr><td>Messages WhatsApp échangés avec nous</td><td>Suivi du dossier client</td><td>3 ans après la fin du chantier</td></tr>
            <tr><td>Données de navigation (pages vues, source de la visite)</td><td>Mesurer la fréquentation du site, seulement si le visiteur l’accepte. Les adresses IP ne sont pas enregistrées.</td><td>13 mois</td></tr>
            <tr><td>Identifiants du personnel (Espace Pro)</td><td>Sécuriser l’accès au back-office</td><td>Durée du contrat de travail</td></tr>
        </tbody>
    </table>
</div>

<h2>Destinataires</h2>
<p>Les données sont réservées au personnel de Maison du Store et à ses prestataires techniques, chacun pour sa mission :</p>
<ul>
    <li>Hostinger : hébergement du site et de l’espace de gestion ;</li>
    <li>Meta : messagerie WhatsApp et, si vous l’acceptez, mesure de nos publicités Facebook et Instagram.</li>
</ul>
<p>La mesure de fréquentation est réalisée par notre propre outil, sur notre serveur. Certains prestataires stockent les données hors de Côte d’Ivoire. Les données ne sont jamais vendues.</p>

<h2 id="cookies">Cookies</h2>
<div class="legal-table">
    <table>
        <thead><tr><th>Catégorie</th><th>Exemples</th><th>Consentement</th></tr></thead>
        <tbody>
            <tr><td>Nécessaires</td><td>Session, sécurité des formulaires, mémorisation de votre choix</td><td>Non requis</td></tr>
            <tr><td>Mesure des visites</td><td>Identifiant de visite anonyme, outil interne Maison du Store</td><td>Requis</td></tr>
            <tr><td>Publicité</td><td>Pixel Meta (Facebook et Instagram)</td><td>Requis</td></tr>
        </tbody>
    </table>
</div>
<p>Votre choix est conservé 180 jours. Vous pouvez le modifier à tout moment avec le lien « Gérer les cookies » en bas de chaque page. Refuser n’empêche pas d’utiliser le site ni de demander un devis.</p>
<p><button type="button" class="btn btn-line" data-cookie-open>Gérer les cookies</button></p>

<h2>Droits des personnes</h2>
<p>Toute personne peut demander l’accès à ses données, leur rectification, leur suppression, ou s’opposer à leur utilisation, notamment pour les messages promotionnels. La demande s’adresse à <a href="mailto:{{ $mail }}">{{ $mail }}</a> ou par WhatsApp au +225 {{ config('site.contact.phones')[0]['label'] }}, avec un justificatif d’identité ; nous répondons sous 30 jours. En cas de désaccord, une réclamation peut être portée devant l’ARTCI (<a href="https://www.artci.ci" rel="noopener">artci.ci</a>).</p>

<h2>Messages promotionnels</h2>
<p>Les offres (nouvelle boutique, promotions) ne sont envoyées par WhatsApp qu’aux clients qui l’ont accepté. Chaque message permet de se désinscrire en répondant « STOP ».</p>

<h2>Sécurité</h2>
<p>Le site utilise une connexion chiffrée (HTTPS). L’accès à l’Espace Pro est nominatif, et les clés des services tiers sont conservées sur le serveur, jamais dans le navigateur.</p>
