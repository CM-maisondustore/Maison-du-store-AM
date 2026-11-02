document.addEventListener('click', event => {
  const add = event.target.closest('[data-add]');
  if (add) {
    const container = document.getElementById(add.dataset.add);
    const template = document.getElementById(add.dataset.add + '-template');
    const indexes = [...container.querySelectorAll('[name]')].map(input => Number(input.name.match(/\[(\d+)\]/)?.[1] ?? -1));
    const index = Number(container.dataset.next || Math.max(-1,...indexes) + 1);
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace('__INDEX__', index); });
    container.appendChild(fragment); container.dataset.next = index + 1;
  }
  const remove = event.target.closest('[data-remove]');
  if (remove) remove.closest('.line-row').remove();
  const open = event.target.closest('[data-open-dialog]');
  if (open) document.getElementById(open.dataset.openDialog)?.showModal();
  const close = event.target.closest('[data-close-dialog]');
  if (close) close.closest('dialog')?.close();
  // Cliquer hors d'un menu ouvert le referme.
  document.querySelectorAll('details.menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') document.querySelectorAll('details.menu[open]').forEach(menu => { menu.open = false; menu.querySelector('summary')?.focus(); });
});

/* Confirmation : boîte de dialogue accessible, avec repli sur confirm(). */
document.addEventListener('submit', event => {
  const form = event.target, message = form.dataset.confirm;
  if (!message || form.dataset.confirmed === '1') return;
  event.preventDefault();
  const dialog = document.getElementById('confirm-dialog');
  if (!dialog?.showModal) { if (window.confirm(message)) { form.dataset.confirmed = '1'; form.requestSubmit(event.submitter || undefined); } return; }
  dialog.querySelector('[data-confirm-text]').textContent = message;
  dialog.querySelector('[data-confirm-ok]').onclick = () => { dialog.close(); form.dataset.confirmed = '1'; form.requestSubmit(event.submitter || undefined); };
  dialog.showModal();
});

/* Barre latérale repliable ; le choix reste sur l'appareil. */
document.querySelector('[data-collapse]')?.addEventListener('click', event => {
  const root = document.documentElement, collapsed = root.dataset.sidebar !== 'collapsed';
  if (collapsed) root.dataset.sidebar = 'collapsed'; else delete root.dataset.sidebar;
  event.currentTarget.setAttribute('aria-expanded', String(!collapsed));
  try { localStorage.setItem('mds-sidebar', collapsed ? 'collapsed' : 'open'); } catch (_) {}
});

/* Onglets dans une page : flèches gauche et droite au clavier. */
document.querySelectorAll('[data-tabs]').forEach(group => {
  const tabs = [...group.querySelectorAll('[role=tab]')];
  const show = tab => tabs.forEach(item => {
    const on = item === tab;
    item.setAttribute('aria-selected', String(on));
    item.setAttribute('aria-current', String(on));
    item.tabIndex = on ? 0 : -1;
    const panel = document.getElementById(item.getAttribute('aria-controls'));
    if (panel) panel.hidden = !on;
  });
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', event => { event.preventDefault(); show(tab); });
    tab.addEventListener('keydown', event => {
      const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
      if (!step) return;
      event.preventDefault();
      const next = tabs[(index + step + tabs.length) % tabs.length];
      show(next); next.focus();
    });
  });
  if (tabs.length) show(tabs.find(tab => tab.getAttribute('aria-selected') === 'true') || tabs[0]);
});

