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
  const addProductButton = document.getElementById('offline-add-product');
  const productDialog = document.getElementById('offline-product-dialog');
  const productForm = document.getElementById('offline-product-form');
  const productName = document.getElementById('offline-product-name');
  const productQuantity = document.getElementById('offline-product-quantity');
  const productEstimated = document.getElementById('offline-product-estimated');
  const productTrack = document.getElementById('offline-product-track');

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

    if (!(Number(item.purchased_quantity) > 0)) {
      item.purchased_quantity = Number(item.quantity || 1);
    }

    const checkLabel = createEl('label', 'shopping-live-check');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = Boolean(item.selected);
    const checkVisual = createEl('span', '', '✓');
    checkLabel.append(checkbox, checkVisual);

    const product = createEl('div', 'shopping-live-product');
    const title = createEl('div', 'shopping-live-product-title');
    title.append(
      createEl('strong', '', item.name || 'Produto'),
      createEl('span', 'shopping-stock-tag ' + (item.track_inventory === false ? 'quick' : 'stock'), item.track_inventory === false ? 'Consumo rápido' : 'Estoque')
    );
    product.append(
      title,
      createEl(
        'span',
        '',
        'Planejado: ' + quantityText(item.quantity) +
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

    const quantityLabel = createEl('label', 'shopping-quantity-field');
    quantityLabel.append(createEl('span', '', 'Qtd. comprada'));

    const quantityControl = createEl('div', 'shopping-quantity-control');
    const minusButton = createEl('button', '', '−');
    minusButton.type = 'button';
    minusButton.setAttribute('aria-label', 'Diminuir quantidade');

    const quantityInput = document.createElement('input');
    quantityInput.type = 'number';
    quantityInput.min = '0.01';
    quantityInput.step = '0.01';
    quantityInput.inputMode = 'decimal';
    quantityInput.value = Number(item.purchased_quantity || item.quantity || 1);

    const plusButton = createEl('button', '', '＋');
    plusButton.type = 'button';
    plusButton.setAttribute('aria-label', 'Aumentar quantidade');

    quantityControl.append(minusButton, quantityInput, plusButton);
    quantityLabel.append(quantityControl);

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

    const storeLabel = createEl('label', 'shopping-store-field');
    storeLabel.append(createEl('span', '', 'Qual mercado?'));
    const storeInput = document.createElement('input');
    storeInput.type = 'text';
    storeInput.maxLength = 160;
    storeInput.placeholder = 'Ex.: Bahamas';
    storeInput.value = item.store_name || '';
    storeLabel.append(storeInput);

    const destinationLabel = createEl('label', 'shopping-destination-field');
    destinationLabel.append(createEl('span', '', 'Destino'));
    const destinationSelect = document.createElement('select');
    const stockOption = document.createElement('option');
    stockOption.value = '1';
    stockOption.textContent = 'Vai para o estoque';
    const quickOption = document.createElement('option');
    quickOption.value = '0';
    quickOption.textContent = 'Consumo rápido';
    destinationSelect.append(stockOption, quickOption);
    destinationSelect.value = item.track_inventory === false ? '0' : '1';
    destinationLabel.append(destinationSelect);

    const lineTotalWrap = createEl('div', 'shopping-line-total-box');
    lineTotalWrap.append(createEl('span', '', 'Total deste produto'));
    const lineTotal = createEl(
      'strong',
      '',
      money.format(Number(item.purchased_quantity || item.quantity || 0) * Number(item.purchased_price || 0))
    );
    lineTotalWrap.append(lineTotal);

    fields.append(quantityLabel, priceLabel, storeLabel, destinationLabel, lineTotalWrap);
    row.append(checkLabel, product, estimated, fields);

    row.classList.toggle('selected', Boolean(item.selected));

    async function saveQuantity(value) {
      const numeric = Math.max(0.01, Number(value || 0.01));
      item.purchased_quantity = Math.round(numeric * 100) / 100;
      quantityInput.value = String(item.purchased_quantity);
      lineTotal.textContent = money.format(item.purchased_quantity * Number(priceInput.value || 0));
      row.classList.remove('needs-quantity');
      await saveState();
      updateTotals();
    }

    checkbox.addEventListener('change', async () => {
      item.selected = checkbox.checked;
      if (checkbox.checked && !(Number(item.purchased_quantity) > 0)) {
        item.purchased_quantity = Number(item.quantity || 1);
        quantityInput.value = String(item.purchased_quantity);
      }
      fields.hidden = !checkbox.checked;
      row.classList.toggle('selected', checkbox.checked);
      await saveState();
      updateTotals();

      if (checkbox.checked) {
        requestAnimationFrame(() => priceInput.focus());
      }
    });

    quantityInput.addEventListener('input', async () => {
      item.purchased_quantity = quantityInput.value;
      lineTotal.textContent = money.format(Number(quantityInput.value || 0) * Number(priceInput.value || 0));
      row.classList.remove('needs-quantity');
      await saveState();
      updateTotals();
    });

    minusButton.addEventListener('click', () => {
      saveQuantity(Number(quantityInput.value || item.purchased_quantity || item.quantity || 1) - 1);
    });

    plusButton.addEventListener('click', () => {
      saveQuantity(Number(quantityInput.value || item.purchased_quantity || item.quantity || 1) + 1);
    });

    priceInput.addEventListener('input', async () => {
      item.purchased_price = priceInput.value;
      lineTotal.textContent = money.format(Number(item.purchased_quantity || item.quantity || 0) * Number(priceInput.value || 0));
      row.classList.remove('needs-price');
      await saveState();
      updateTotals();
    });

    storeInput.addEventListener('input', async () => {
      item.store_name = storeInput.value;
      await saveState();
    });

    destinationSelect.addEventListener('change', async () => {
      item.track_inventory = destinationSelect.value !== '0';
      const badge = title.querySelector('.shopping-stock-tag');
      badge.textContent = item.track_inventory ? 'Estoque' : 'Consumo rápido';
      badge.classList.toggle('stock', item.track_inventory);
      badge.classList.toggle('quick', !item.track_inventory);
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
            ? 'Aguardando sincronização • ' + (item.track_inventory === false ? 'consumo rápido' : 'estoque') + ' • qtd. ' + quantityText(item.purchased_quantity || item.quantity) + ' • ' + (item.store_name || 'mercado não informado')
            : (item.store_name || 'Mercado não informado') + ' • ' + (item.track_inventory === false ? 'consumo rápido' : 'estoque') + ' • qtd. ' + quantityText(item.purchased_quantity || item.quantity) + ' • ' + money.format(Number(item.purchased_price || 0)) + '/un.'
        )
      );

      const total = createEl(
        'strong',
        '',
        money.format(Number(item.purchased_quantity || item.quantity || 0) * Number(item.purchased_price || 0))
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
      (sum, item) => sum + Number(item.purchased_quantity || item.quantity || 0) * Number(item.estimated_price || 0),
      0
    );
    const actual = selected.reduce(
      (sum, item) => sum + Number(item.purchased_quantity || item.quantity || 0) * Number(item.purchased_price || 0),
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
    if (!selected.length) {
      throw new Error('Selecione pelo menos um produto.');
    }

    const missingQuantity = selected.find((item) => !(Number(item.purchased_quantity || 0) > 0));
    if (missingQuantity) {
      const row = listNode.querySelector('[data-item-id="' + String(missingQuantity.id) + '"]');
      row?.classList.add('needs-quantity');
      row?.querySelector('.shopping-quantity-control input')?.focus();
      throw new Error('Informe a quantidade realmente comprada de todos os produtos selecionados.');
    }

    const missing = selected.find((item) => !(Number(item.purchased_price || 0) > 0));
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
      items: selected.map((item) => {
        const numericId = Number(item.id);
        const isLocal = item.local_only || !Number.isFinite(numericId) || numericId <= 0;

        return {
          id: isLocal ? null : numericId,
          state_key: String(item.id),
          client_item_id: isLocal ? String(item.id) : '',
          name: String(item.name || ''),
          planned_quantity: Number(item.quantity || item.purchased_quantity || 1),
          estimated_price: Number(item.estimated_price || 0),
          purchased_quantity: Number(item.purchased_quantity || item.quantity || 0),
          purchased_price: Number(item.purchased_price || 0),
          store_name: String(item.store_name || '').trim(),
          track_inventory: item.track_inventory !== false
        };
      }),
      csrf_token: state.csrfToken || '',
      created_at: Date.now()
    };
  }

  async function queuePayload(payload) {
    await idbPut(OUTBOX_STORE, payload);

    payload.items.forEach((queued) => {
      const stateKey = String(queued.state_key || queued.client_item_id || queued.id);
      const item = state.items[stateKey];
      if (!item) return;
      item.selected = false;
      item.pending_sync = true;
      item.pending_purchase_id = payload.client_purchase_id;
      item.purchased_quantity = queued.purchased_quantity;
      item.track_inventory = queued.track_inventory !== false;
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
      const stateKey = String(synced.state_key || synced.client_item_id || synced.id);
      const item = state.items[stateKey];
      if (!item) return;
      item.selected = false;
      item.pending_sync = false;
      item.pending_purchase_id = null;
      item.purchased = true;
      item.purchased_quantity = synced.purchased_quantity;
      item.track_inventory = synced.track_inventory !== false;
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
      return sum + Number(selected.purchased_quantity || 0) * Number(selected.purchased_price || 0);
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

  async function reconcileBackgroundSync() {
    if (!state) return;

    const queued = await idbGetAll(OUTBOX_STORE);
    const queuedIds = new Set(queued.map((payload) => String(payload.client_purchase_id || '')));
    let changed = false;

    Object.values(state.items || {}).forEach((item) => {
      if (!item.pending_sync || !item.pending_purchase_id) return;

      if (!queuedIds.has(String(item.pending_purchase_id))) {
        item.pending_sync = false;
        item.pending_purchase_id = null;
        item.purchased = true;
        changed = true;
      }
    });

    if (changed) {
      await saveState();
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

  async function addOfflineProduct(event) {
    event.preventDefault();

    if (!state) return;

    const name = String(productName?.value || '').trim();
    const quantity = Number(productQuantity?.value || 0);
    const estimated = Number(productEstimated?.value || 0);

    if (!name || !(quantity > 0)) {
      alert('Informe o nome e uma quantidade válida.');
      return;
    }

    const id = 'local-' + uuid();
    state.items[id] = {
      id,
      local_only: true,
      name,
      quantity,
      purchased_quantity: quantity,
      estimated_price: estimated > 0 ? estimated : 0,
      track_inventory: productTrack?.value !== '0',
      selected: true,
      purchased_price: '',
      store_name: '',
      purchased: false,
      pending_sync: false,
      pending_purchase_id: null
    };

    await saveState();
    productForm?.reset();
    if (productQuantity) productQuantity.value = '1';
    if (productTrack) productTrack.value = '1';
    productDialog?.close();
    render();

    requestAnimationFrame(() => {
      const row = listNode.querySelector('[data-item-id="' + CSS.escape(id) + '"]');
      row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }

  function openProductDialog() {
    productForm?.reset();
    if (productQuantity) productQuantity.value = '1';
    if (productTrack) productTrack.value = '1';
    productDialog?.showModal();
    requestAnimationFrame(() => productName?.focus());
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

    Object.values(state.items || {}).forEach((item) => {
      if (!Object.prototype.hasOwnProperty.call(item, 'track_inventory')) {
        item.track_inventory = true;
      }
      if (!(Number(item.purchased_quantity) > 0)) {
        item.purchased_quantity = Number(item.quantity || 1);
      }
    });

    localStorage.setItem(ACTIVE_KEY, state.key);
    await reconcileBackgroundSync();
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

  addProductButton?.addEventListener('click', openProductDialog);
  productForm?.addEventListener('submit', addOfflineProduct);
  document.getElementById('offline-product-close')?.addEventListener('click', () => productDialog?.close());
  document.getElementById('offline-product-cancel')?.addEventListener('click', () => productDialog?.close());

  finalizeButton?.addEventListener('click', finalize);

  window.addEventListener('online', async () => {
    await updateSyncUi();
    await syncOutbox();
  });

  window.addEventListener('offline', updateSyncUi);

  init();
})();