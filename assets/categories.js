(() => {
  const input = document.getElementById('category-input');
  const menu = document.getElementById('category-options');
  const typeInput = document.getElementById('transaction-type');
  if (!input || !menu || !typeInput) return;
  const source = window.familiaCategories || {income: [], expense: []};
  let activeIndex = -1;
  let displayed = [];
  const fold = (v) => v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
  function close() {
    menu.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    activeIndex = -1;
  }
  function options() {
    const type = typeInput.value === 'income' ? 'income' : 'expense';
    const text = input.value.trim();
    const items = (source[type] || []).filter((item) => fold(item.name).includes(fold(text)));
    const exact = items.some((item) => fold(item.name) === fold(text));
    displayed = items.slice(0, 10).map((item) => ({text:item.name, newItem:false}));
    if (text && !exact) displayed.push({text, newItem:true});
    menu.replaceChildren();
    if (!displayed.length) {
      const p = document.createElement('span');
      p.className = 'smart-category-empty';
      p.textContent = 'Nenhuma categoria. Digite para criar.';
      menu.append(p);
    }
    displayed.forEach((item, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.id = 'category-option-' + index;
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', 'false');
      button.className = 'smart-category-option';
      const icon = document.createElement('span');
      icon.className = 'smart-category-option-icon';
      icon.textContent = item.newItem ? '+' : '✓';
      const textSpan = document.createElement('span');
      textSpan.textContent = item.newItem ? 'Criar “' + item.text + '”' : item.text;
      button.append(icon, textSpan);
      button.addEventListener('mousedown', e => e.preventDefault());
      button.addEventListener('click', () => pick(index));
      menu.append(button);
    });
    menu.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    activeIndex = -1;
  }
  function pick(index) {
    if (!displayed[index]) return;
    input.value = displayed[index].text;
    close();
    input.focus();
  }
  function highlight(index) {
    activeIndex = index;
    Array.from(menu.querySelectorAll('[role="option"]')).forEach((element, i) => {
      const selected = i === index;
      element.classList.toggle('active', selected);
      element.setAttribute('aria-selected', String(selected));
    });
    if (index >= 0) input.setAttribute('aria-activedescendant', 'category-option-' + index);
    else input.removeAttribute('aria-activedescendant');
  }
  input.addEventListener('focus', options);
  input.addEventListener('input', options);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { close(); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (menu.hidden) options();
      if (displayed.length) highlight((activeIndex + (e.key === 'ArrowDown' ? 1 : -1) + displayed.length) % displayed.length);
    }
    if (e.key === 'Enter' && !menu.hidden && activeIndex >= 0) {
      e.preventDefault();
      pick(activeIndex);
    }
  });
  input.addEventListener('blur', () => setTimeout(close, 150));
  document.addEventListener('pointerdown', (e) => {
    if (!document.getElementById('smart-category')?.contains(e.target)) close();
  });
  typeInput.addEventListener('change', () => { input.value = ''; close(); });
})();