/* Brouillons locaux : rien n'est perdu si le réseau coupe pendant la saisie. Réservé aux formulaires sans prix. */
(() => {
  const pending = sessionStorage.getItem('mds-draft-clear');
  if (pending && document.body.dataset.flash === 'success') { try { localStorage.removeItem(pending); } catch (_) {} }
  if (pending && !document.querySelector('.notice.errors')) sessionStorage.removeItem('mds-draft-clear');
  document.querySelectorAll('[data-local-draft]').forEach(form => {
    const key = 'mds-draft:' + form.dataset.localDraft;
    const fields = () => [...form.elements].filter(el => el.name && !['hidden', 'password', 'file', 'submit', 'checkbox', 'radio'].includes(el.type) && el.tagName !== 'BUTTON');
    const status = document.querySelector('[data-draft-status="' + form.dataset.localDraft + '"]');
    let saved = null;
    try { saved = JSON.parse(localStorage.getItem(key) || 'null'); } catch (_) {}
    if (saved) {
      let restored = 0;
      fields().forEach(el => { if (saved[el.name] && !el.value) { el.value = saved[el.name]; restored++; } });
      if (restored && status) status.textContent = 'Saisie retrouvée sur cet appareil.';
    }
    form.addEventListener('input', () => {
      const data = {};
      fields().forEach(el => { if (el.value) data[el.name] = el.value; });
      try { localStorage.setItem(key, JSON.stringify(data)); if (status) status.textContent = 'Enregistré sur cet appareil : rien n’est perdu si le réseau coupe.'; } catch (_) {}
    });
    form.addEventListener('submit', () => sessionStorage.setItem('mds-draft-clear', key));
  });
})();

/* Terminer une intervention exige un compte rendu : le serveur le vérifie aussi. */
document.querySelectorAll('[data-report-required]').forEach(button => {
  const area = document.getElementById(button.dataset.reportRequired);
  const help = document.querySelector('[data-report-help]');
  button.addEventListener('click', event => {
    if (area.value.trim()) return;
    event.preventDefault();
    if (help) help.textContent = 'Rédigez le compte rendu pour terminer l’intervention.';
    area.focus();
  });
});

/* Robustesse du mot de passe : indication seulement, les règles sont côté serveur. */
document.querySelectorAll('[data-strength]').forEach(input => {
  const meter = document.getElementById(input.dataset.strength), bar = meter?.querySelector('i'), label = document.querySelector('[data-strength-label]');
  input.addEventListener('input', () => {
    const v = input.value;
    const ok = v.length >= 12 && /[a-z]/.test(v) && /[A-Z]/.test(v) && /[0-9]/.test(v);
    const level = !v ? 0 : ok && v.length >= 16 ? 3 : ok ? 2 : 1;
    meter.dataset.level = level; bar.style.width = (level * 33.4) + '%';
    if (label) label.textContent = ['', 'Trop faible : 12 caractères, majuscules, minuscules et chiffres.', 'Correct.', 'Robuste.'][level];
  });
});

/* Articles d'un devis : ajouter depuis le catalogue ou en saisie libre. */
(() => {
  const list = document.getElementById('articles');
  if (!list) return;
  let next = Math.max(-1, ...[...list.querySelectorAll('[name]')].map(el => Number(el.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
  const add = (name = '', focus = true) => {
    if (list.querySelectorAll('.article-row').length >= 100) return;
    const fragment = document.getElementById('articles-template').content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace('__INDEX__', next); });
    fragment.querySelector('[data-article-name]').value = name;
    list.appendChild(fragment); next++;
    if (focus) list.lastElementChild?.querySelector('[data-article-name]')?.focus();
  };
  /* Brouillon local de l'agent : les articles ajoutés reviennent aussi. */
  const draftForm = list.closest('[data-local-draft]');
  if (draftForm && !document.querySelector('.notice.errors')) {
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('mds-draft:' + draftForm.dataset.localDraft) || '{}') || {}; } catch (_) {}
    Object.entries(saved).forEach(([name, value]) => {
      const index = name.match(/^articles\[(\d+)\]/);
      if (!index || list.querySelector(`[name="${name}"]`)) return;
      while (Number(index[1]) >= next && next < 100) add('', false);
      const field = list.querySelector(`[name="${name}"]`);
      if (field) field.value = value;
    });
  }
  document.getElementById('add-article')?.addEventListener('click', () => add());
  const search = document.getElementById('article-search');
  search?.addEventListener('change', () => { if (search.value.trim()) { add(search.value.trim()); search.value = ''; } });
  list.addEventListener('click', event => {
    if (event.target.closest('[data-remove-article]') && list.querySelectorAll('.article-row').length > 1) event.target.closest('.article-row').remove();
  });
})();

