/* Éditeur libre de mise en page des PDF : blocs placés en millimètres sur une page A4, enregistrés dans le champ caché « blocks ».
   Sans JavaScript, l'écran garde les réglages du document et la mise en page déjà enregistrée. */
(() => {
  'use strict';
  const form = document.getElementById('layout-form'), input = document.getElementById('layout-blocks');
  if (!form || !input) return;
  const data = JSON.parse(document.getElementById('layout-editor-data').textContent);
  const starter = JSON.parse(document.getElementById('layout-starter').textContent);
  const $ = (selector, root = form) => root.querySelector(selector), $$ = (selector, root = form) => [...root.querySelectorAll(selector)];
  const PAGE = { w: 210, h: 297 }, GRID = 5, MAGNET = 1.5, PX = 96 / 25.4, ZOOMS = [0.4, 0.5, 0.6, 0.75, 0.9, 1, 1.25, 1.5];
  const sheet = $('[data-sheet]'), layer = $('[data-blocks]'), layers = $('[data-layers]'), props = $('[data-props]');
  const stage = document.getElementById('editor-stage'), pdf = document.getElementById('apercu-a4'), m = data.margins;
  const kindWord = { logo: 'image', signature: 'cadre', stamp: 'image', lines: 'tableau', shape: 'forme' };
  const parse = value => { try { const parsed = value ? JSON.parse(value) : null; return parsed && Array.isArray(parsed.items) ? parsed : null; } catch { return null; } };
  let state = parse(input.value), view = 'single', selected = null, zoom = 1, history = [], future = [], dirty = false, editing = false;

  $$('[data-editor-only]', document).forEach(el => { el.hidden = false; });
  pdf.hidden = true;

  const round = value => Math.round(value * 10) / 10;
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const scale = () => PX * zoom;
  const find = id => state?.items.find(block => block.id === id);
  const onView = () => (state?.items || []).filter(block => block.pages.includes(view));
  const isOut = b => b.x < m.left - 0.05 || b.y < m.top - 0.05 || b.x + b.w > PAGE.w - m.right + 0.05 || b.y + b.h > PAGE.h - m.bottom + 0.05;
  const describe = b => `${data.types[b.type]}, x ${String(b.x).replace('.', ',')} mm, y ${String(b.y).replace('.', ',')} mm, ${String(b.w).replace('.', ',')} × ${String(b.h).replace('.', ',')} mm${b.locked ? ', verrouillé' : ''}${isOut(b) ? ', hors de la zone imprimable' : ''}`;
  const el = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };

  // Aimantation : grille de 5 mm et bords de la zone imprimable. Alt la désactive.
  const snapEdge = (start, size, axis, free) => {
    if (free) return round(start);
    const low = axis === 'x' ? m.left : m.top, high = (axis === 'x' ? PAGE.w - m.right : PAGE.h - m.bottom);
    if (Math.abs(start - low) < MAGNET) return low;
    if (Math.abs(start + size - high) < MAGNET) return round(high - size);
    return Math.round(start / GRID) * GRID;
  };
  const snapSize = (start, size, axis, free) => {
    if (free) return round(size);
    const high = axis === 'x' ? PAGE.w - m.right : PAGE.h - m.bottom;
    if (Math.abs(start + size - high) < MAGNET) return round(high - start);
    return Math.max(GRID, Math.round(size / GRID) * GRID);
  };
  const fit = b => {
    const min = b.type === 'shape' ? 0.2 : 2;
    b.w = round(clamp(b.w, min, PAGE.w)); b.h = round(clamp(b.h, min, PAGE.h));
    b.x = round(clamp(b.x, 0, PAGE.w - b.w)); b.y = round(clamp(b.y, 0, PAGE.h - b.h));
  };

  /* Historique : chaque modification enregistre l'état précédent. */
  const snapshot = () => JSON.stringify(state);
  const push = before => { history.push(before); if (history.length > 100) history.shift(); future = []; };
  function save() {
    input.value = state ? JSON.stringify(state) : '';
    dirty = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    $('[data-undo]', document).disabled = !history.length; $('[data-redo]', document).disabled = !future.length;
  }
  function commit(change) { push(snapshot()); change(); save(); render(); }
  function travel(from, to) {
    if (!from.length) return;
    to.push(snapshot()); state = parse(from.pop());
    if (selected && !find(selected)) selected = null;
    save(); render();
  }

  /* Contenu indicatif de chaque bloc sur la page ; le PDF réel se voit dans « Aperçu PDF ». */
  function sample(b) {
    const body = el('div', 'eb-body');
    const add = (text, className) => body.append(el('p', className, text));
    switch (b.type) {
      case 'logo': {
        const asset = document.querySelector(`img[data-asset="${b.source}"]`);
        if (asset) { const img = el('img'); img.src = asset.src; img.alt = ''; body.append(img); } else add(data.sources[b.source] || 'Image', 'eb-placeholder');
        break;
      }
      case 'text': add(b.text || 'Texte libre', b.text ? 'eb-prose' : 'eb-placeholder'); break;
      case 'commitment': { const p = el('p', b.text ? 'eb-prose' : 'eb-placeholder'); p.append(el('strong', '', label(b, 'Notre engagement.') + ' ')); p.append(b.text || 'Texte du modèle, modifiable dans Réglages du document › Entreprise'); body.append(p); break; }
      case 'company': if (gridOn(b)) { body.append(gridSample(b, { name: data.issuer || 'Entreprise', activities: 'Activités', address: 'Adresse', phone: 'Téléphone', email: 'e-mail' })); break; }
        add(data.issuer || 'Entreprise', 'eb-strong eb-big'); add('Activités · adresse · téléphone · e-mail', 'eb-muted'); break;
      case 'title': add(label(b, { invoice: 'FACTURE', receipt: 'REÇU' }[data.kind] || 'DEVIS'), 'eb-strong eb-huge'); add('N° du document · date'); break;
      case 'client': add(label(b, 'Client').toUpperCase(), 'eb-eyebrow'); if (gridOn(b)) { body.append(gridSample(b, { name: 'Nom du client', phone: '07 00 00 00 00', address: 'Adresse, commune', email: 'client@exemple.ci' })); break; }
        add('Nom du client', 'eb-strong'); add('Téléphone · adresse'); break;
      case 'object': add(label(b, 'Objet').toUpperCase(), 'eb-eyebrow'); add('Objet du document'); break;
      case 'lines': {
        const table = el('div', 'eb-table');
        const head = el('div', 'eb-row eb-head'), cl = b.column_labels || {}; head.append(el('span', '', cl.designation || label(b, 'Désignation')));
        b.columns.forEach(column => head.append(el('span', 'n', cl[column] || { quantity: 'Qté', unit_price: 'PU (F)', amount: 'Montant (F)' }[column])));
        if (b.header_bg) head.style.background = b.header_bg; if (b.header_color) head.style.color = b.header_color;
        if (!b.hide_header) table.append(head);
        for (let i = 0; i < 3; i++) { const row = el('div', 'eb-row'); row.append(el('span', 'eb-bar')); b.columns.forEach(() => row.append(el('span', 'eb-bar n'))); table.append(row); }
        body.append(table); body.append(el('p', 'eb-muted', rowKeys(b).filter(k => rowSet()[k]).map(k => rowSet()[k]).join(' · ') || 'Aucune ligne affichée'));
        break;
      }
      case 'total': { const row = el('div', 'eb-total'); row.append(el('span', '', label(b, data.kind === 'receipt' ? 'MONTANT REÇU' : 'TOTAL GÉNÉRAL'))); row.append(el('strong', '', '000 000 F CFA')); body.append(row); break; }
      case 'payment': add(label(b, 'Conditions de paiement'), 'eb-strong'); add('Échéances · acompte reçu · reste à payer'); break;
      case 'signature': add(label(b, 'Bon pour accord, signature du client'), 'eb-muted'); break;
      case 'stamp': add(label(b, 'Cachet et signature de l’entreprise'), 'eb-muted'); break;
      case 'footer': add('Mentions légales et pied de page', 'eb-muted'); break;
    }
    // Texte complémentaire des blocs automatiques, imprimé sous leur contenu.
    if ((data.complement || []).includes(b.type) && (b.text || '').trim()) add(b.text, 'eb-prose');
    // Ligne sous l'intitulé : premier élément du bloc ; « --- » dans un texte : séparation.
    if ((b.separators || []).includes('label') && (data.labels || {})[b.type]) body.firstElementChild?.classList.add('eb-under');
    body.querySelectorAll('.eb-prose').forEach(p => {
      if (!/^\s*-{3,}\s*$/m.test(p.textContent)) return;
      const parts = p.textContent.split(/\r?\n/), frag = document.createDocumentFragment(); let buffer = [];
      const flush = () => { if (buffer.length) { frag.append(el('p', 'eb-prose', buffer.join('\n'))); buffer = []; } };
      parts.forEach(line => { if (/^\s*-{3,}\s*$/.test(line)) { flush(); const hr = el('span', 'eb-sep'); hr.style.borderTopColor = b.border_color; frag.append(hr); } else buffer.push(line); });
      flush(); p.replaceWith(frag);
    });
    return body;
  }
  function label(b, fallback) { return (b.label || '').trim() || fallback; }
  const gridOn = b => Array.isArray(b.fields) || b.field_labels || b.grid_h || b.grid_v;
  const fieldKeys = b => Array.isArray(b.fields) ? b.fields : Object.keys((data.blockFields || {})[b.type] || {});
  function gridSample(b, values) {
    const labels = (data.blockFields || {})[b.type] || {}, keys = fieldKeys(b), grid = el('div', 'eb-grid');
    keys.forEach((key, i) => {
      const row = el('div', 'eb-grid-row' + (b.grid_h && i < keys.length - 1 ? ' h' : ''));
      if (b.field_labels) row.append(el('span', 'eb-grid-k' + (b.grid_v ? ' v' : ''), labels[key]));
      row.append(el('span', key === 'name' ? 'eb-strong' : '', values[key] || ''));
      row.style.borderColor = b.border_color; row.firstChild.style.borderColor = b.border_color; grid.append(row);
    });
    return grid;
  }
  // Lignes du tableau : celles du document édité (reçu ou devis / facture), dans l'ordre enregistré.
  const rowSet = () => data.kind === 'receipt' ? (data.receiptRows || {}) : (data.rows || {});
  const allRows = () => [...Object.keys(data.rows || {}), ...Object.keys(data.receiptRows || {})];
  const rowKeys = b => Array.isArray(b.row_order) ? b.row_order : allRows();
  const FONT_STACKS = { 'DejaVu Serif': '"DejaVu Serif",Georgia,serif', 'Arial': 'Arial,Helvetica,sans-serif', 'Times New Roman': '"Times New Roman",Times,serif' };
  function paint(node, b) {
    const s = scale();
    Object.assign(node.style, { left: b.x * s + 'px', top: b.y * s + 'px', width: b.w * s + 'px', height: (b.type === 'shape' && b.shape === 'line' ? 0 : b.h) * s + 'px',
      fontSize: b.size * 0.3528 * s + 'px', color: b.color, textAlign: b.align, background: b.background || '',
      fontFamily: FONT_STACKS[b.font] || '"DejaVu Sans",Verdana,sans-serif', fontWeight: b.bold ? '700' : '', fontStyle: b.italic ? 'italic' : '',
      lineHeight: b.line_height ? String(b.line_height) : '', letterSpacing: b.letter_spacing ? b.letter_spacing * 0.3528 * s + 'px' : '', textTransform: b.uppercase ? 'uppercase' : '',
      borderRadius: b.radius ? b.radius * s + 'px' : '', borderStyle: b.border_style || '',
      border: b.type === 'shape' ? '' : (b.border_width > 0 ? `${Math.max(1, b.border_width * s)}px solid ${b.border_color}` : ''),
      borderTop: b.type === 'shape' ? `${Math.max(1, Math.max(0.2, b.border_width) * s)}px solid ${b.border_color}` : undefined });
    if (b.type === 'shape' && b.shape === 'frame') node.style.border = `${Math.max(1, Math.max(0.2, b.border_width) * s)}px solid ${b.border_color}`;
    if (b.type !== 'shape') ['top', 'bottom'].forEach(side => { if ((b.separators || []).includes(side)) node.style['border' + side[0].toUpperCase() + side.slice(1)] = `${Math.max(1, 0.3 * s)}px ${b.border_style || 'solid'} ${b.border_color}`; });
    if (b.type === 'shape' && b.border_style) { node.style.borderTopStyle = b.border_style; if (b.shape === 'frame') node.style.borderStyle = b.border_style; }
    const body = node.querySelector('.eb-body');
    if (body) Object.assign(body.style, { padding: b.padding !== undefined ? b.padding * s + 'px' : '', display: b.valign ? 'flex' : '', flexDirection: b.valign ? 'column' : '',
      justifyContent: { middle: 'center', bottom: 'flex-end' }[b.valign] || '' });
    node.querySelectorAll('.eb-eyebrow,.eb-under').forEach(x => { x.style.color = b.label_color || ''; });
    node.classList.toggle('is-out', isOut(b)); node.classList.toggle('is-locked', b.locked);
    node.setAttribute('aria-label', describe(b));
  }

  function render() {
    const s = scale(), empty = $('[data-empty]'), wrap = $('[data-sheet-wrap]');
    empty.hidden = !!state; wrap.hidden = !state; $('[data-reset]').hidden = !state;
    sheet.style.width = PAGE.w * s + 'px'; sheet.style.height = PAGE.h * s + 'px';
    sheet.style.setProperty('--grid', GRID * s + 'px');
    Object.assign($('[data-margins]').style, { left: m.left * s + 'px', top: m.top * s + 'px', right: m.right * s + 'px', bottom: m.bottom * s + 'px' });
    rulers();
    layer.replaceChildren(); layers.replaceChildren();
    const items = onView();
    $('[data-layers-empty]').hidden = !state || items.length > 0;
    if (!state) { props.hidden = true; return; }
    items.forEach((b, index) => {
      const node = el('div', 'eb' + (b.type === 'shape' ? ' eb-shape' : '') + (b.id === selected ? ' is-selected' : ''));
      node.dataset.id = b.id; node.tabIndex = 0; node.setAttribute('role', 'button'); node.setAttribute('aria-pressed', String(b.id === selected)); node.setAttribute('aria-describedby', 'block-help');
      node.style.zIndex = String(index + 1);
      if (b.type !== 'shape') node.append(sample(b));
      if (b.id === selected) {
        node.append(el('span', 'eb-tag', `${data.types[b.type]} · ${String(b.w).replace('.', ',')} × ${String(b.h).replace('.', ',')} mm`));
        if (!b.locked) { const handle = el('span', 'eb-resize'); handle.dataset.resize = ''; node.append(handle); }
      }
      paint(node, b); layer.append(node);
      const item = el('li'), button = el('button', 'layer');
      button.type = 'button'; button.dataset.select = b.id; button.setAttribute('aria-pressed', String(b.id === selected));
      button.append(el('span', '', data.types[b.type]), el('small', 'mono', (kindWord[b.type] || 'texte') + (b.pages.length > 1 ? ' · toutes pages' : '')));
      item.append(button); layers.append(item);
    });
    fill();
  }
  function rulers() {
    const s = scale(), x = $('[data-ruler-x]'), y = $('[data-ruler-y]');
    x.style.width = PAGE.w * s + 'px'; y.style.height = PAGE.h * s + 'px';
    if (x.dataset.zoom === String(zoom)) return;
    x.dataset.zoom = String(zoom); x.replaceChildren(); y.replaceChildren();
    for (let mm = 0; mm <= PAGE.w; mm += 10) { const tick = el('span', mm % 20 ? 'minor' : '', mm % 20 ? '' : String(mm)); tick.style.left = mm * s + 'px'; x.append(tick); }
    for (let mm = 0; mm <= PAGE.h; mm += 10) { const tick = el('span', mm % 20 ? 'minor' : '', mm % 20 ? '' : String(mm)); tick.style.top = mm * s + 'px'; y.append(tick); }
  }

  /* Panneau des propriétés du bloc choisi. */
  function fill() {
    const b = find(selected);
    props.hidden = !b;
    if (!b) return;
    $('[data-prop-title]').textContent = data.types[b.type];
    $$('[data-prop]', props).forEach(field => {
      const key = field.dataset.prop;
      if (key === 'has_background') field.checked = !!b.background;
      else if (key === 'background') field.value = b.background || '#F5ECD4';
      else if (field.type === 'checkbox') field.checked = !!b[key];
      else if (document.activeElement !== field) field.value = b[key] ?? '';
    });
    $$('[data-for-types]', props).forEach(box => { box.hidden = !box.dataset.forTypes.split(' ').includes(b.type); });
    // Intitulé proposé en indication ; contenu principal (texte libre, engagement) ou texte complémentaire (blocs automatiques).
    const labelInput = $('[data-label-input]', props);
    if (labelInput) labelInput.placeholder = b.type === 'title' ? ({ invoice: 'FACTURE', receipt: 'REÇU' }[data.kind] || 'DEVIS') : (b.type === 'total' && data.kind === 'receipt' ? 'MONTANT REÇU' : (data.labels || {})[b.type] || '');
    const main = ['text', 'commitment'].includes(b.type);
    $('[data-text-label]', props).textContent = main ? 'Contenu' : 'Texte complémentaire';
    $('[data-text-hint]', props).textContent = main ? '' : 'Imprimé sous le contenu automatique du bloc. Laissez vide pour ne rien ajouter.';
    $$('[data-align]', props).forEach(button => button.setAttribute('aria-pressed', String(b.align === button.dataset.align)));
    $$('[data-column]', props).forEach(box => { box.checked = (b.columns || []).includes(box.dataset.column); });
    $$('[data-toggle]', props).forEach(button => button.setAttribute('aria-pressed', String(!!b[button.dataset.toggle])));
    $$('[data-valign]', props).forEach(button => button.setAttribute('aria-pressed', String((b.valign || 'top') === button.dataset.valign)));
    ['label_color', 'header_bg', 'header_color'].forEach(key => { const f = $(`[data-prop="${key}"]`, props); if (f) f.value = b[key] || (key === 'header_color' ? '#111111' : key === 'header_bg' ? '#F5ECD4' : '#666666'); });
    const desig = $('[data-col-label="designation"]', props); if (desig && document.activeElement !== desig) desig.value = (b.column_labels || {}).designation || '';
    const colOrder = $('[data-col-order]', props);
    if (colOrder && b.type === 'lines') {
      const shown = (b.columns || []).filter(k => data.columns[k]), hidden = Object.keys(data.columns).filter(k => !shown.includes(k)), cl = b.column_labels || {};
      const defaultsLabel = { quantity: 'Qté', unit_price: 'PU (F)', amount: 'Montant (F)' };
      colOrder.replaceChildren(...[...shown, ...hidden].map((key, i) => {
        const li = el('li', 'row-item'), on = shown.includes(key), box = el('input'), lab = el('label', 'check'), name = el('input', 'col-name');
        box.type = 'checkbox'; box.checked = on; box.dataset.colKey = key; lab.append(box, data.columns[key]);
        name.maxLength = 40; name.placeholder = defaultsLabel[key]; name.value = cl[key] || ''; name.dataset.colLabel = key; name.setAttribute('aria-label', `Intitulé de la colonne ${data.columns[key]}`);
        const up = el('button', 'secondary icon-btn', '↑'), down = el('button', 'secondary icon-btn', '↓');
        [[up, -1, 'Monter'], [down, 1, 'Descendre']].forEach(([btn, dir, word]) => { btn.type = 'button'; btn.dataset.colMove = key; btn.dataset.dir = String(dir); btn.setAttribute('aria-label', `${word} : ${data.columns[key]}`);
          btn.disabled = !on || (dir < 0 ? i === 0 : i === shown.length - 1); });
        li.append(lab, name, up, down); return li;
      }));
    }
    $$('[data-separator]', props).forEach(box => { box.checked = (b.separators || []).includes(box.dataset.separator); });
    const fieldOrder = $('[data-field-order]', props);
    if (fieldOrder && (data.blockFields || {})[b.type]) {
      const set = data.blockFields[b.type], shown = fieldKeys(b), hidden = Object.keys(set).filter(k => !shown.includes(k));
      fieldOrder.replaceChildren(...[...shown, ...hidden].map((key, i) => {
        const li = el('li', 'row-item'), on = shown.includes(key), box = el('input'), lab = el('label', 'check');
        box.type = 'checkbox'; box.checked = on; box.dataset.fieldKey = key; lab.append(box, set[key]);
        const up = el('button', 'secondary icon-btn', '↑'), down = el('button', 'secondary icon-btn', '↓');
        [[up, -1, 'Monter'], [down, 1, 'Descendre']].forEach(([btn, dir, word]) => { btn.type = 'button'; btn.dataset.fieldMove = key; btn.dataset.dir = String(dir); btn.setAttribute('aria-label', `${word} : ${set[key]}`);
          btn.disabled = !on || (dir < 0 ? i === 0 : i === shown.length - 1); });
        li.append(lab, up, down); return li;
      }));
    }
    const order = $('[data-row-order]', props);
    if (order && b.type === 'lines') {
      const set = rowSet(), shown = rowKeys(b).filter(k => set[k]), hidden = Object.keys(set).filter(k => !shown.includes(k));
      order.replaceChildren(...[...shown, ...hidden].map((key, i) => {
        const li = el('li', 'row-item'), on = shown.includes(key), box = el('input'), lab = el('label', 'check');
        box.type = 'checkbox'; box.checked = on; box.dataset.rowKey = key; lab.append(box, set[key]);
        const up = el('button', 'secondary icon-btn', '↑'), down = el('button', 'secondary icon-btn', '↓');
        [[up, -1, 'Monter'], [down, 1, 'Descendre']].forEach(([btn, dir, word]) => { btn.type = 'button'; btn.dataset.rowMove = key; btn.dataset.dir = String(dir); btn.setAttribute('aria-label', `${word} : ${set[key]}`);
          btn.disabled = !on || (dir < 0 ? i === 0 : i === shown.length - 1); });
        li.append(lab, up, down); return li;
      }));
    }
    $$('[data-page-kind]', props).forEach(box => { box.checked = b.pages.includes(box.dataset.pageKind); });
  }
  function select(id, focus) {
    selected = id; editing = false; render();
    if (focus) layer.querySelector(`[data-id="${CSS.escape(id)}"]`)?.focus();
  }
  function update(field) {
    const b = find(selected); if (!b) return;
    if (!editing) { push(snapshot()); editing = true; }
    const key = field.dataset.prop;
    const OPTIONAL = { line_height: [0.9, 2.5], letter_spacing: [0, 3], padding: [0, 8], radius: [0, 6] };
    if (OPTIONAL[key]) { const value = parseFloat(String(field.value).replace(',', '.')); if (field.value === '' || Number.isNaN(value)) delete b[key]; else b[key] = Math.round(clamp(value, ...OPTIONAL[key]) * 100) / 100; }
    else if (key === 'border_style') { if (field.value) b.border_style = field.value; else delete b.border_style; }
    else if (['x', 'y', 'w', 'h', 'border_width'].includes(key)) { const value = parseFloat(String(field.value).replace(',', '.')); if (Number.isNaN(value)) return; b[key] = key === 'border_width' ? round(clamp(value, 0, 3)) : value; fit(b); }
    else if (key === 'size') { const value = parseInt(field.value, 10); if (Number.isNaN(value)) return; b.size = clamp(value, 6, 28); }
    else if (key === 'has_background') b.background = field.checked ? $('[data-prop="background"]', props).value.toUpperCase() : null;
    else if (key === 'background') { b.background = field.value.toUpperCase(); $('[data-prop="has_background"]', props).checked = true; }
    else if (field.type === 'checkbox') b[key] = field.checked;
    else b[key] = key.endsWith('color') ? field.value.toUpperCase() : field.value;
    save();
    const node = layer.querySelector(`[data-id="${CSS.escape(b.id)}"]`);
    if (node && !['text', 'label', 'locked', 'source', 'shape', 'group', 'follow', 'field_labels', 'grid_h', 'grid_v', 'hide_header', 'stripes', 'no_row_lines', 'header_bg', 'header_color', 'label_color', 'padding'].includes(key)) { paint(node, b); node.querySelector('.eb-tag')?.replaceChildren(`${data.types[b.type]} · ${String(b.w).replace('.', ',')} × ${String(b.h).replace('.', ',')} mm`); }
    else render();
  }
  props.addEventListener('input', event => {
    if (event.target.dataset.prop) update(event.target);
    const col = event.target.dataset.colLabel, b = find(selected);
    if (col && b) {
      if (!editing) { push(snapshot()); editing = true; }
      const labels = { ...(b.column_labels || {}) }; if (event.target.value.trim()) labels[col] = event.target.value; else delete labels[col];
      if (Object.keys(labels).length) b.column_labels = labels; else delete b.column_labels;
      save(); const node = layer.querySelector(`[data-id="${CSS.escape(b.id)}"]`); if (node) { node.querySelector('.eb-body')?.replaceWith(sample(b)); paint(node, b); }
    }
  });
  props.addEventListener('change', event => {
    const field = event.target, b = find(selected); if (!b) return;
    if (field.dataset.prop) { update(field); editing = false; fill(); return; }
    if (field.dataset.column) commit(() => { b.columns = $$('[data-column]', props).filter(box => box.checked).map(box => box.dataset.column); });
    if (field.dataset.colKey) commit(() => {
      const shown = (b.columns || []).filter(k => k !== field.dataset.colKey);
      b.columns = field.checked ? [...(b.columns || []), field.dataset.colKey] : shown;
    });
    if (field.dataset.fieldKey) commit(() => {
      const shown = fieldKeys(b);
      b.fields = field.checked ? [...shown, field.dataset.fieldKey] : shown.filter(k => k !== field.dataset.fieldKey);
    });
    if (field.dataset.separator) commit(() => { b.separators = $$('[data-separator]', props).filter(box => box.checked).map(box => box.dataset.separator); });
    if (field.dataset.rowKey) commit(() => {
      const set = rowSet(), others = rowKeys(b).filter(k => !set[k]), shown = rowKeys(b).filter(k => set[k]);
      b.row_order = field.checked ? [...shown, field.dataset.rowKey, ...others] : [...shown.filter(k => k !== field.dataset.rowKey), ...others];
    });
    if (field.dataset.pageKind) {
      const pages = $$('[data-page-kind]', props).filter(box => box.checked).map(box => box.dataset.pageKind);
      if (!pages.length) { field.checked = true; return; }
      commit(() => { b.pages = pages; });
      if (!b.pages.includes(view)) setView(b.pages[0]);
    }
  });
  props.addEventListener('click', event => {
    const b = find(selected); if (!b) return;
    const align = event.target.closest('[data-align]'), insert = event.target.closest('[data-insert]');
    if (align) commit(() => { b.align = align.dataset.align; });
    const toggle = event.target.closest('[data-toggle]'), move = event.target.closest('[data-row-move]'), fieldMove = event.target.closest('[data-field-move]');
    const colMove = event.target.closest('[data-col-move]'), valign = event.target.closest('[data-valign]'), clear = event.target.closest('[data-clear-prop]'), layerMove = event.target.closest('[data-layer-move]');
    if (colMove) {
      const key = colMove.dataset.colMove, dir = Number(colMove.dataset.dir);
      commit(() => { const cols = [...(b.columns || [])], i = cols.indexOf(key), j = i + dir; if (i < 0 || j < 0 || j >= cols.length) return; [cols[i], cols[j]] = [cols[j], cols[i]]; b.columns = cols; });
      props.querySelector(`[data-col-move="${CSS.escape(key)}"][data-dir="${colMove.dataset.dir}"]`)?.focus();
    }
    if (valign) commit(() => { if (valign.dataset.valign === 'top') delete b.valign; else b.valign = valign.dataset.valign; });
    if (clear) commit(() => { delete b[clear.dataset.clearProp]; });
    if (layerMove) commit(() => { state.items = state.items.filter(item => item !== b); if (layerMove.dataset.layerMove === 'front') state.items.push(b); else state.items.unshift(b); });
    if (event.target.closest('[data-duplicate-block]')) {
      if (state.items.length >= 60) return;
      let id = b.type; for (let n = 2; find(id); n++) id = `${b.type}-${n}`;
      const copy = { ...JSON.parse(JSON.stringify(b)), id, x: b.x + 5, y: b.y + 5, locked: false }; fit(copy);
      commit(() => { state.items.push(copy); selected = id; });
      layer.querySelector(`[data-id="${CSS.escape(id)}"]`)?.focus();
    }
    if (fieldMove) {
      const key = fieldMove.dataset.fieldMove, dir = Number(fieldMove.dataset.dir);
      commit(() => { const shown = [...fieldKeys(b)], i = shown.indexOf(key), j = i + dir; if (i < 0 || j < 0 || j >= shown.length) return; [shown[i], shown[j]] = [shown[j], shown[i]]; b.fields = shown; });
      props.querySelector(`[data-field-move="${CSS.escape(key)}"][data-dir="${fieldMove.dataset.dir}"]`)?.focus();
    }
    if (toggle) commit(() => { b[toggle.dataset.toggle] = !b[toggle.dataset.toggle]; });
    if (move) {
      const key = move.dataset.rowMove, dir = Number(move.dataset.dir);
      commit(() => {
        const set = rowSet(), shown = rowKeys(b).filter(k => set[k]), others = rowKeys(b).filter(k => !set[k]), i = shown.indexOf(key), j = i + dir;
        if (i < 0 || j < 0 || j >= shown.length) return;
        [shown[i], shown[j]] = [shown[j], shown[i]]; b.row_order = [...shown, ...others];
      });
      props.querySelector(`[data-row-move="${CSS.escape(key)}"][data-dir="${move.dataset.dir}"]`)?.focus();
    }
    if (insert) {
      const area = $('[data-prop="text"]', props), start = area.selectionStart ?? area.value.length, end = area.selectionEnd ?? start;
      area.value = area.value.slice(0, start) + insert.dataset.insert + area.value.slice(end);
      area.focus(); area.setSelectionRange(start + insert.dataset.insert.length, start + insert.dataset.insert.length);
      update(area); editing = false;
    }
    if (event.target.closest('[data-remove-block]')) remove(b);
    if (event.target.closest('[data-deselect]')) { const id = selected; selected = null; render(); layers.querySelector(`[data-select="${CSS.escape(id)}"]`)?.focus(); }
  });
  function remove(b) {
    const index = onView().indexOf(b);
    commit(() => { state.items = state.items.filter(item => item !== b); selected = null; });
    (layer.querySelectorAll('.eb')[Math.max(0, index - 1)] || $('[data-add-block]'))?.focus();
  }

  /* Ajout depuis la palette : le bloc se place en haut de la zone imprimable de la page affichée. */
  const defaults = {
    logo: { w: 25, h: 20, source: 'logo' }, text: { w: 80, h: 15, text: '' }, company: { w: 80, h: 24, size: 8 }, title: { w: 70, h: 24, align: 'right', size: 8 },
    client: { w: 88, h: 24, background: '#F5ECD4' }, object: { w: 88, h: 24 }, lines: { w: 180, h: 110, size: 8, columns: Object.keys(data.columns), group: true, follow: true },
    total: { w: 80, h: 11, align: 'right', size: 11, color: '#FFFFFF', background: '#1F3A2E' }, payment: { w: 180, h: 26, size: 8 }, commitment: { w: 180, h: 16, size: 8, text: '' },
    signature: { w: 85, h: 28, size: 8, border_width: 0.3, border_color: '#DDDDDD' }, stamp: { w: 85, h: 28, size: 8, border_width: 0.3, border_color: '#DDDDDD' },
    footer: { w: 180, h: 12, align: 'center', size: 7, color: '#555555' }, shape: { w: 180, h: 0.2, shape: 'line', border_width: 0.3 },
  };
  $$('[data-add-block]', document).forEach(button => button.addEventListener('click', () => {
    if (!state) state = { format: 1, items: [] };
    if (state.items.length >= 60) { button.blur(); $('[data-layers-empty]').hidden = false; $('[data-layers-empty]').textContent = '60 blocs au maximum par modèle.'; return; }
    const type = button.dataset.addBlock, count = state.items.length;
    let id = type; for (let n = 2; find(id); n++) id = `${type}-${n}`;
    const b = { id, type, x: m.left, y: m.top + (count % 8) * 5, w: 60, h: 15, pages: [view], align: 'left', locked: false, font: 'DejaVu Sans', size: 9, color: '#111111', background: null, border_width: 0, border_color: '#C9A037', ...defaults[type] };
    fit(b);
    commit(() => { state.items.push(b); selected = id; });
    layer.querySelector(`[data-id="${CSS.escape(id)}"]`)?.focus();
  }));
  layers.addEventListener('click', event => { const button = event.target.closest('[data-select]'); if (button) select(button.dataset.select, true); });

  /* Glisser et redimensionner à la souris ou au doigt. */
  let drag = null;
  layer.addEventListener('pointerdown', event => {
    const node = event.target.closest('.eb'); if (!node || event.button > 0) return;
    if (node.dataset.id !== selected) select(node.dataset.id);
    const b = find(node.dataset.id), target = layer.querySelector(`[data-id="${CSS.escape(b.id)}"]`);
    if (b.locked) return;
    event.preventDefault(); target.focus({ preventScroll: true });
    drag = { b, node: target, resize: !!event.target.closest('[data-resize]'), x: event.clientX, y: event.clientY, start: { ...b }, before: snapshot(), moved: false };
    target.setPointerCapture(event.pointerId);
  });
  layer.addEventListener('pointermove', event => {
    if (!drag) return;
    const dx = (event.clientX - drag.x) / scale(), dy = (event.clientY - drag.y) / scale(), free = event.altKey, b = drag.b, start = drag.start;
    if (!drag.moved && Math.abs(dx) < 0.8 && Math.abs(dy) < 0.8) return;
    drag.moved = true;
    if (drag.resize) { b.w = snapSize(start.x, start.w + dx, 'x', free); b.h = b.type === 'shape' && b.shape === 'line' ? b.h : snapSize(start.y, start.h + dy, 'y', free); }
    else { b.x = snapEdge(start.x + dx, b.w, 'x', free); b.y = snapEdge(start.y + dy, b.h, 'y', free); }
    fit(b); paint(drag.node, b);
    drag.node.querySelector('.eb-tag')?.replaceChildren(`${data.types[b.type]} · ${String(b.w).replace('.', ',')} × ${String(b.h).replace('.', ',')} mm`);
  });
  const drop = () => { if (!drag) return; const moved = drag.moved, before = drag.before; drag = null; if (moved) { push(before); save(); render(); layer.querySelector(`[data-id="${CSS.escape(selected)}"]`)?.focus({ preventScroll: true }); } };
  layer.addEventListener('pointerup', drop); layer.addEventListener('pointercancel', drop);

  /* Clavier : flèches 1 mm, Maj 5 mm, Alt pour la taille ; Suppr retire ; Échap rend la main à la liste des calques. */
  layer.addEventListener('focusin', event => { const node = event.target.closest('.eb'); if (node && node.dataset.id !== selected && !drag) { selected = node.dataset.id; render(); layer.querySelector(`[data-id="${CSS.escape(selected)}"]`)?.focus(); } });
  layer.addEventListener('keydown', event => {
    const node = event.target.closest('.eb'); if (!node) return;
    const b = find(node.dataset.id), arrows = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
    if (arrows) {
      event.preventDefault(); if (b.locked) return;
      const step = event.shiftKey ? 5 : 1;
      commit(() => { if (event.altKey) { b.w += arrows[0] * step; b.h += arrows[1] * step; } else { b.x += arrows[0] * step; b.y += arrows[1] * step; } fit(b); });
      layer.querySelector(`[data-id="${CSS.escape(b.id)}"]`)?.focus();
    } else if (event.key === 'Delete' || event.key === 'Backspace') { event.preventDefault(); remove(b); }
    else if (event.key === 'Escape') { selected = null; render(); layers.querySelector(`[data-select="${CSS.escape(b.id)}"]`)?.focus(); }
    else if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $('[data-prop="x"]', props).focus(); }
  });
  document.addEventListener('keydown', event => {
    if (!(event.ctrlKey || event.metaKey) || event.target.matches('input,textarea,select')) return;
    const key = event.key.toLowerCase();
    if (key === 'z' && !event.shiftKey) { event.preventDefault(); travel(history, future); }
    else if (key === 'y' || (key === 'z' && event.shiftKey)) { event.preventDefault(); travel(future, history); }
  });
  $('[data-undo]', document).addEventListener('click', () => travel(history, future));
  $('[data-redo]', document).addEventListener('click', () => travel(future, history));

  /* Page affichée, zoom, onglets Page / Aperçu PDF. */
  function setView(next) {
    view = next;
    $$('[data-view]', document).forEach(button => button.setAttribute('aria-pressed', String(button.dataset.view === view)));
    if (selected && !find(selected)?.pages.includes(view)) selected = null;
    render();
  }
  $$('[data-view]', document).forEach(button => button.addEventListener('click', () => setView(button.dataset.view)));
  function setZoom(next) { zoom = next; $('[data-zoom-value]', document).textContent = Math.round(zoom * 100) + ' %'; render(); }
  $$('[data-zoom]', document).forEach(button => button.addEventListener('click', () => {
    const index = ZOOMS.findIndex(level => level >= zoom - 0.001);
    setZoom(ZOOMS[clamp(index + Number(button.dataset.zoom), 0, ZOOMS.length - 1)]);
  }));
  const tabs = $$('.editor-switch [role=tab]');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const toPdf = tab.id === 'tab-pdf';
    tabs.forEach(other => other.setAttribute('aria-selected', String(other === tab)));
    stage.hidden = toPdf; pdf.hidden = !toPdf;
    if (toPdf) document.getElementById('refresh-preview').click();
  }));

  $('[data-start]').addEventListener('click', () => { commit(() => { state = JSON.parse(JSON.stringify(starter)); selected = null; }); layer.querySelector('.eb')?.focus(); });
  $('[data-reset]').addEventListener('click', () => { commit(() => { state = null; selected = null; }); $('[data-start]').focus(); });

  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });

  // Zoom initial : la page tient dans la largeur disponible.
  const room = (stage.clientWidth || 600) - 40;
  zoom = ZOOMS.filter(level => PAGE.w * PX * level <= room).pop() || ZOOMS[0];
  setZoom(zoom);
})();
