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