/* Un lien vers une section repliée (#planifier, #nouveau-contact) l'ouvre. */
(() => {
  const reveal = () => { if (!location.hash) return; const target = document.querySelector(location.hash); if (target?.tagName === 'DETAILS') { target.open = true; target.scrollIntoView(); } };
  window.addEventListener('hashchange', reveal); reveal();
})();

/* Filtres par pastilles sur une liste déjà chargée. */
document.querySelectorAll('[data-chip-filter]').forEach(group => {
  const list = document.getElementById(group.dataset.chipFilter);
  if (!list) return;
  group.addEventListener('click', event => {
    const chip = event.target.closest('[data-chip]');
    if (!chip) return;
    group.querySelectorAll('[data-chip]').forEach(item => item.setAttribute('aria-pressed', String(item === chip)));
    list.querySelectorAll('[data-chip-item]').forEach(item => { item.hidden = chip.dataset.chip !== 'all' && item.dataset.chipItem !== chip.dataset.chip; });
  });
});

/* Filtres secondaires : toujours visibles sur grand écran, repliés sur téléphone sauf s'ils sont actifs. */
document.querySelectorAll('details.more-filters').forEach(box => { if (matchMedia('(min-width:768px)').matches || box.hasAttribute('data-active')) box.open = true; });

/* Bulles : même position aléatoire et même transition que la section « En ce moment » du site. */
(() => {
  const field = document.querySelector('[data-bubbles]');
  if (!field) return;
  const reduce = () => document.documentElement.dataset.motion === 'reduced' || matchMedia('(prefers-reduced-motion: reduce)').matches;
  const SLOTS = [[0, 2, 112], [20, 50, 96], [40, 0, 124], [60, 52, 100], [80, 2, 96], [100, 48, 108]];
  const bubbles = [...field.querySelectorAll('[data-bubble]')];
  const shuffle = list => { const a = [...list]; for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };
  const place = () => {
    const slots = shuffle(SLOTS);
    bubbles.forEach((bubble, index) => {
      const [left, top, size] = slots[index];
      bubble.style.left = `calc((100% - 190px) * ${left / 100})`; bubble.style.top = top + '%';
      const circle = bubble.querySelector('.bubble-c');
      circle.style.width = circle.style.height = size + 'px';
    });
  };
  place();
  document.querySelector('[data-bubbles-shuffle]')?.addEventListener('click', place);
  if (!reduce()) setInterval(() => { if (!document.hidden && !field.matches(':hover') && !field.contains(document.activeElement)) place(); }, 5500);
})();

/* Confirmations : la bulle se retire seule, sauf si la souris ou le clavier s'y attarde. */
document.querySelectorAll('[data-flash-bubble]').forEach(flash => {
  let timer;
  const start = () => { timer = setTimeout(() => { flash.classList.add('is-leaving'); setTimeout(() => flash.remove(), 320); }, 5200); };
  flash.addEventListener('mouseenter', () => clearTimeout(timer));
  flash.addEventListener('mouseleave', start);
  start();
});

