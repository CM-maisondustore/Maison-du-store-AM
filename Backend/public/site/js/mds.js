/* Maison du Store — site public Stores & Rideaux, sans dépendance. Les demandes partent vers les API de l’espace pro. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var body = document.body;
  var routes = JSON.parse(body.dataset.routes || '{}');
  var csrf = ($('meta[name=csrf-token]') || {}).content || '';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.documentElement.classList.add('js');

  function request(url, opts) {
    opts = opts || {};
    return fetch(url, {
      method: opts.method || 'GET',
      credentials: 'same-origin',
      headers: Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, opts.body && !(opts.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}, opts.headers || {}),
      body: opts.body instanceof FormData ? opts.body : (opts.body ? JSON.stringify(opts.body) : undefined)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok) { var e = new Error('http'); e.status = r.status; e.data = data; throw e; }
        return data;
      });
    });
  }
  function networkError(e) {
    if (e && e.status === 419) { toast('error', 'Session expirée', 'Actualisez la page puis réessayez.'); return; }
    if (e && e.status === 429) { toast('error', 'Trop de tentatives', 'Patientez une minute avant de réessayer.'); return; }
    toast('error', 'Envoi impossible', (e && e.data && e.data.message) || 'Vérifiez votre connexion et réessayez.');
  }
  var digits = function (v) { return String(v || '').replace(/\D/g, ''); };
  var validTel = function (v) { var d = digits(v); if (d.length === 13 && d.indexOf('225') === 0) d = d.slice(3); return /^0\d{9}$/.test(d); };

  /* ---------- Notifications dans la pilule ---------- */
  var KINDS = {
    rdv: ['M4 5h16v16H4zM4 9h16M8 3v4M16 3v4M9 14l2 2 4-4', '#86cfa3'], devis: ['M6 3h9l4 4v14H6zM14 3v5h5M9 14l2 2 4-4', '#d9b77e'],
    sent: ['M4 12l5 5L20 6', '#86cfa3'], cart: ['M6 8h12l-1 12H7zM9 8V6a3 3 0 0 1 6 0v2', '#ffffff'],
    info: ['M12 8h.01M11 12h1v5h1M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18', '#9ec0e3'], error: ['M12 8v5M12 16h.01M10.3 3.9 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z', '#f28b80']
  };
  var notif = $('[data-notif]');
  var queue = [];
  var busy = false;
  var header = $('[data-header]');
  function toast(kind, title, text) { queue.push({ kind: kind, title: title, text: text }); pump(); }
  function pump() {
    if (busy || !queue.length || !notif) return;
    busy = true;
    var n = queue.shift();
    var K = KINDS[n.kind] || KINDS.info;
    var isErr = n.kind === 'error';
    var done = isErr ? ['M12 7.5v6M12 17v.01', '#f28b80'] : ['M5.5 12.5l4.2 4.2L18.5 8', '#86cfa3'];
    $('[data-live]').textContent = (n.title || '') + '. ' + (n.text || '');
    $('[data-n-title]', notif).textContent = n.title || '';
    $('[data-n-text]', notif).textContent = n.text || '';
    $('[data-n-path]', notif).setAttribute('d', K[0]);
    $('[data-n-kind]', notif).style.color = K[1];
    var check = $('[data-n-check]', notif);
    check.setAttribute('d', done[0]);
    $('[data-n-done]', notif).style.color = done[1];
    var arc = $('[data-n-arc]', notif);
    arc.style.transition = 'none'; arc.style.strokeDashoffset = 100; arc.style.stroke = K[1];
    $('[data-n-icon]', notif).style.background = 'rgba(255,255,255,0.08)';
    notif.classList.remove('on', 'done');
    notif.hidden = false;
    body.classList.add('has-notif');
    setTimeout(function () {
      void notif.offsetWidth;
      notif.classList.add('on');
      arc.style.transition = reduce ? 'none' : 'stroke-dashoffset 1300ms linear';
      arc.style.strokeDashoffset = 0;
    }, 30);
    setTimeout(function () {
      notif.classList.add('done');
      arc.style.stroke = done[1];
      $('[data-n-icon]', notif).style.background = done[1] + '2e';
      setTimeout(function () {
        notif.classList.remove('on');
        setTimeout(function () {
          notif.hidden = true;
          body.classList.remove('has-notif');
          busy = false;
          pump();
        }, reduce ? 0 : 200);
      }, reduce ? 900 : 720);
    }, 1330);
  }
  window.mdsToast = toast;
  if (body.dataset.toast) { try { var t0 = JSON.parse(body.dataset.toast); toast(t0.kind, t0.title, t0.text); } catch (e) {} }

  /* ---------- En-tête ---------- */
  var lastY = 0;
  window.addEventListener('scroll', function () {
    var y = window.scrollY || 0;
    if (window.innerWidth >= 1100) { header.classList.remove('is-hidden'); lastY = y; return; }
    if (y > lastY + 6 && y > 120) header.classList.add('is-hidden');
    else if (y < lastY - 6 || y < 60) header.classList.remove('is-hidden');
    lastY = y;
  }, { passive: true });

  var mega = $('#mds-mega');
  var megaBtn = $('[data-mega-toggle]');
  function setMega(open) { if (!mega) return; mega.hidden = !open; megaBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); var p = $('[data-pill-nav]'); if (p && window.innerWidth >= 1100) p.style.visibility = open ? 'hidden' : ''; }
  if (megaBtn) megaBtn.addEventListener('click', function () { setMega(mega.hidden); });
  function pickMega(k) {
    $$('[data-mega-pick]').forEach(function (b) { b.setAttribute('aria-expanded', b.dataset.megaPick === k ? 'true' : 'false'); });
    $$('[data-mega-panel]').forEach(function (p) { p.hidden = p.dataset.megaPanel !== k; });
  }
  $$('[data-mega-pick]').forEach(function (b) {
    b.addEventListener('click', function () { pickMega(b.dataset.megaPick); });
    b.addEventListener('mouseenter', function () { pickMega(b.dataset.megaPick); });
  });
  document.addEventListener('click', function (e) { if (mega && !mega.hidden && !e.target.closest('#mds-mega') && !e.target.closest('[data-mega-toggle]')) setMega(false); });

  var menu = $('[data-menu]');
  var openMenuBtn = $('[data-menu-open]');
  function setMenu(open) { menu.hidden = !open; openMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); document.documentElement.style.overflow = open ? 'hidden' : ''; if (open) $('[data-menu-close]', menu).focus(); else openMenuBtn.focus(); }
  if (openMenuBtn) openMenuBtn.addEventListener('click', function () { setMenu(true); });
  $$('[data-menu-close]').forEach(function (b) { b.addEventListener('click', function () { setMenu(false); }); });

  var search = $('[data-search]');
  function setSearch(open) { search.hidden = !open; if (open) $('input', search).focus(); }
  $$('[data-search-open]').forEach(function (b) { b.addEventListener('click', function () { setSearch(true); }); });
  $$('[data-search-close]').forEach(function (b) { b.addEventListener('click', function () { setSearch(false); }); });
  if (search) search.addEventListener('click', function (e) { if (e.target === search) setSearch(false); });


  /* ---------- Catalogue : filtres sans rechargement ---------- */
  var shopForm = $('[data-shop-form]');
  if (shopForm) {
    var results = $('[data-results]');
    var groupsBox = $('[data-filter-groups]');
    var chipsBox = $('[data-type-chips]');
    var shown = 0;
    var ctrl = null;
    var timer = null;
    var buildParams = function () {
      var p = new URLSearchParams();
      $$('input, select', shopForm).forEach(function (el) {
        if ((el.type === 'checkbox' && !el.checked) || !el.name || !el.value) return;
        if (el.name === 'sort' && el.value === 'pert') return;
        p.append(el.name, el.value);
      });
      if (shown) p.set('shown', shown);
      return p;
    };
    var refresh = function () {
      var openGroup = ($('.fg.open', shopForm) || { dataset: {} }).dataset.group;
      var params = buildParams();
      var qs = params.toString();
      var url = shopForm.getAttribute('action') + (qs ? '?' + qs : '');
      if (ctrl) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      fetch(url, { headers: { 'X-Partial': '1', 'Accept': 'application/json' }, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          results.innerHTML = d.results;
          groupsBox.innerHTML = d.filters;
          if (chipsBox) chipsBox.innerHTML = d.chips;
          if (openGroup) { $$('.fg', groupsBox).forEach(function (g) { if (g.dataset.group === openGroup) { g.classList.add('open'); $('.fg-btn', g).setAttribute('aria-expanded', 'true'); } }); }
          updateFilterLabels(d.total);
          history.replaceState(null, '', url);
        })
        .catch(function (e) { if (e.name !== 'AbortError') networkError(e); });
    };
    var updateFilterLabels = function (total) {
      var n = $$('input[type=checkbox]:checked', shopForm).length;
      var l = $('[data-filters-label]'); if (l) l.textContent = n ? 'Filtres (' + n + ')' : 'Filtres';
      var see = $('[data-see-label]'); if (see) see.textContent = 'Voir les ' + total + ' produits';
    };
    updateFilterLabels(+($('[data-result-label]').dataset.total || 0));
    shopForm.addEventListener('submit', function (e) { e.preventDefault(); shown = 0; refresh(); });
    shopForm.addEventListener('change', function (e) { if (e.target.matches('input[type=checkbox], select')) { shown = 0; refresh(); } });
    $('[data-shop-q]').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { shown = 0; refresh(); }, 250); });
    var clearAll = function () { $$('input[type=checkbox]', shopForm).forEach(function (c) { c.checked = false; }); $('[data-shop-q]').value = ''; shown = 0; refresh(); };
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-filters-reset]')) { e.preventDefault(); clearAll(); return; }
      var chip = e.target.closest('[data-chip-group]');
      if (chip) {
        $$('input[type=checkbox]', shopForm).forEach(function (c) { if (c.name === 'f[' + chip.dataset.chipGroup + '][]' && c.value === chip.dataset.chipValue) c.checked = false; });
        shown = 0; refresh(); return;
      }
      var more = e.target.closest('[data-more]');
      if (more) { shown = +more.dataset.more; refresh(); return; }
      var tc = e.target.closest('[data-type-chip]');
      if (tc) {
        var val = tc.dataset.typeChip;
        $$('input[name="f[Type][]"]', shopForm).forEach(function (c) { c.checked = !!val && c.value === val; });
        shown = 0; refresh(); return;
      }
      var fb = e.target.closest('[data-fg-toggle]');
      $$('.fg', shopForm).forEach(function (g) {
        var isThis = fb && g.contains(fb);
        var inside = g.contains(e.target);
        if (isThis) { var o = !g.classList.contains('open'); g.classList.toggle('open', o); fb.setAttribute('aria-expanded', o ? 'true' : 'false'); }
        else if (!inside) { g.classList.remove('open'); $('.fg-btn', g).setAttribute('aria-expanded', 'false'); }
      });
      if (e.target.closest('[data-sheet-open]')) shopForm.classList.add('sheet-open');
      if (e.target.closest('[data-sheet-close]') || e.target.matches('[data-sheet-scrim]')) shopForm.classList.remove('sheet-open');
    });
  }


  /* ---------- Accueil ---------- */
  var hero = $('[data-hero]');
  if (hero) {
    var tabs = $$('[data-hero-tab]');
    var heroPanels = $$('[data-hero-panel]');
    var idx = 0;
    var playing = !reduce;
    var hold = false;
    var playBtn = $('[data-hero-play]');
    var playIcon = $('[data-hero-play-i] path') || $('path', playBtn);
    var showHero = function (i) {
      idx = i;
      tabs.forEach(function (t, n) { t.setAttribute('aria-selected', n === i ? 'true' : 'false'); t.tabIndex = n === i ? 0 : -1; });
      heroPanels.forEach(function (p, n) { p.hidden = n !== i; });
      restartFill();
    };
    var restartFill = function () { hero.classList.remove('playing'); void hero.offsetWidth; hero.classList.toggle('playing', playing && !hold); };
    var setPlay = function (on) {
      playing = on;
      playBtn.setAttribute('aria-label', on ? 'Mettre en pause la rotation' : 'Reprendre la rotation');
      playIcon.setAttribute('d', on ? 'M8 5v14M16 5v14' : 'M7 5l12 7-12 7z');
      restartFill();
    };
    tabs.forEach(function (t, n) {
      t.addEventListener('click', function () { setPlay(false); showHero(n); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { var to = (n + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length; setPlay(false); showHero(to); tabs[to].focus(); }
      });
    });
    playBtn.addEventListener('click', function () { setPlay(!playing); });
    hero.addEventListener('mouseenter', function () { hold = true; restartFill(); });
    hero.addEventListener('mouseleave', function () { hold = false; restartFill(); });
    setPlay(playing);
    setInterval(function () { if (playing && !hold && !document.hidden) showHero((idx + 1) % tabs.length); }, 6000);
  }

  var field = $('[data-bubbles]');
  if (field) {
    var pool = JSON.parse(field.dataset.pool || '[]');
    var SLOTS = [[1, 6, 164], [19, 38, 146], [37, 2, 182], [55, 40, 156], [71, 6, 146], [84, 36, 166]];
    var shuffleArr = function (a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; };
    var make = function (p) {
      var a = document.createElement('a');
      a.className = 'bubble'; a.href = p.href; a.dataset.shop = p.shop; a.setAttribute('aria-label', 'Voir ' + p.name);
      var c = document.createElement('span'); c.className = 'bubble-c'; c.setAttribute('aria-hidden', 'true');
      if (p.img) { var im = document.createElement('img'); im.src = p.img; im.alt = ''; im.loading = 'lazy'; c.appendChild(im); }
      else c.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M3 5h18v14H3zM7 10a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0M21 16l-5-5-8 8"></path></svg>';
      var l = document.createElement('span'); l.className = 'bubble-l';
      var dot = document.createElement('span'); dot.className = 'dot dot-10'; dot.setAttribute('aria-hidden', 'true');
      var t = document.createElement('span'); t.className = 'bubble-t';
      var n = document.createElement('span'); n.className = 'bubble-n'; n.textContent = p.name;
      var s = document.createElement('span'); s.className = 'bubble-s'; s.textContent = p.label;
      t.appendChild(n); t.appendChild(s); l.appendChild(dot); l.appendChild(t); a.appendChild(c); a.appendChild(l);
      return a;
    };
    var nodes = {};
    var draw = function () {
      var shops = ['rideaux', 'stores', 'electro', 'meubles'];
      var picks = [];
      shops.forEach(function (k) { var l = pool.filter(function (p) { return p.shop === k; }); if (l.length) picks.push(l[Math.floor(Math.random() * l.length)]); });
      shuffleArr(pool).forEach(function (p) { if (picks.length < 6 && picks.indexOf(p) < 0) picks.push(p); });
      var slots = shuffleArr(SLOTS);
      field.innerHTML = '';
      shuffleArr(picks).forEach(function (p, i) {
        var b = nodes[p.href] || (nodes[p.href] = make(p));
        var sl = slots[i];
        b.style.left = sl[0] + '%'; b.style.top = sl[1] + '%';
        var c = $('.bubble-c', b); c.style.width = sl[2] + 'px'; c.style.height = sl[2] + 'px';
        field.appendChild(b);
      });
    };
    draw();
    $('[data-bubbles-shuffle]').addEventListener('click', draw);
    if (!reduce) setInterval(function () { if (!document.hidden && !field.matches(':hover') && !field.contains(document.activeElement)) draw(); }, 5500);
  }

  $$('[data-gal]').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('[data-gal]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      $$('[data-gal-item]').forEach(function (f) { f.hidden = b.dataset.gal !== 'all' && f.dataset.galItem !== b.dataset.gal; });
    });
  });


  /* ---------- Fiche produit : partage et panneaux ---------- */
  var pr = $('[data-product]');
  if (pr) {
    var share = $('[data-share]');
    if (share) share.addEventListener('click', function () {
      if (navigator.share) { navigator.share({ title: pr.dataset.name, url: share.dataset.url }).catch(function () {}); return; }
      if (navigator.clipboard) navigator.clipboard.writeText(share.dataset.url).then(function () { toast('info', 'Lien copié', pr.dataset.name + '.'); });
    });
    var panels = $$('[data-panel]');
    if (window.innerWidth >= 768) panels.forEach(function (p) { p.open = true; });
    else panels.forEach(function (p) { p.addEventListener('toggle', function () { if (p.open) panels.forEach(function (o) { if (o !== p) o.open = false; }); }); });
  }

  /* Numéro saisi en Côte d'Ivoire (10 chiffres) : envoyé avec l'indicatif +225, comme le formulaire historique. */
  var intlPhone = function (v) {
    var raw = String(v || '').replace(/[\s.\-()]/g, '');
    if (raw.indexOf('00') === 0) raw = '+' + raw.slice(2);
    if (raw.charAt(0) === '+') return raw;
    return /^0\d{9}$/.test(raw) ? '+225' + raw : raw;
  };
  var okPhone = function (v) { return /^\+?\d{8,15}$/.test(intlPhone(v)); };
  var okMail = function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').trim()); };
  var firstError = function (err) { var e = err && err.data && err.data.errors; if (!e) return null; var k = Object.keys(e)[0]; return { key: k, msg: e[k][0] }; };

  /* ---------- Rendez-vous : envoyé à l'API de l'espace pro ---------- */
  var rdv = $('[data-rdv]');
  if (rdv) {
    var rdvBar = $('[data-rdv-bar]');
    var recapEl = $('[data-rdv-recap]');
    var rSent = false;
    var checked = function (n) { return $('input[name=' + n + ']:checked', rdv); };
    var fieldOk = {
      lastName: function () { return $('#rdv-nom').value.trim().length > 1; },
      firstName: function () { return $('#rdv-prenom').value.trim().length > 1; },
      phone: function () { return okPhone($('#rdv-tel').value); },
      email: function () { return okMail($('#rdv-mail').value); }
    };
    var missingRdv = function () {
      var labels = { lastName: 'votre nom', firstName: 'votre prénom', phone: 'votre téléphone', email: 'votre e-mail' };
      return Object.keys(fieldOk).filter(function (k) { return !fieldOk[k](); }).map(function (k) { return labels[k]; });
    };
    var renderRdv = function (tried) {
      var projects = $$('input[name="projects[]"]:checked', rdv).map(function (i) { return i.value; });
      var date = checked('date'); var time = checked('time'); var place = checked('appointmentType');
      var rows = [['Boutique', 'Stores & Rideaux'], ['Projet', projects.length ? projects.join(', ') : 'À préciser'], ['Lieu', place ? place.dataset.label : ''], ['Date', date ? date.dataset.label : 'À choisir'], ['Heure', time ? time.value : 'À choisir']];
      recapEl.innerHTML = '';
      rows.forEach(function (r) { var d = document.createElement('div'); var t = document.createElement('dt'); t.textContent = r[0]; var v = document.createElement('dd'); v.textContent = r[1]; d.appendChild(t); d.appendChild(v); recapEl.appendChild(d); });
      var m = missingRdv();
      $('[data-bar-1]').classList.toggle('on', !m.length);
      var title = $('[data-rdv-title]'); var sub = $('[data-rdv-sub]');
      if (rSent) { title.textContent = 'Rendez-vous demandé'; sub.textContent = 'Un conseiller vous appelle sous 24 h ouvrées.'; sub.className = 'bar-s ok'; return; }
      title.textContent = (place ? place.dataset.label : '') + (date ? ', ' + date.dataset.label : '') + (time ? ' à ' + time.value : '');
      sub.textContent = m.length ? 'Il manque ' + m.join(', ') : 'Tout est prêt, vous pouvez confirmer.';
      sub.className = 'bar-s ' + (m.length && tried ? 'ko' : '');
    };
    var loadSlots = function () {
      var date = checked('date'); if (!date) return;
      fetch(rdv.dataset.availability + '?date=' + encodeURIComponent(date.value), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var off = (d.data && d.data.unavailable) || [];
          var inputs = $$('input[name=time]', rdv);
          inputs.forEach(function (i) { i.disabled = off.indexOf(i.value) >= 0; if (i.disabled) i.checked = false; });
          if (!checked('time')) { var free = inputs.filter(function (i) { return !i.disabled; })[0]; if (free) free.checked = true; }
          renderRdv(false);
        }).catch(function () {});
    };
    $$('input[name=date]', rdv).forEach(function (i) { i.addEventListener('change', loadSlots); });
    var reset = function () { rSent = false; $('[data-rdv-cta]').textContent = 'Confirmer'; renderRdv(false); };
    rdv.addEventListener('input', reset);
    rdv.addEventListener('change', reset);
    renderRdv(false); loadSlots();
    rdv.addEventListener('submit', function (e) {
      e.preventDefault();
      if (rSent) return;
      var m = missingRdv();
      $('#rdv-nom').classList.toggle('bad', !fieldOk.lastName()); $('#rdv-prenom').classList.toggle('bad', !fieldOk.firstName());
      $('#rdv-tel').classList.toggle('bad', !fieldOk.phone()); $('#rdv-mail').classList.toggle('bad', !fieldOk.email());
      if (m.length) { renderRdv(true); toast('error', 'Il manque des informations', 'Indiquez ' + m.join(', ') + '.'); var bad = $('.bad', rdv); if (bad) bad.focus(); return; }
      if (!checked('date') || !checked('time')) { toast('error', 'Choisissez un créneau', 'Sélectionnez une date et une heure disponibles.'); return; }
      var projects = $$('input[name="projects[]"]:checked', rdv).map(function (i) { return i.value; });
      var body = {
        lastName: $('#rdv-nom').value.trim(), firstName: $('#rdv-prenom').value.trim(),
        email: $('#rdv-mail').value.trim(), phone: intlPhone($('#rdv-tel').value),
        appointmentType: checked('appointmentType').value,
        project: ('Stores & Rideaux' + (projects.length ? ' : ' + projects.join(', ') : '')).slice(0, 200),
        message: ($('[name=message]', rdv).value || '').trim() || null,
        date: checked('date').value, time: checked('time').value, submittedAt: new Date().toISOString()
      };
      var cta = $('[data-rdv-cta]'); cta.disabled = true;
      request(rdv.action, { method: 'POST', body: body }).then(function () {
        rSent = true; renderRdv(false);
        cta.textContent = 'Envoyée';
        if (window.MDS && MDS.analytics) MDS.analytics.track('appointment_received');
        if (window.MDS && MDS.metaPixel) MDS.metaPixel.trackSchedule();
        toast('rdv', 'Rendez-vous demandé', checked('date').dataset.label + ' à ' + body.time + '. Un conseiller vous confirme sous 24 h ouvrées.');
        loadSlots();
      }).catch(function (err) {
        var f = err.status === 422 && firstError(err);
        if (f) { toast('error', 'Vérifiez le formulaire', f.msg); if (f.key === 'time') loadSlots(); return; }
        networkError(err);
      }).then(function () { cta.disabled = false; });
    });
    if (window.IntersectionObserver) {
      new IntersectionObserver(function (en) { rdvBar.hidden = !en[0].isIntersecting; }, { threshold: 0.12 }).observe($('[data-rdv-section]'));
    } else rdvBar.hidden = false;
  }

  /* ---------- Demande de devis : envoyée à /api/quotes/stores ou /api/quotes/curtains ---------- */
  var dv = $('[data-devis2]');
  if (dv) {
    var D = JSON.parse($('#dv-data').textContent || '{}');
    var endpoints = JSON.parse(dv.dataset.endpoints);
    var labels = JSON.parse(dv.dataset.steps);
    var stepsEls = $$('.dv-step', dv);
    var N = stepsEls.length;
    var step = 1;
    var root = $('[data-dv-root]');
    var linesBox = $('[data-dv-lines]');
    var tpl = linesBox.firstElementChild.cloneNode(true);
    var STORE_COLORS = ['blanc', 'ecru', 'beige', 'taupe', 'gris', 'anthracite'];
    var val = function (name) { var el = $('input[name=' + name + ']:checked', dv); return el ? el.value : ''; };
    var type = function () { return val('type') || 'store'; };
    var model = function () { return (D.models || []).filter(function (m) { return m.id === val('model'); })[0] || null; };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var card = function (name, value, title, text, img, on) {
      var media = img ? '<img src="' + esc(img) + '" alt="" loading="lazy">' : '<span class="dv-card-ph" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M3 5h18v14H3zM7 10a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0M21 16l-5-5-8 8"></path></svg></span>';
      return '<label class="dv-card"><input type="radio" name="' + name + '" value="' + esc(value) + '"' + (on ? ' checked' : '') + '><span class="dv-card-b">' + media + '<span class="serif-22">' + esc(title) + '</span>' + (text ? '<span class="small">' + esc(text) + '</span>' : '') + '</span></label>';
    };
    var fill = function (box, name, list, keep, emptyCard) {
      var prev = keep ? val(name) : '';
      var html = (emptyCard ? card(name, '', emptyCard, '', null, false) : '') + list.map(function (o) { return card(name, o.id, o.label || o.name, o.text, o.imageUrl, o.id === prev); }).join('');
      box.innerHTML = html;
    };
    var renderModels = function () {
      var t = type(); var prev = val('model') || D.preset;
      var list = (D.models || []).filter(function (m) { return m.category === t; });
      var box = $('[data-dv-models]');
      box.innerHTML = list.map(function (m) { return card('model', m.id, m.name, m.text, m.imageUrl, m.id === prev); }).join('');
      $('[data-dv-models-empty]').hidden = list.length > 0;
      root.dataset.shop = t === 'rideau' ? 'rideaux' : 'stores';
    };
    var options = function () {
      var t = type(); var m = model(); var pid = m ? m.productId : null; var control = val('control');
      return (D.options || []).filter(function (o) {
        if (o.category !== t) return false;
        if (o.product_ids && o.product_ids.length && o.product_ids.indexOf(pid) < 0) return false;
        if (t === 'store' && control && control !== 'inconnu' && o.motorization && o.motorization !== 'any' && o.motorization !== (control === 'motorisee' ? 'yes' : 'no')) return false;
        return true;
      });
    };
    var lists = function () {
      var t = type(); var ops = options();
      if (t === 'store') return { fabric: ops.filter(function (o) { return o.kind === 'material'; }), sheer: [], finish: ops.filter(function (o) { return o.kind === 'finish'; }) };
      return {
        fabric: ops.filter(function (o) { return o.kind === 'material' && (o.fabric_group || o.category) === 'rideau'; }),
        sheer: ops.filter(function (o) { return o.kind === 'material' && o.fabric_group === 'voilage'; }),
        finish: D.finishes || []
      };
    };
    var renderChoices = function () {
      var t = type(); var L = lists();
      $$('[data-for]', dv).forEach(function (el) { el.hidden = el.dataset.for !== t; $$('input', el).forEach(function (i) { i.disabled = el.hidden; if (el.hidden && i.checked && i.name === 'control') { i.checked = false; } }); });
      if (!val('control')) $('input[name=control][value=manuelle]', dv).checked = true;
      $('[data-dv-fabric-label]').innerHTML = t === 'store' ? 'Toile' : 'Tissu de rideau <span class="light">(facultatif si un voilage est choisi)</span>';
      fill($('[data-dv-cards=fabric]'), 'fabric', L.fabric, true, t === 'rideau' ? 'Sans rideau' : null);
      fill($('[data-dv-cards=sheer]'), 'sheer', L.sheer, true, 'Sans voilage');
      fill($('[data-dv-cards=finish]'), 'finish', L.finish, true, null);
      $('[data-dv-group=finish]').hidden = !L.finish.length;
      var colors = (D.colors || []).filter(function (c) { return t === 'rideau' || STORE_COLORS.indexOf(c.id) >= 0; });
      var prevC = val('color');
      $('[data-dv-colors]').innerHTML = colors.map(function (c) { return '<label class="pick"><input type="radio" name="color" value="' + esc(c.id) + '"' + (c.id === prevC ? ' checked' : '') + '><span><span aria-hidden="true" class="sw" style="background:' + esc(c.hex) + '"></span> ' + esc(c.label) + '</span></label>'; }).join('');
    };
    var labelOf = function (list, id) { var o = list.filter(function (x) { return x.id === id; })[0]; return o ? (o.label || o.name) : ''; };
    var readLines = function () {
      return $$('[data-dv-line]', linesBox).map(function (li, i) {
        var g = function (k) { return ($('[data-k=' + k + ']', li).value || '').trim(); };
        return { id: 'f' + (i + 1), label: g('label') || null, width: g('width').replace(',', '.'), height: g('height').replace(',', '.'), quantity: g('quantity') };
      });
    };
    var renderRecap = function () {
      var t = type(); var m = model(); var L = lists(); var lines = readLines().filter(function (l) { return l.width || l.height; });
      var colorEl = $('input[name=color]:checked', dv);
      var rows = [['Produit', t === 'store' ? 'Store' : 'Rideaux et voilages'], ['Modèle', m ? m.name : 'À choisir'], [t === 'store' ? 'Toile' : 'Tissu', labelOf(L.fabric, val('fabric')) || (t === 'rideau' && val('fabric') === '' && $('input[name=fabric]:checked', dv) ? 'Sans rideau' : 'À choisir')]];
      if (t === 'rideau') rows.push(['Voilage', labelOf(L.sheer, val('sheer')) || 'Sans voilage']);
      if (L.finish.length) rows.push(['Finition', labelOf(L.finish, val('finish')) || 'À choisir']);
      rows.push(['Manœuvre', { manuelle: 'Manuelle', motorisee: 'Motorisée', inconnu: 'À définir' }[val('control')] || '']);
      rows.push(['Coloris', colorEl ? colorEl.parentNode.textContent.trim() : 'À choisir']);
      rows.push(['Fenêtres', lines.length ? lines.length + (lines.length > 1 ? ' fenêtres' : ' fenêtre') : 'À préciser']);
      var box = $('[data-dv-recap]'); box.innerHTML = '';
      rows.forEach(function (r) { var d = document.createElement('div'); var dt = document.createElement('dt'); dt.textContent = r[0]; var dd = document.createElement('dd'); dd.textContent = r[1]; d.appendChild(dt); d.appendChild(dd); box.appendChild(d); });
    };
    var renumber = function () { $$('[data-dv-line]', linesBox).forEach(function (li, i) { $('[data-dv-line-n]', li).textContent = 'Fenêtre ' + (i + 1); }); $('[data-dv-add]').hidden = $$('[data-dv-line]', linesBox).length >= 8; };
    $('[data-dv-add]').addEventListener('click', function () {
      if ($$('[data-dv-line]', linesBox).length >= 8) return;
      var li = tpl.cloneNode(true); linesBox.appendChild(li); renumber(); renderRecap(); $('input', li).focus();
    });
    linesBox.addEventListener('click', function (e) {
      var r = e.target.closest('[data-dv-remove]'); if (!r) return;
      var all = $$('[data-dv-line]', linesBox); var li = r.closest('[data-dv-line]');
      if (all.length > 1) li.remove(); else $$('input', li).forEach(function (i) { i.value = i.dataset.k === 'quantity' ? '1' : ''; });
      renumber(); renderRecap();
    });

    /* Contrôles par étape, alignés sur la validation serveur de l'espace pro. */
    var problems = function (s) {
      var t = type(); var L = lists(); var out = [];
      if (s === 1) { if (!model()) out.push('choisissez un modèle'); }
      if (s === 2) {
        if (t === 'store') { if (!L.fabric.length) out.push('aucune toile n’est disponible en ligne pour ce modèle, appelez-nous'); else if (!val('fabric')) out.push('choisissez une toile'); }
        else if (!val('fabric') && !val('sheer')) out.push('choisissez un tissu de rideau ou un voilage');
        if (t === 'rideau' && !L.finish.length) out.push('les finitions ne sont pas encore proposées en ligne, appelez-nous');
        else if (L.finish.length && !val('finish')) out.push('choisissez une finition');
        if (!val('color')) out.push('choisissez un coloris');
      }
      if (s === 3) {
        var lines = readLines();
        lines.forEach(function (l, i) {
          var w = parseFloat(l.width), h = parseFloat(l.height), q = +l.quantity;
          if (!(w >= 20 && w <= 400) || !(h >= 20 && h <= 400)) out.push('fenêtre ' + (i + 1) + ' : largeur et hauteur entre 20 et 400 cm');
          if (!(q >= 1 && q <= 20 && Math.floor(q) === q)) out.push('fenêtre ' + (i + 1) + ' : quantité entre 1 et 20');
        });
      }
      if (s === 4) {
        var f = function (n) { var el = $('[name=' + n + ']', dv); return el ? el.value.trim() : ''; };
        if (!val('civility')) out.push('votre civilité');
        if (f('lastName').length < 2) out.push('votre nom');
        if (f('firstName').length < 2) out.push('votre prénom');
        if (!okPhone(f('phone'))) out.push('un téléphone valide');
        if (f('email') && !okMail(f('email'))) out.push('un e-mail valide');
        if (f('address').length < 4) out.push('votre adresse ou quartier');
        if (!$('[name=acceptTerms]', dv).checked) out.push('votre accord pour être recontacté');
      }
      return out;
    };

    var nextBtn = $('[data-dv-next]'); var prevBtn = $('[data-dv-prev]'); var nextIcon = $('svg path', nextBtn);
    var errBox = $('[data-dv-error]');
    var renderStep = function (scroll) {
      stepsEls.forEach(function (s, i) { s.classList.toggle('on', i === step - 1); });
      $$('[data-dv-steps] li').forEach(function (li, i) { li.classList.toggle('cur', i === step - 1); li.classList.toggle('done', i < step - 1); if (i === step - 1) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current'); });
      $$('[data-dv-rail] span').forEach(function (d, i) { d.classList.toggle('on', i < step - 1); });
      $('[data-dv-bar-title]').textContent = 'Étape ' + step + ' sur ' + N + ' : ' + labels[step - 1].toLowerCase();
      var last = step === N;
      prevBtn.disabled = step === 1;
      $('[data-dv-next-label]').textContent = last ? 'Envoyer' : 'Continuer';
      nextIcon.setAttribute('d', last ? 'M4 12 20 4l-6 16-3-7z' : 'M5 12h14M13 6l6 6-6 6');
      $('[data-dv-bar-sub]').textContent = last ? 'Votre demande part dans notre espace de gestion' : 'Rien n’est envoyé avant la dernière étape';
      $('[data-dv-bar-sub]').className = 'bar-s';
      if (scroll) { var h = $('#demande-t'); if (h) h.scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' }); }
    };
    var block = function (p) {
      var s = $('[data-dv-bar-sub]'); s.textContent = 'Il manque : ' + p.join(', '); s.className = 'bar-s ko';
      toast('error', 'Il manque des informations', p[0].charAt(0).toUpperCase() + p[0].slice(1) + '.');
    };
    var STEP_OF = function (key) { return key === 'product.model' ? 1 : key.indexOf('product.') === 0 ? 2 : key.indexOf('dimensions') === 0 ? 3 : 4; };
    var sending = false;
    var send = function () {
      var t = type(); var m = model(); var f = function (n) { var el = $('[name=' + n + ']', dv); return el ? el.value.trim() : ''; };
      var product = { model: m.id, control: val('control'), color: val('color') };
      if (val('fabric')) product.fabric = val('fabric');
      if (val('finish')) product.finish = val('finish');
      if (t === 'store') product.orderType = val('orderType') || 'installation';
      else if (val('sheer')) product.sheer = val('sheer');
      var body = {
        contact: { civility: val('civility'), lastName: f('lastName'), firstName: f('firstName'), email: f('email') || null, phoneCountryCode: f('phoneCountryCode') || '+225', phone: f('phone'), address: f('address'), city: f('city'), notes: f('notes') || null },
        product: product,
        dimensions: readLines().map(function (l) { return { id: l.id, label: l.label, width: l.width, height: l.height, quantity: +l.quantity }; }),
        acceptTerms: true, submittedAt: new Date().toISOString()
      };
      sending = true; nextBtn.disabled = true; errBox.hidden = true;
      request(endpoints[t], { method: 'POST', body: body }).then(function (d) {
        var ref = d.data && d.data.reference;
        dv.hidden = true; $('[data-dv-aside]').hidden = true; $('[data-dv-bar]').hidden = true;
        $$('[data-dv-steps] li').forEach(function (li) { li.classList.remove('cur'); li.classList.add('done'); });
        $('[data-dv-sent-text]').textContent = 'Merci ' + body.contact.firstName + '. ' + (ref ? 'Votre demande ' + ref + ' est enregistrée. ' : '') + 'Un conseiller vous rappelle au ' + body.contact.phone + ' pour fixer la prise de mesures.';
        $('[data-dv-sent]').hidden = false;
        toast('devis', 'Demande de devis envoyée', 'Un conseiller vous rappelle rapidement.');
        if (window.MDS && MDS.analytics) MDS.analytics.track('quote_received', { target: t });
        if (window.MDS && MDS.metaPixel) MDS.metaPixel.trackContact();
        var h = $('#demande-t'); if (h) h.scrollIntoView({ block: 'start' });
      }).catch(function (err) {
        var fe = err.status === 422 && firstError(err);
        if (fe) { step = STEP_OF(fe.key); renderStep(true); errBox.textContent = fe.msg; errBox.hidden = step !== N; toast('error', 'Vérifiez le formulaire', fe.msg); return; }
        networkError(err);
      }).then(function () { sending = false; nextBtn.disabled = false; });
    };
    prevBtn.addEventListener('click', function () { if (step > 1) { step--; renderStep(true); } });
    nextBtn.addEventListener('click', function () {
      if (sending) return;
      var p = problems(step); if (p.length) { block(p); return; }
      if (step < N) { step++; renderStep(true); return; }
      send();
    });
    dv.addEventListener('submit', function (e) { e.preventDefault(); nextBtn.click(); });
    dv.addEventListener('change', function (e) {
      if (e.target.name === 'type') { renderModels(); renderChoices(); }
      if (e.target.name === 'model' || e.target.name === 'control') renderChoices();
      renderRecap();
    });
    dv.addEventListener('input', renderRecap);
    renderModels(); renderChoices(); renumber(); renderRecap(); renderStep(false);
    if (D.preset && model()) { step = 2; renderStep(false); }
  }

  /* ---------- Échap ferme ce qui est ouvert ---------- */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (menu && !menu.hidden) setMenu(false);
    if (search && !search.hidden) setSearch(false);
    if (mega && !mega.hidden) setMega(false);
    if (shopForm) {
      shopForm.classList.remove('sheet-open');
      $$('.fg.open', shopForm).forEach(function (g) { g.classList.remove('open'); $('.fg-btn', g).setAttribute('aria-expanded', 'false'); });
    }
  });
})();
