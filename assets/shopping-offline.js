(() => {
  const DB_NAME = 'familia-almeida-shopping';
  const DB_VERSION = 1;
  const STATE_STORE = 'states';
  const OUTBOX_STORE = 'outbox';
  const ACTIVE_KEY = 'familia-almeida-active-shopping-state';

  const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });

  const monthFormatter = new Intl.DateTimeFormat('pt-BR', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC'
  });

  const noList = document.getElementById('offline-no-list');
  const content = document.getElementById('offline-shopping-content');
  const footer = document.getElementById('offline-footer');
  const monthLabel = document.getElementById('offline-month-label');
  const listNode = document.getElementById('shopping-live-list');
  const boughtSection = document.getElementById('offline-bought-section');
  const boughtList = document.getElementById('offline-bought-list');
  const boughtCount = document.getElementById('offline-bought-count');
  const itemCount = document.getElementById('offline-item-count');
  const selectedCount = document.getElementById('shopping-selected-count');
  const selectedEstimated = document.getElementById('shopping-selected-estimated');
  const selectedActual = document.getElementById('shopping-selected-actual');
  const footerTotal = document.getElementById('shopping-footer-total');
  const finalizeButton = document.getElementById('shopping-finalize-button');
  const connectionStatus = document.getElementById('shopping-connection-status');
  const offlineBanner = document.getElementById('shopping-offline-banner');
  const pendingBanner = document.getElementById('shopping-pending-sync-banner');
  const pendingTitle = document.getElementById('pending-sync-title');
  const pendingCopy = document.getElementById('pending-sync-copy');

  let dbPromise;
  let state = null;
  let syncNeedsLogin = false;

  function openDb() {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STATE_STORE)) {
          db.createObjectStore(STATE_STORE, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(OUTBOX_STORE)) {
          db.createObjectStore(OUTBOX_STORE, { keyPath: 'client_purchase_id' });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });

    return dbPromise;
  }

  async function idbGet(storeName, key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const request = tx.objectStore(storeName).get(key);
      request.onsuccess = () => resolve(request.result || null);
      request.onerror = () => reject(request.error);
    });
  }

  async function idbGetAll(storeName) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const request = tx.objectStore(storeName).getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
  }

  async function idbPut(storeName, value) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      tx.objectStore(storeName).put(value);
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }

  async function idbDelete(storeName, key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      tx.objectStore(storeName).delete(key);
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }

  async function findSavedState() {
    const activeKey = localStorage.getItem(ACTIVE_KEY);

    if (activeKey) {
      const active = await idbGet(STATE_STORE, activeKey);
      if (active) return active;
    }

    const states = await idbGetAll(STATE_STORE);
    return states
      .filter((candidate) => String(candidate.key || '').startsWith('market:'))
      .sort((a, b) => Number(b.updatedAt || 0) - Number(a.updatedAt || 0))[0] || null;
  }

  function formatMonth(month) {
    if (!/^\d{4}-\d{2}$/.test(String(month || ''))) return 'Lista salva';
    const date = new Date(month + '-01T00:00:00Z');
    const text = monthFormatter.format(date);
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function quantityText(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3
    });
  }

  function itemEntries() {
    return Object.values(state?.items || {});
  }

  function pendingItems() {
    return itemEntries().filter((item) => !item.purchased && !item.pending_sync);
  }

  function boughtOrQueuedItems() {
    return itemEntries().filter((item) => item.purchased || item.pending_sync);
  }

  function createEl(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function buildPendingRow(item) {
    const row = createEl('article', 'shopping-live-row');
    row.dataset.itemId = String(item.id);

    const checkLabel = createEl('label', 'shopping-live-check');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = Boolean(item.selected);
    const checkVisual = createEl('span', '', '✓');
    checkLabel.append(checkbox, checkVisual);

    const product = createEl('div', 'shopping-live-product');
    product.append(
      createEl('strong', '', item.name || 'Produto'),
      createEl(
        'span',
        '',
        'Qtd. ' + quantityText(item.quantity) +
          ' • estimado ' +
          (Number(item.estimated_price || 0) > 0 ? money.format(Number(item.estimated_price)) + '/un.' : 'não informado')
      )
    );

    const estimated = createEl('div', 'shopping-live-estimated');
    estimated.append(
      createEl('small', '', 'Previsto'),
      createEl(
        'strong',
        '',
        Number(item.estimated_price || 0) > 0
          ? money.format(Number(item.quantity || 0) * Number(item.estimated_price || 0))
          : '—'
      )
    );

    const fields = createEl('div', 'shopping-purchase-fields');
    fields.hidden = !item.selected;

    const priceLabel = document.createElement('label');
    priceLabel.append(createEl('span', '', 'Preço comprado / un.'));
    const priceInput = document.createElement('input');
    priceInput.type = 'number';
    priceInput.min = '0.01';
    priceInput.step = '0.01';
    priceInput.inputMode = 'decimal';
    priceInput.placeholder = '0,00';
    priceInput.value = item.purchased_price || '';
    priceLabel.append(priceInput);

    const storeLabel = document.createElement('label');
    storeLabel.append(createEl('span', '', 'Qual mercado?'));
    const storeInput = document.createElement('input');
    storeInput.type = 'text';
    storeInput.maxLength = 160;
    storeInput.placeholder = 'Ex.: Bahamas';
    storeInput.value = item.store_name || '';
    storeLabel.append(storeInput);

    const lineTotalWrap = createEl('div');
    lineTotalWrap.append(createEl('span', '', 'Total deste produto'));
    const lineTotal = createEl('strong', '', money.format(Number(item.quantity || 0) * Number(item.purchased_price || 0)));
    lineTotalWrap.append(lineTotal);

    fields.append(priceLabel, storeLabel, lineTotalWrap);
    row.append(checkLabel, product, estimated, fields);

    row.classList.toggle('selected', Boolean(item.selected));

    checkbox.addEventListener('change', async () => {
      item.selected = checkbox.checked;
      fields.hidden = !checkbox.checked;
      row.classList.toggle('selected', checkbox.checked);
      await saveState();
      updateTotals();

      if (checkbox.checked) {
        requestAnimationFrame(() => priceInput.focus());
      }
    });

    priceInput.addEventListener('input', async () => {
      item.purchased_price = priceInput.value;
      lineTotal.textContent = money.format(Number(item.quantity || 0) * Number(priceInput.value || 0));
      row.classList.remove('needs-price');
      await saveState();
      updateTotals();
    });

    storeInput.addEventListener('input', async () => {
      item.store_name = storeInput.value;
      await saveState();
    });

    return row;
  }

  function renderBought() {
    const items = boughtOrQueuedItems();
    boughtList.replaceChildren();
    boughtCount.textContent = String(items.length);
    boughtSection.hidden = items.length === 0;

    items.forEach((item) => {
      const row = document.createElement('article');
      const icon = createEl('span', '', item.pending_sync ? '☁' : '✓');
      const copy = createEl('div');
      copy.append(
        createEl('strong', '', item.name || 'Produto'),
        createEl(
          'small',
          '',
          item.pending_sync
            ? 'Aguardando sincronização • ' + (item.store_name || 'mercado não informado')
            : (item.store_name || 'Mercado não informado') + ' • ' + money.format(Number(item.purchased_price || 0)) + '/un.'
        )
      );

      const total = createEl(
        'strong',
        '',
        money.format(Number(item.quantity || 0) * Number(item.purchased_price || 0))
      );

      row.append(icon, copy, total);
      boughtList.append(row);
    });
  }

  function render() {
    listNode.replaceChildren();

    const items = pendingItems();
    itemCount.textContent = String(items.length);

    if (!items.length) {
      const empty = createEl('div', 'shopping-mode-empty');
      empty.append(
        createEl('span', '', '✓'),
        createEl('strong', '', 'Nenhum produto pendente'),
        createEl('p', '', 'A lista foi concluída ou os itens estão aguardando sincronização.')
      );
      listNode.append(empty);
    } else {
      items.forEach((item) => listNode.append(buildPendingRow(item)));
    }

    renderBought();
    updateTotals();
  }

  async function saveState() {
    if (!state) return;
    state.updatedAt = Date.now();
    await idbPut(STATE_STORE, state);
  }

  function selectedItems() {
    return pendingItems().filter((item) => item.selected);
  }

  function updateTotals() {
    const selected = selectedItems();
    const estimated = selected.reduce(
      (sum, item) => sum + Number(item.quantity || 0) * Number(item.estimated_price || 0),
      0
    );
    const actual = selected.reduce(
      (sum, item) => sum + Number(item.quantity || 0) * Number(item.purchased_price || 0),
      0
    );

    selectedCount.textContent = String(selected.length);
    selectedEstimated.textContent = money.format(estimated);
    selectedActual.textContent = money.format(actual);
    footerTotal.textContent = money.format(actual);
    finalizeButton.disabled = selected.length === 0;
  }

  function uuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return 'purchase-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  function localDate() {
    const now = new Date();
    return [
      now.getFullYear(),
      String(now.getMonth() + 1).padStart(2, '0'),
      String(now.getDate()).padStart(2, '0')
    ].join('-');
  }

  function buildPayload() {
    const selected = selectedItems();
    const missing = selected.find((item) => !(Number(item.purchased_price || 0) > 0));

    if (!selected.length) {
      throw new Error('Selecione pelo menos um produto.');
    }

    if (missing) {
      const row = listNode.querySelector('[data-item-id="' + String(missing.id) + '"]');
      row?.classList.add('needs-price');
      row?.querySelector('input[type="number"]')?.focus();
      throw new Error('Informe o preço comprado de todos os produtos selecionados.');
    }

    return {
      client_purchase_id: uuid(),
      list_id: state.listId,
      month: state.month,
      purchase_date: localDate(),
      items: selected.map((item) => ({
        id: Number(item.id),
        purchased_price: Number(item.purchased_price || 0),
        store_name: String(item.store_name || '').trim()
      })),
      csrf_token: state.csrfToken || '',
      created_at: Date.now()
    };
  }

  async function queuePayload(payload) {
    await idbPut(OUTBOX_STORE, payload);

    payload.items.forEach((queued) => {
      const item = state.items[String(queued.id)];
      if (!item) return;
      item.selected = false;
      item.pending_sync = true;
      item.purchased_price = queued.purchased_price;
      item.store_name = queued.store_name;
    });

    await saveState();
    render();
  }

  async function sendPayload(payload) {
    const response = await fetch(state.syncUrl || '/api/compras/sincronizar', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const error = new Error('LOGIN_REQUIRED');
      error.loginRequired = true;
      throw error;
    }

    const body = await response.json();

    if (!response.ok || !body.ok) {
      const message = String(body.message || '');
      const error = new Error(message || 'Não foi possível sincronizar.');
      if (response.status === 401 || response.status === 403 || response.status === 419 || /sessão|autent/i.test(message)) {
        error.loginRequired = true;
      }
      throw error;
    }

    return body;
  }

  function markPayloadPurchased(payload) {
    payload.items.forEach((synced) => {
      const item = state.items[String(synced.id)];
      if (!item) return;
      item.selected = false;
      item.pending_sync = false;
      item.purchased = true;
      item.purchased_price = synced.purchased_price;
      item.store_name = synced.store_name;
    });
  }

  async function finalize() {
    let payload;

    try {
      payload = buildPayload();
    } catch (error) {
      alert(error.message);
      return;
    }

    const total = payload.items.reduce((sum, selected) => {
      const item = state.items[String(selected.id)];
      return sum + Number(item?.quantity || 0) * Number(selected.purchased_price || 0);
    }, 0);

    if (!confirm('Finalizar esta compra em ' + money.format(total) + '?')) return;

    finalizeButton.disabled = true;
    finalizeButton.textContent = navigator.onLine ? 'Finalizando...' : 'Salvando offline...';

    try {
      if (navigator.onLine && state.csrfToken) {
        try {
          await sendPayload(payload);
          markPayloadPurchased(payload);
          await saveState();
          render();
          alert('Compra sincronizada com o financeiro.');
          return;
        } catch (error) {
          if (error.loginRequired) syncNeedsLogin = true;
        }
      }

      await queuePayload(payload);
      alert(
        navigator.onLine
          ? 'Compra guardada neste aparelho. Entre no sistema depois para sincronizar.'
          : 'Compra salva offline. Ela será sincronizada quando for possível.'
      );
    } finally {
      finalizeButton.textContent = 'Finalizar compra';
      updateSyncUi();
    }
  }

  async function syncOutbox() {
    if (!navigator.onLine || !state) return;

    const queued = await idbGetAll(OUTBOX_STORE);
    const current = queued.filter((payload) => Number(payload.list_id) === Number(state.listId));

    if (!current.length) {
      syncNeedsLogin = false;
      updateSyncUi();
      return;
    }

    for (const payload of current) {
      try {
        // Usa o token mais recente salvo na lista, não o token antigo da fila.
        const freshPayload = {
          ...payload,
          csrf_token: state.csrfToken || payload.csrf_token || ''
        };

        await sendPayload(freshPayload);
        markPayloadPurchased(payload);
        await idbDelete(OUTBOX_STORE, payload.client_purchase_id);
        syncNeedsLogin = false;
      } catch (error) {
        if (error.loginRequired) syncNeedsLogin = true;
        break;
      }
    }

    await saveState();
    render();
    updateSyncUi();
  }

  async function pendingCount() {
    const queued = await idbGetAll(OUTBOX_STORE);
    return queued.filter((payload) => Number(payload.list_id) === Number(state?.listId)).length;
  }

  async function updateSyncUi() {
    const online = navigator.onLine;

    connectionStatus.classList.toggle('offline', !online);
    connectionStatus.querySelector('strong').textContent = online ? 'Online' : 'Offline';
    offlineBanner.hidden = online;

    if (!state) {
      pendingBanner.hidden = true;
      return;
    }

    const queued = await pendingCount();
    pendingBanner.hidden = queued === 0;

    if (queued > 0 && online && syncNeedsLogin) {
      pendingTitle.textContent = 'Compra salva. Falta autenticar para sincronizar.';
      pendingCopy.textContent = 'Nada será perdido. Quando puder, abra o sistema e faça login; a fila continuará neste aparelho.';
    } else if (queued > 0) {
      pendingTitle.textContent = 'Compra aguardando sincronização.';
      pendingCopy.textContent = online
        ? 'Estamos tentando enviar os dados ao financeiro.'
        : 'Quando a internet voltar, tentaremos enviar os dados ao financeiro.';
    }
  }

  async function init() {
    try {
      state = await findSavedState();
    } catch (_) {
      state = null;
    }

    if (!state || !state.items || !Object.values(state.items).some((item) => item.name)) {
      noList.hidden = false;
      content.hidden = true;
      footer.hidden = true;
      updateSyncUi();
      return;
    }

    localStorage.setItem(ACTIVE_KEY, state.key);
    monthLabel.textContent = formatMonth(state.month);
    noList.hidden = true;
    content.hidden = false;
    footer.hidden = false;

    render();
    await updateSyncUi();

    if (navigator.onLine) {
      await syncOutbox();
    }
  }

  document.getElementById('offline-back')?.addEventListener('click', () => {
    if (history.length > 1) {
      history.back();
    } else if (navigator.onLine) {
      window.location.href = '/compras';
    }
  });

  finalizeButton?.addEventListener('click', finalize);

  window.addEventListener('online', async () => {
    await updateSyncUi();
    await syncOutbox();
  });

  window.addEventListener('offline', updateSyncUi);

  init();
})();