/* Notifications : bulle à l'arrivée d'une alerte, compteur et cloche à jour. */
(() => {
  const body = document.body, url = body.dataset.notificationsUrl, tray = document.querySelector('[data-bubble-tray]');
  if (!url || !tray) return;
  const PATHS = { devis: 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7', rdv: 'M4 5h16v16H4zM4 9h16M8 3v4M16 3v4', prestations: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z', messagerie: 'M3 5h18v14H3zM3 6l9 7 9-7', factures: 'M6 2h12v20l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h4', bell: 'M6 16V10a6 6 0 1 1 12 0v6l2 2H4zM10 21h4' };
  const SEEN_KEY = 'mds-bubbles-seen', OFFSETS = [0, 14, 4], SPACING = 86, MOBILE = matchMedia('(max-width:767px)'), IDLE_LIMIT = 15 * 60 * 1000, POLL = 45000;
  const icon = (name, size) => { const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('width', size); svg.setAttribute('height', size); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor'); svg.setAttribute('stroke-width', '1.6'); svg.setAttribute('stroke-linecap', 'round'); svg.setAttribute('stroke-linejoin', 'round'); const path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); path.setAttribute('d', PATHS[name] || PATHS.bell); svg.appendChild(path); return svg; };
  const el = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };
  const readSeen = () => { try { return JSON.parse(localStorage.getItem(SEEN_KEY) || '[]'); } catch (_) { return []; } };
  const saveSeen = ids => { try { localStorage.setItem(SEEN_KEY, JSON.stringify(ids.slice(-60))); } catch (_) {} };

  const arrange = () => [...tray.querySelectorAll('.tray-item:not(.is-leaving)')].reverse().forEach((item, index) => { item.style.transform = `translate(${-OFFSETS[index % OFFSETS.length]}px,${-index * SPACING}px)`; });
  const dismiss = item => { clearTimeout(item.timer); item.classList.add('is-leaving'); arrange(); setTimeout(() => item.remove(), 320); };
  const arm = item => { clearTimeout(item.timer); item.timer = setTimeout(() => dismiss(item), 8000); };
  const showBubble = data => {
    const item = el('div', 'tray-item');
    const circle = el('span', `bubble-c tone-${data.tone}`); circle.setAttribute('aria-hidden', 'true'); circle.appendChild(icon(data.icon, 24));
    const link = el('a', 'tray-link'); link.href = data.url || body.dataset.notificationsPage;
    const label = el('span', 'bubble-l'); label.append(el('span', 'bubble-n', data.title), el('span', 'bubble-s', data.message)); link.appendChild(label);
    const close = el('button', 'tray-close', '×'); close.type = 'button'; close.setAttribute('aria-label', 'Fermer cette notification'); close.addEventListener('click', () => dismiss(item));
    item.append(circle, link, close);
    item.addEventListener('mouseenter', () => clearTimeout(item.timer));
    item.addEventListener('mouseleave', () => arm(item));
    item.addEventListener('focusin', () => clearTimeout(item.timer));
    tray.appendChild(item); arrange(); arm(item);
    [...tray.querySelectorAll('.tray-item:not(.is-leaving)')].slice(0, MOBILE.matches ? -2 : -3).forEach(dismiss);
  };

  const setCount = count => {
    document.querySelectorAll('.notification-count').forEach(badge => { const grew = Number(badge.textContent) < count; badge.textContent = count; badge.hidden = !count; if (grew) { const bell = badge.closest('.bell'); bell?.classList.remove('is-ringing'); void bell?.offsetWidth; bell?.classList.add('is-ringing'); } });
    document.querySelectorAll('.bell').forEach(bell => bell.setAttribute('aria-label', `Notifications : ${count} non lue(s)`));
  };
  const request = async () => {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok || !(response.headers.get('content-type') || '').includes('json')) throw new Error('indisponible');
    return response.json();
  };
  const announce = data => {
    setCount(data.count);
    const seen = readSeen();
    const fresh = data.items.filter(item => item.unread && !seen.includes(item.id));
    if (!body.hasAttribute('data-notifications-here')) fresh.slice(0, 3).reverse().forEach((item, index) => setTimeout(() => showBubble(item), 500 + index * 420));
    saveSeen([...seen, ...data.items.map(item => item.id)].filter((id, index, all) => all.indexOf(id) === index));
  };

  /* Sans activité depuis 15 minutes, on cesse d'interroger le serveur : la session d'un poste laissé ouvert continue d'expirer. */
  let lastActivity = Date.now(), timer;
  ['pointerdown', 'keydown', 'scroll'].forEach(type => addEventListener(type, () => { lastActivity = Date.now(); schedule(); }, { passive: true }));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) schedule(); });
  const tick = () => { timer = null; if (document.hidden || Date.now() - lastActivity > IDLE_LIMIT) return; request().then(announce).then(schedule).catch(() => {}); };
  function schedule() { if (!timer && !document.hidden && Date.now() - lastActivity <= IDLE_LIMIT) timer = setTimeout(tick, POLL); }
  request().then(announce).then(schedule).catch(() => {});

  /* Cloche sur grand écran : aperçu sans quitter la page. Sans JavaScript ou sur téléphone, le lien ouvre la page. */
  const bell = document.querySelector('[data-bell]'), pop = document.querySelector('[data-bell-pop]'), list = document.querySelector('[data-bell-list]');
  if (!bell || !pop || !list) return;
  const closePop = () => { pop.hidden = true; bell.setAttribute('aria-expanded', 'false'); };
  const render = items => {
    list.replaceChildren();
    if (!items.length) { list.appendChild(el('li', 'empty', 'Aucune notification pour le moment.')); return; }
    items.slice(0, 5).forEach(item => {
      const row = el('li', item.unread ? 'is-unread' : ''), link = el('a'); link.href = item.url || body.dataset.notificationsPage;
      const dot = el('span', `notif-dot tone-${item.tone}`); dot.setAttribute('aria-hidden', 'true'); dot.appendChild(icon(item.icon, 18));
      const text = el('span', 'grow'); text.append(el('strong', '', item.title), el('span', '', item.message), el('span', 'mono', item.time));
      link.append(dot, text); row.appendChild(link); list.appendChild(row);
    });
  };
  bell.setAttribute('aria-expanded', 'false');
  bell.addEventListener('click', event => {
    if (matchMedia('(max-width:767px)').matches || body.hasAttribute('data-notifications-here') || event.ctrlKey || event.metaKey) return;
    event.preventDefault();
    if (!pop.hidden) { closePop(); return; }
    pop.hidden = false; bell.setAttribute('aria-expanded', 'true');
    request().then(data => { setCount(data.count); render(data.items); }).catch(() => { location.href = bell.href; });
  });
  document.addEventListener('click', event => { if (!pop.hidden && !event.target.closest('.bell-wrap')) closePop(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !pop.hidden) { closePop(); bell.focus(); } });
})();

