(() => {
  'use strict';
  const rows = document.getElementById('csa-faq-rows');
  const template = document.getElementById('csa-faq-template');
  const add = document.getElementById('csa-add-faq');
  if (!rows || !template || !add) return;
  let index = rows.children.length;
  add.addEventListener('click', () => {
    const row = template.content.cloneNode(true);
    row.querySelectorAll('[name]').forEach(field => {
      field.name = field.name.replace('__INDEX__', String(index));
    });
    index += 1;
    rows.appendChild(row);
    rows.lastElementChild.querySelector('input').focus();
  });
  rows.addEventListener('click', event => {
    const button = event.target.closest('.csa-remove-faq');
    if (button) button.closest('.csa-faq-row').remove();
  });
})();
