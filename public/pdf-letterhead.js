(() => {
  const root = document.getElementById('pdf-letterhead');
  if (!root) return;
  const images = [];
  root.querySelectorAll('[data-letterhead-image]').forEach(box => {
    const input = box.querySelector('input[type="file"]');
    const preview = box.querySelector('[data-letterhead-preview]');
    const remove = box.querySelector('input[type="checkbox"]');
    const saved = preview.getAttribute('src');
    let url;
    function updateImage() {
      if (url) URL.revokeObjectURL(url);
      url = input.files[0] ? URL.createObjectURL(input.files[0]) : null;
      const source = url || (remove?.checked ? null : saved);
      preview.hidden = !source;
      if (source) preview.src = source; else preview.removeAttribute('src');
    }
    input.addEventListener('change', updateImage);
    remove?.addEventListener('change', updateImage);
    images.push(preview); updateImage();
  });
  function updateLayout() {
    const margins = {};
    for (const side of ['top','bottom','left','right']) {
      const input = root.querySelector(`[name="margin_${side}_mm"]`);
      const max = Number(input.max);
      margins[side] = Math.min(max, Math.max(5, Number(input.value) || 5)) / (['top','bottom'].includes(side) ? 297 : 210) * 100;
    }
    root.querySelectorAll('[data-letterhead-text]').forEach(box => {
      for (const side of Object.keys(margins)) box.style[side] = margins[side] + '%';
    });
    const enabled = root.querySelector('input[type="checkbox"][name="letterhead_enabled"]').checked;
    const opacity = Math.min(100, Math.max(10, Number(root.querySelector('[name="letterhead_opacity"]').value) || 10)) / 100;
    images.forEach(img => { img.style.opacity = enabled ? opacity : 0.2; });
  }
  root.addEventListener('input', updateLayout); updateLayout();
})();