/* Révéler un champ replié : section, carte d'ouverture ou onglet. */
const revealField = field => {
  for (let node = field.parentElement; node; node = node.parentElement) {
    if (node.tagName === 'DETAILS') node.open = true;
    if (node.getAttribute('role') === 'tabpanel' && node.hidden) document.querySelector(`[aria-controls="${node.id}"]`)?.click();
  }
};
document.addEventListener('invalid', event => revealField(event.target), true);

/* Erreurs du serveur : message sous le champ, aria-invalid, sections rouvertes, focus sur le premier champ fautif. */
(() => {
  const box = document.getElementById('form-errors');
  if (!box) return;
  const scope = document.querySelector('form[data-failed]') || document.getElementById('contenu');
  const controls = [...scope.querySelectorAll('[name]')].filter(el => el.type !== 'hidden');
  const flagged = new Set();
  Object.entries(JSON.parse(box.textContent)).forEach(([key, messages], index) => {
    const [head, ...rest] = key.split('.');
    const name = head + rest.map(part => `[${part}]`).join('');
    const fields = controls.filter(el => el.name === name || el.name === name.replace(/\[\d+\]$/, '[]'));
    if (!fields.length) {
      /* Erreur sans champ visible (choix d'une ouverture) : on ouvre le bloc concerné, le résumé donne le message. */
      const parent = name.replace(/\[[^\]]+\]$/, '[');
      const near = controls.find(el => parent.endsWith('[') && parent !== name && el.name.startsWith(parent));
      if (near) { revealField(near); flagged.add(near); }
      return;
    }
    const message = document.createElement('span');
    message.className = 'field-error'; message.id = 'field-error-' + index; message.textContent = messages[0];
    const field = fields[0], group = fields.length > 1 || ['radio', 'checkbox'].includes(field.type) ? field.closest('fieldset') : null;
    const label = field.closest('label');
    if (group) group.append(message);
    else if (label && !label.matches('.check,.switch,.toggle-row,.drop-input')) label.append(message);
    else (label?.closest('.drop') || label || field).after(message);
    fields.forEach(el => {
      el.setAttribute('aria-invalid', 'true'); el.setAttribute('aria-describedby', message.id); flagged.add(el);
      el.addEventListener('input', () => { fields.forEach(item => { item.removeAttribute('aria-invalid'); item.removeAttribute('aria-describedby'); }); message.remove(); }, { once: true });
    });
    revealField(field);
  });
  const first = controls.find(el => flagged.has(el));
  if (first) { first.focus({ preventScroll: true }); first.scrollIntoView({ block: 'center' }); }
})();

