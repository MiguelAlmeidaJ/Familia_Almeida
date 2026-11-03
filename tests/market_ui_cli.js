// Structural safeguards for the market purchase workflow (no browser required).
const assert=require('node:assert/strict');
const fs=require('node:fs');
const page=fs.readFileSync('pages/market_lists.php','utf8');
const action=fs.readFileSync('market_action.php','utf8');
const popups=fs.readFileSync('assets/bemora-market-popups.js','utf8');
const scanner=fs.readFileSync('assets/bemora-market.js','utf8');
for(const id of ['market-product-dialog','market-scan-dialog','market-finish-dialog','market-open-camera','market-open-finish','market-product-save-close','market-product-save-continue','market-finish-form'])assert.ok(page.includes('id="'+id+'"'),'Required element: '+id);
assert.ok(page.includes('action" value="finish_purchase"'),'Finish action must be explicitly posted');
assert.ok(page.indexOf('<form class="market-add"')<0,'Planning form must not be displayed inline');
assert.ok(!page.includes('id="market-photo"'),'File upload shortcut removed from market page');
assert.ok(!page.includes('market-basket-shortcut'),'Obsolete basket shortcut removed');
assert.ok(action.includes("if($action==='finish_purchase')"),'Financial action must exist on server');
assert.ok(action.includes('transaction_id'), 'Transaction link prevents duplicate expense creation');
assert.ok(action.includes('$pdo->beginTransaction()'), 'Atomic database operation');
assert.ok(action.includes('FOR UPDATE'), 'Lock each list while finalizing');
assert.ok(popups.includes('Salvar')||page.includes('Salvar e continuar adicionando'),'Save and add another supported');
assert.ok(scanner.includes("scanDialog?.addEventListener('close',stopCamera)"),'Camera stops on modal close');
console.log('[PASS] Product popup, camera popup, protected financial finalization and removed upload UI');
