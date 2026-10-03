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