/* Panneau de création : focus au premier champ à l'ouverture par ancre, fermeture sans recharger. */
(() => {
  const opened = () => {
    const panel = document.getElementById(location.hash.slice(1));
    if (panel?.matches('.create-panel') && !document.getElementById('form-errors')) panel.querySelector('input:not([type=hidden]),select,textarea')?.focus({ preventScroll: true });
    return panel;
  };
  window.addEventListener('hashchange', () => { opened()?.scrollIntoView({ block: 'start' }); });
  /* À partir de 1200 px, le panneau suit la page : inutile de masquer le titre et la liste à l'arrivée. */
  if (opened()?.matches('.create-panel') && matchMedia('(min-width:1200px)').matches) window.scrollTo(0, 0);
  document.addEventListener('click', event => {
    const close = event.target.closest('[data-close-panel]');
    if (!close) return;
    event.preventDefault();
    close.closest('.create-panel').removeAttribute('data-open');
    location.hash = '';
    document.querySelector(`.page-actions [href$="#${close.closest('.create-panel').id}"]`)?.focus();
  });
})();

/* Sommaire : étape en cours et étapes complètes, d'après les champs obligatoires. */
document.querySelectorAll('[data-step-nav]').forEach(nav => {
  const form = document.getElementById(nav.dataset.stepNav);
  const steps = [...nav.querySelectorAll('a[href^="#"]')].map(link => ({ link, item: link.closest('li'), note: link.querySelector('small'), section: document.getElementById(link.hash.slice(1)) })).filter(step => step.section);
  const stateOf = section => {
    const fields = [...section.querySelectorAll('input,select,textarea')].filter(el => el.name && el.type !== 'hidden');
    if (section.dataset.stepNeeds) return form.elements[section.dataset.stepNeeds]?.value ? 'done' : 'todo';
    const required = fields.filter(el => el.required);
    const changed = el => el.tagName === 'SELECT' ? el.selectedIndex !== Math.max(0, [...el.options].findIndex(option => option.defaultSelected)) : ['checkbox', 'radio'].includes(el.type) ? el.checked !== el.defaultChecked : el.value !== el.defaultValue;
    if (!required.length) return fields.some(changed) ? 'done' : 'optional';
    return required.every(el => el.validity.valid) ? 'done' : 'todo';
  };
  const update = () => steps.forEach(({ item, note, section }) => {
    const state = stateOf(section);
    item.classList.toggle('is-done', state === 'done');
    if (note) { note.dataset.stepState = state; note.textContent = section.dataset['step' + state[0].toUpperCase() + state.slice(1)] || { done: 'Complet', todo: 'À renseigner', optional: 'Facultatif' }[state]; }
  });
  ['input', 'change', 'mds:calculated'].forEach(type => form.addEventListener(type, update));
  form.addEventListener('click', () => setTimeout(update));
  document.addEventListener('DOMContentLoaded', update);
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      steps.forEach(({ link, section }) => { if (section === entry.target) link.setAttribute('aria-current', 'step'); else link.removeAttribute('aria-current'); });
    }), { rootMargin: '-25% 0px -65% 0px' });
    steps.forEach(({ section }) => observer.observe(section));
  }
});

/* Champ numérique avec boutons − et + (fenêtres identiques, quantité d'un article). */
document.addEventListener('click', event => {
  const button = event.target.closest('[data-step-by]');
  if (!button) return;
  const input = button.closest('.stepper').querySelector('input');
  const min = Number(input.min || 0), max = Number(input.max || 1000000);
  input.value = Math.min(max, Math.max(min, (Number(input.value) || 0) + Number(button.dataset.stepBy)));
  input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true }));
});

