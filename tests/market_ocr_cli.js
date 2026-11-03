// Validate the exact browser OCR parser without requiring a camera or network.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/bemora-market.js','utf8');
const start=source.indexOf('function parseLabel(text){');
const end=source.indexOf('function setupOutput(source){',start);
assert.ok(start>=0 && end>start,'OCR parser must be present');
const context={normalizeName:s=>s.replace(/\s+/g,' ').trim().slice(0,160)};
vm.createContext(context);
vm.runInContext(source.slice(start,end),context);
const sample=['ARROZ TIPO1 CAMIL 5KG FD6','Varejo','R$ 12,49','Atacado','R$ 9,99','LEVE + PAGUE','R$ 0,09'].join('\n');
const result=context.parseLabel(sample);
assert.match(result.product,/ARROZ TIPO1 CAMIL 5KG/i);
assert.equal(result.options.length,2,'retail and wholesale both require manual review');
const prices=result.options.map(x=>x.amount).sort((a,b)=>a-b);
assert.equal(prices[0],9.99);assert.equal(prices[1],12.49);
assert.equal(context.parseLabel('Produto sem preco\nCodigo 789012345').options.length,0);
console.log('[PASS] Label parser identifies product, rejects 0,09 and preserves separate prices');

const layouts=['wide','medium','square','tall'];
for(const layout of layouts){
 assert.match(source,new RegExp('\\b'+layout+'\\s*:\\s*\\{'),'camera crop preset '+layout+' exists');
}
assert.match(source,/getBoundingClientRect\(\)/,'camera crop must use visible guide measurements');
assert.match(source,/topBand=cropFrom/,'product name must be read separately from price area');
assert.equal(context.betterName('ARROZ TIPO1 CAMIL 5KG FD6','ROZ TIPO1'), 'ARROZ TIPO1 CAMIL 5KG FD6');
console.log('[PASS] Camera formats, guide crop and separate product-name recognition present');
