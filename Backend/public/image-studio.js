/* Images et portfolio : recadrage avant l'envoi et carte à partager, tout dans le navigateur.
   Le recadrage remplace le fichier choisi par l'image recadrée (JPEG, 2 Mo maximum) : le serveur reçoit un import normal. */
(() => {
  'use strict';
  const input = document.querySelector('[data-image-input]');
  const preview = document.querySelector('[data-image-preview]');
  const savedSrc = preview?.getAttribute('src') || '';
  const MAX_SIDE = 2400, MAX_BYTES = 1.9 * 1024 * 1024;
  const loadImage = src => new Promise((resolve, reject) => { const img = new Image(); img.onload = () => resolve(img); img.onerror = reject; img.src = src; });
  const kb = n => n > 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' Mo' : Math.round(n / 1024) + ' Ko';
  const toBlob = (canvas, type, q) => new Promise(resolve => canvas.toBlob(resolve, type, q));
  const emit = () => document.dispatchEvent(new CustomEvent('mds:image-source'));

  /* ---------- Recadrage ---------- */
  const box = document.querySelector('[data-cropper]');
  if (box && input) {
    const canvas = box.querySelector('[data-crop-canvas]'), ctx = canvas.getContext('2d');
    const zoomInput = box.querySelector('[data-crop-zoom]'), status = box.querySelector('[data-crop-status]');
    const currentBtn = document.querySelector('[data-crop-current]');
    let img = null, original = null, ratio = 16 / 10, zoom = 1, cx = 0, cy = 0, applying = false, url = null;

    const parseRatio = v => { if (v === 'original') return null; const [a, b] = v.split('/').map(Number); return a / b; };
    // Rectangle découpé dans l'image : plus grand rectangle au bon format, divisé par le zoom, centré sur (cx, cy).
    const rect = () => {
      if (!ratio) return { x: 0, y: 0, w: img.naturalWidth, h: img.naturalHeight };
      const iw = img.naturalWidth, ih = img.naturalHeight;
      let w = iw / ih > ratio ? ih * ratio : iw, h = w / ratio;
      w /= zoom; h /= zoom;
      cx = Math.min(Math.max(cx, w / 2), iw - w / 2); cy = Math.min(Math.max(cy, h / 2), ih - h / 2);
      return { x: cx - w / 2, y: cy - h / 2, w, h };
    };
    const draw = () => {
      if (!img) return;
      const r = rect(), css = Math.min(box.querySelector('.cropper-stage').clientWidth || 560, 640), dpr = window.devicePixelRatio || 1;
      const cssH = Math.min(css / (r.w / r.h), 520), cssW = cssH * (r.w / r.h);
      canvas.style.width = cssW + 'px'; canvas.style.height = cssH + 'px';
      canvas.width = Math.round(cssW * dpr); canvas.height = Math.round(cssH * dpr);
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, r.x, r.y, r.w, r.h, 0, 0, canvas.width, canvas.height);
      // Repères des tiers, discrets, pour placer le sujet.
      ctx.strokeStyle = 'rgba(255,255,255,.45)'; ctx.lineWidth = dpr;
      for (const f of [1 / 3, 2 / 3]) {
        ctx.beginPath(); ctx.moveTo(canvas.width * f, 0); ctx.lineTo(canvas.width * f, canvas.height); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(0, canvas.height * f); ctx.lineTo(canvas.width, canvas.height * f); ctx.stroke();
      }
      zoomInput.disabled = !ratio;
      status.textContent = `Image envoyée : ${Math.round(Math.min(r.w, r.w * MAX_SIDE / Math.max(r.w, r.h)))} × ${Math.round(Math.min(r.h, r.h * MAX_SIDE / Math.max(r.w, r.h)))} px`;
    };
    const open = async (file) => {
      original = file;
      if (url) URL.revokeObjectURL(url);
      url = URL.createObjectURL(file);
      try { img = await loadImage(url); } catch (_) { status.textContent = 'Image illisible : choisissez un fichier JPG, PNG ou WebP.'; return; }
      ratio = parseRatio(box.querySelector('[name=crop_ui]:checked').value); zoom = 1; zoomInput.value = 1;
      cx = img.naturalWidth / 2; cy = img.naturalHeight / 2;
      box.hidden = false; if (currentBtn) currentBtn.hidden = true; draw();
      box.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    };
    const setFile = file => { const dt = new DataTransfer(); dt.items.add(file); applying = true; input.files = dt.files; input.dispatchEvent(new Event('change', { bubbles: true })); applying = false; emit(); };
    const close = () => { box.hidden = true; if (currentBtn) currentBtn.hidden = !savedSrc; };

    input.addEventListener('change', () => { if (applying) return; const f = input.files[0]; if (f && f.type.startsWith('image/')) open(f); else close(); emit(); });
    currentBtn?.addEventListener('click', async () => {
      currentBtn.disabled = true;
      try { const blob = await (await fetch(savedSrc, { credentials: 'same-origin' })).blob(); await open(new File([blob], 'image-actuelle.' + (blob.type.split('/')[1] || 'jpg'), { type: blob.type })); }
      catch (_) { status.textContent = 'Image actuelle indisponible.'; }
      finally { currentBtn.disabled = false; }
    });
    box.addEventListener('change', e => {
      if (e.target.name === 'crop_ui') { ratio = parseRatio(e.target.value); zoom = 1; zoomInput.value = 1; draw(); }
      e.stopPropagation();
    });
    zoomInput.addEventListener('input', () => { zoom = Number(zoomInput.value); draw(); });

    // Glisser pour déplacer ; flèches et +/- au clavier.
    let drag = null;
    canvas.addEventListener('pointerdown', e => { if (!ratio) return; drag = { x: e.clientX, y: e.clientY }; canvas.setPointerCapture(e.pointerId); });
    canvas.addEventListener('pointermove', e => {
      if (!drag) return; const r = rect(), k = r.w / canvas.clientWidth;
      cx -= (e.clientX - drag.x) * k; cy -= (e.clientY - drag.y) * k; drag = { x: e.clientX, y: e.clientY }; draw();
    });
    canvas.addEventListener('pointerup', () => { drag = null; });
    canvas.addEventListener('keydown', e => {
      if (!img || !ratio) return; const r = rect(), step = r.w * 0.03;
      const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
      if (moves[e.key]) { cx += moves[e.key][0]; cy += moves[e.key][1]; }
      else if (e.key === '+' || e.key === '=') zoom = Math.min(4, zoom + 0.1);
      else if (e.key === '-') zoom = Math.max(1, zoom - 0.1);
      else return;
      e.preventDefault(); zoomInput.value = zoom; draw();
    });
    window.addEventListener('resize', draw);

    box.querySelector('[data-crop-apply]').addEventListener('click', async () => {
      const r = rect(), scale = Math.min(1, MAX_SIDE / Math.max(r.w, r.h)), out = document.createElement('canvas');
      out.width = Math.round(r.w * scale); out.height = Math.round(r.h * scale);
      const o = out.getContext('2d'); o.imageSmoothingQuality = 'high'; o.fillStyle = '#fff'; o.fillRect(0, 0, out.width, out.height);
      o.drawImage(img, r.x, r.y, r.w, r.h, 0, 0, out.width, out.height);
      let blob = null;
      for (const q of [0.9, 0.84, 0.78, 0.7, 0.6]) { blob = await toBlob(out, 'image/jpeg', q); if (blob.size <= MAX_BYTES) break; }
      const name = (original.name.replace(/\.[^.]+$/, '') || 'image') + '-recadree.jpg';
      setFile(new File([blob], name, { type: 'image/jpeg' }));
      close();
      document.querySelector('[data-image-name]').textContent = `${name} : ${out.width} × ${out.height} px, ${kb(blob.size)}. Envoyée à l’enregistrement.`;
    });
    box.querySelector('[data-crop-cancel]').addEventListener('click', () => { if (original && original.name !== 'image-actuelle.' + original.type.split('/')[1]) setFile(original); close(); });
  }

  /* ---------- Carte à partager ---------- */
  const card = document.querySelector('[data-share-card]');
  if (!card) return;
  const canvas = card.querySelector('[data-share-canvas]'), ctx = canvas.getContext('2d');
  const titleIn = card.querySelector('[data-share-title]'), lineIn = card.querySelector('[data-share-line]'), status = card.querySelector('[data-share-status]');
  const FORMATS = { link: [1200, 630], square: [1080, 1080], story: [1080, 1920] };
  const C = { pine: '#20332c', pine2: '#2c423a', gold: '#c09f6a', goldL: '#d9b77e', mist: '#d3dcd7', white: '#ffffff' };
  let photo = null, logo = null, fontsReady = null, pending = 0;

  const fonts = () => fontsReady ??= Promise.all([
    new FontFace('Cormorant Garamond', 'url(/fonts/cormorant-garamond-latin-600-normal.woff2)', { weight: '600' }),
    new FontFace('Inter', 'url(/fonts/inter-latin-500-normal.woff2)', { weight: '500' }),
  ].map(f => f.load().then(l => document.fonts.add(l)).catch(() => null)));
  const sourceUrl = () => input?.files[0] ? URL.createObjectURL(input.files[0]) : card.dataset.image;

  const cover = (img, x, y, w, h) => {
    const s = Math.max(w / img.naturalWidth, h / img.naturalHeight), sw = w / s, sh = h / s;
    ctx.drawImage(img, (img.naturalWidth - sw) / 2, (img.naturalHeight - sh) / 2, sw, sh, x, y, w, h);
  };
  const wrap = (text, maxW, maxLines) => {
    const words = text.trim().split(/\s+/).filter(Boolean), lines = [];
    let line = '';
    for (const w of words) {
      const next = line ? line + ' ' + w : w;
      if (ctx.measureText(next).width <= maxW || !line) line = next; else { lines.push(line); line = w; }
    }
    if (line) lines.push(line);
    if (lines.length > maxLines) { lines.length = maxLines; lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…'; }
    return lines;
  };
  const mark = (x, y, size) => {
    ctx.save(); ctx.beginPath(); ctx.arc(x + size / 2, y + size / 2, size / 2, 0, Math.PI * 2); ctx.fillStyle = C.white; ctx.fill(); ctx.restore();
    if (logo) ctx.drawImage(logo, x + size * 0.06, y + size * 0.06, size * 0.88, size * 0.88);
  };
  // Bloc texte : marque, titre en Cormorant, filet or, ligne secondaire, adresse du site en bas.
  const text = (x, y, w, h, sizes) => {
    const [titleSize, lineSize, markSize] = sizes;
    mark(x, y, markSize);
    let ty = y + markSize + titleSize * 0.9;
    ctx.fillStyle = C.white; ctx.font = `600 ${titleSize}px "Cormorant Garamond", Georgia, serif`; ctx.textBaseline = 'alphabetic';
    for (const l of wrap(titleIn.value || 'Maison du Store AM', w, 3)) { ctx.fillText(l, x, ty); ty += titleSize * 1.08; }
    ctx.fillStyle = C.gold; ctx.fillRect(x, ty - titleSize * 0.35, Math.min(96, w * 0.25), Math.max(3, titleSize / 18));
    ty += lineSize * 1.2;
    ctx.fillStyle = C.mist; ctx.font = `500 ${lineSize}px Inter, system-ui, sans-serif`;
    for (const l of wrap(lineIn.value, w, 2)) { ctx.fillText(l, x, ty); ty += lineSize * 1.4; }
    ctx.fillStyle = C.goldL; ctx.font = `500 ${lineSize * 0.86}px Inter, system-ui, sans-serif`;
    ctx.fillText('maisondustoream.com', x, y + h);
  };
  const render = async () => {
    const ticket = ++pending;
    await fonts();
    try { logo ??= await loadImage(card.dataset.logo); } catch (_) { logo = null; }
    const src = sourceUrl();
    try { photo = src ? await loadImage(src) : null; } catch (_) { photo = null; }
    if (src?.startsWith('blob:')) URL.revokeObjectURL(src);
    if (ticket !== pending) return;
    const format = card.querySelector('[name=share_format]:checked').value, [W, H] = FORMATS[format];
    canvas.width = W; canvas.height = H; canvas.dataset.format = format;
    const band = ctx.createLinearGradient(0, 0, 0, H); band.addColorStop(0, C.pine2); band.addColorStop(1, C.pine);
    ctx.fillStyle = band; ctx.fillRect(0, 0, W, H);
    if (format === 'link') {
      const iw = Math.round(W * 0.56);
      if (photo) cover(photo, 0, 0, iw, H);
      ctx.fillStyle = C.gold; ctx.fillRect(iw, 0, 6, H);
      text(iw + 64, 56, W - iw - 120, H - 56 - 52, [54, 24, 76]);
    } else {
      const ih = Math.round(H * (format === 'story' ? 0.62 : 0.6));
      if (photo) cover(photo, 0, 0, W, ih);
      ctx.fillStyle = C.gold; ctx.fillRect(0, ih, W, 6);
      const pad = format === 'story' ? 88 : 72, sizes = format === 'story' ? [104, 42, 136] : [70, 30, 96];
      text(pad, ih + pad * 0.75, W - pad * 2, H - ih - pad * 1.65, sizes);
    }
    if (!photo) { ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = '500 26px Inter, sans-serif'; ctx.fillText('Ajoutez une image ci-dessus', 48, 64); }
    canvas.setAttribute('aria-label', `Aperçu de la carte à partager, ${W} × ${H} pixels : ${titleIn.value}`);
    status.textContent = `${W} × ${H} px`;
  };
  let frame = 0;
  const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(render); };
  card.addEventListener('input', schedule); card.addEventListener('change', schedule);
  document.addEventListener('mds:image-source', schedule);
  card.querySelector('[data-share-download]').addEventListener('click', async () => {
    await render();
    const blob = await toBlob(canvas, 'image/jpeg', 0.92), a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = `carte-${card.dataset.slot}-${canvas.dataset.format}.jpg`;
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    status.textContent = `Carte téléchargée : ${canvas.width} × ${canvas.height} px, ${kb(blob.size)}.`;
  });
  render();
})();