/* Carte d'ouverture : ses mesures restent lisibles une fois repliée. */
(() => {
  const cards = '[data-window],[data-agent-window]';
  const sync = card => {
    const value = key => card.querySelector(`[name$="][${key}]"]`)?.value.trim() || '';
    const name = card.querySelector('[data-opening-name]'), sum = card.querySelector('[data-opening-sum]');
    if (!name || !sum) return;
    const quantity = Number(value('quantity')) || 1;
    name.textContent = value('label') || 'Ouverture';
    sum.textContent = (value('width') || value('height') ? `${value('width') || '?'} × ${value('height') || '?'} cm` : 'Mesures à saisir') + (quantity > 1 ? ` ×${quantity}` : '');
  };
  document.querySelectorAll(cards).forEach(sync);
  document.addEventListener('input', event => { const card = event.target.closest(cards); if (card) sync(card); });
  document.addEventListener('click', event => { if (event.target.closest('#add-quote-window,#agent-add-opening')) setTimeout(() => document.querySelectorAll(cards).forEach(sync)); });
})();

/* Image : aperçu immédiat, poids vérifié avant l'envoi, texte alternatif rappelé. */
document.querySelectorAll('[data-image-block]').forEach(block => {
  const input = block.querySelector('[data-image-input]'), drop = block.querySelector('.drop'), preview = block.querySelector('[data-image-preview]');
  const name = block.querySelector('[data-image-name]'), remove = block.querySelector('[name=remove_image]'), alt = block.querySelector('[name=image_alt]'), warning = block.querySelector('[data-alt-warning]');
  const saved = preview.getAttribute('src');
  let url;
  const refresh = () => {
    if (url) URL.revokeObjectURL(url);
    const file = input.files[0];
    url = file ? URL.createObjectURL(file) : null;
    const source = url || saved;
    preview.hidden = !source;
    if (source) preview.src = source; else preview.removeAttribute('src');
    drop.classList.toggle('has-image', Boolean(source));
    drop.classList.toggle('is-removed', Boolean(!file && remove?.checked));
    name.textContent = !file ? '' : file.size > 2097152 ? `${file.name} dépasse 2 Mo : choisissez une image plus légère.` : file.name;
    warning.hidden = !(source && !(remove?.checked && !file)) || Boolean(alt.value.trim());
  };
  input.addEventListener('change', refresh); remove?.addEventListener('change', refresh); alt.addEventListener('input', refresh);
  refresh();
});

/* Client : l'origine du consentement devient obligatoire dès que le consentement est coché. */
document.querySelectorAll('[data-consent]').forEach(box => {
  const check = box.querySelector('[name=marketing]'), source = box.querySelector('[name=consent_source]');
  source.required = check.checked;
  check.addEventListener('change', () => { source.required = check.checked; if (check.checked) source.focus(); });
});

/* Nouvelle prestation : devis et rendez-vous du client choisi, adresse préremplie sans écraser une saisie. */
(() => {
  const form = document.getElementById('planifier-form');
  if (!form) return;
  const contact = form.elements.contact_id, address = form.elements.address;
  const links = [form.elements.quote_id, form.elements.appointment_id].map(select => ({ select, options: [...select.options] }));
  let filled = '';
  const fromLink = links.map(({ select }) => select.selectedOptions[0]?.dataset.contact).find(Boolean);
  if (!contact.value && fromLink) contact.value = fromLink;
  const sync = () => {
    links.forEach(({ select, options }) => {
      const value = select.value, match = options.filter(option => !option.value || option.dataset.contact === contact.value);
      select.replaceChildren(...match); select.value = match.some(option => option.value === value) ? value : '';
    });
    const known = contact.selectedOptions[0]?.dataset.address || '';
    if (!address.value.trim() || address.value === filled) address.value = filled = known;
  };
  contact.addEventListener('change', sync); sync();
  /* Un client créé dans l'autre onglet rejoint la liste au retour, sans perdre la saisie. */
  let stale = false;
  form.querySelector('[data-new-client]')?.addEventListener('click', () => { stale = true; });
  window.addEventListener('focus', async () => {
    if (!stale) return;
    stale = false;
    const html = await fetch(location.pathname, { credentials: 'same-origin' }).then(response => response.text()).catch(() => '');
    const next = new DOMParser().parseFromString(html, 'text/html').querySelector('#planifier-form [name=contact_id]');
    if (!next) return;
    const value = contact.value;
    contact.replaceChildren(...[...next.options].map(option => document.adoptNode(option)));
    contact.value = value;
  });
})();

