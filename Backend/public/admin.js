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
});
document.addEventListener('submit', event => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) event.preventDefault();
});
(() => {
  const nav=document.getElementById('staff-navigation');if(!nav)return;
  const buttons=[...document.querySelectorAll('[data-nav-scroll]')],controls=document.querySelector('.sidebar-controls');
  const horizontal=()=>matchMedia('(max-width:850px)').matches;
  function update(){const max=horizontal()?nav.scrollWidth-nav.clientWidth:nav.scrollHeight-nav.clientHeight,pos=horizontal()?nav.scrollLeft:nav.scrollTop;controls.hidden=max<2;buttons.forEach(b=>b.disabled=Number(b.dataset.navScroll)<0?pos<2:pos>=max-2);}
  buttons.forEach(b=>b.addEventListener('click',()=>nav.scrollBy({[horizontal()?'left':'top']:Number(b.dataset.navScroll)*(horizontal()?nav.clientWidth:nav.clientHeight)*0.7,behavior:'smooth'})));
  nav.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update);update();
})();
(() => {
  const button = document.getElementById('pwa-install');
  if (!button) return;

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {});
  }

  const isInstalled = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (isInstalled) return;

  let installPrompt = null;
  button.hidden = false;

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
    button.textContent = 'Installer l’application';
  });

  window.addEventListener('appinstalled', () => { button.hidden = true; });

  button.addEventListener('click', async () => {
    if (installPrompt) {
      await installPrompt.prompt();
      const { outcome } = await installPrompt.userChoice;
      installPrompt = null;
      if (outcome === 'accepted') button.hidden = true;
      return;
    }

    const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent);
    window.alert(isAppleMobile
      ? 'Dans Safari, touchez Partager puis « Sur l’écran d’accueil » et Ajouter.'
      : 'Ouvrez le menu du navigateur puis choisissez « Installer l’application » ou « Ajouter à l’écran d’accueil ».');
  });
})();
(() => {
  const sidebar = document.getElementById('staff-sidebar');
  const toggle = document.getElementById('pro-menu-toggle');
  if (!sidebar || !toggle) return;

  const setOpen = open => {
    sidebar.classList.toggle('menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? 'Fermer' : 'Menu';
  };

  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  document.addEventListener('click', event => {
    if (sidebar.classList.contains('menu-open') && !sidebar.contains(event.target)) setOpen(false);
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
  const desktop = window.matchMedia('(min-width: 851px)');
  const closeOnDesktop = event => { if (event.matches) setOpen(false); };
  if (desktop.addEventListener) desktop.addEventListener('change', closeOnDesktop);
  else desktop.addListener(closeOnDesktop);
})();