/* Matériau : la nature décide des champs utiles, la catégorie filtre groupes, finitions et produits. */
document.querySelectorAll('[data-option-form]').forEach(form => {
  const kind = form.elements.kind, category = form.elements.category, group = form.elements.fabric_group;
  const groups = [...group.options];
  const sync = () => {
    const k = kind.value, c = category.value;
    form.querySelectorAll('[data-for-kinds]').forEach(el => { el.hidden = !el.dataset.forKinds.split(' ').includes(k); });
    const valid = groups.filter(option => k === 'material' ? (c === 'store' ? option.value === 'store' : ['rideau', 'voilage'].includes(option.value)) : !option.value);
    const current = group.value;
    group.replaceChildren(...valid); group.value = valid.some(option => option.value === current) ? current : valid[0].value;
    form.querySelectorAll('[data-category]').forEach(item => {
      const on = item.dataset.category === c;
      item.hidden = !on;
      if (!on) item.querySelector('input').checked = false;
    });
    form.querySelectorAll('[data-empty-note]').forEach(note => { note.hidden = [...note.parentElement.querySelectorAll('[data-category]')].some(item => !item.hidden); });
  };
  kind.addEventListener('change', sync); category.addEventListener('change', sync); sync();
});

/* Produit : le type guide l'univers. Une fiche ancienne incohérente n'est pas corrigée sans action de l'utilisateur. */
(() => {
  const form = document.getElementById('product-form');
  if (!form) return;
  const universes = { store: ['stores_rideaux'], rideau: ['stores_rideaux'], electromenager: ['electromenager'], meuble: ['meubles_bureau', 'meubles_domicile'] };
  const select = form.elements.universe, options = [...select.options];
  const sync = keep => {
    const allowed = universes[form.elements.type.value] || [], current = select.value;
    const match = options.filter(option => allowed.includes(option.value) || (keep && option.value === current));
    select.replaceChildren(...match); select.value = match.some(option => option.value === current) ? current : match[0].value;
  };
  [...form.elements.type].forEach(radio => radio.addEventListener('change', () => sync(false)));
  sync(true);
  form.addEventListener('input', event => {
    const state = event.target.closest('.cost-card')?.querySelector('.cost-state');
    if (!state || !event.target.matches('[data-cost]')) return;
    state.textContent = event.target.value === '' ? 'Non renseigné' : Number(event.target.value) === 0 ? 'Coût nul' : '';
  });
})();

/* Fiche technique : lignes guidées qui écrivent le même champ specs_text. Sans JavaScript, la zone de texte reste. */
document.querySelectorAll('[data-specs]').forEach(box => {
  const area = box.querySelector('textarea'), rows = box.querySelector('[data-spec-rows]'), template = box.querySelector('template');
  const write = () => { area.value = [...rows.children].map(row => { const [label, value] = [...row.querySelectorAll('input')].map(input => input.value.trim()); return label || value ? `${label} : ${value}` : ''; }).filter(Boolean).join('\n'); };
  const add = (label = '', value = '') => { const fragment = template.content.cloneNode(true), inputs = fragment.querySelectorAll('input'); inputs[0].value = label; inputs[1].value = value; rows.append(fragment); };
  area.value.split(/\r?\n/).filter(line => line.trim()).forEach(line => { const at = line.indexOf(':'); add(at < 0 ? line.trim() : line.slice(0, at).trim(), at < 0 ? '' : line.slice(at + 1).trim()); });
  if (!rows.children.length) add();
  box.classList.add('is-enhanced');
  rows.addEventListener('input', write);
  box.querySelector('[data-spec-add]').addEventListener('click', () => { add(); rows.lastElementChild.querySelector('input').focus(); });
  rows.addEventListener('click', event => { const remove = event.target.closest('[data-spec-remove]'); if (!remove) return; remove.closest('.spec-row').remove(); if (!rows.children.length) add(); write(); });
});