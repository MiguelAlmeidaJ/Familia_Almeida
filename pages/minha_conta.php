<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/finance_scope.php';
$user=require_family_access(); $csrf=csrf_token(); $flash=pull_flash();
$pdo=db();$tenantId=(int)$user['tenant_id'];
$roleQuery=$pdo->prepare('SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?');$roleQuery->execute([$tenantId,(int)$user['id']]);$familyAdmin=$roleQuery->fetchColumn()==='family_admin';
$familyQuery=$pdo->prepare('SELECT name,finance_mode FROM tenants WHERE id=?');$familyQuery->execute([$tenantId]);$family=$familyQuery->fetch();
$financeReady=finance_separate_schema_ready($pdo);
$isSeparate=($family['finance_mode']??'shared')==='separate';
$members=[];$bills=[];$ownedRecords=[];$ownershipReady=true;
foreach(['transactions','fixed_bills','recurring_incomes'] as $ownershipTable){
 $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
 $q->execute([$ownershipTable]);if(!(int)$q->fetchColumn())$ownershipReady=false;
}
if($familyAdmin){
 $q=$pdo->prepare('SELECT u.id,u.name FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? ORDER BY u.name');$q->execute([$tenantId]);$members=$q->fetchAll();
 if($ownershipReady){
  foreach(['transaction'=>['transactions','description'],'bill'=>['fixed_bills','name'],'income'=>['recurring_incomes','name']] as $kind=>$meta){
   $isSeparate=($family['finance_mode']??'shared')==='separate';
   $q=$pdo->prepare("SELECT id, `{$meta[1]}` AS label, owner_user_id FROM `{$meta[0]}` WHERE tenant_id=?" . ($isSeparate?' AND (owner_user_id IS NULL OR owner_user_id=?)':'') . " ORDER BY id DESC LIMIT 30");
   $q->execute($isSeparate?[$tenantId,(int)$user['id']]:[$tenantId]);
   foreach($q->fetchAll() as $item)$ownedRecords[]=['type'=>$kind]+$item;
  }
 }
 $isSeparate=($family['finance_mode']??'shared')==='separate';
 $q=$pdo->prepare('SELECT b.id,b.name,r.user_id FROM fixed_bills b LEFT JOIN family_bill_responsibilities r ON r.fixed_bill_id=b.id AND r.tenant_id=b.tenant_id WHERE b.tenant_id=?' . ($isSeparate?' AND (b.owner_user_id IS NULL OR b.owner_user_id=?)':'') . ' ORDER BY b.name');
 $q->execute($isSeparate?[$tenantId,(int)$user['id']]:[$tenantId]);$bills=$q->fetchAll();
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configurações · Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/bemora.css?v=2">
<link rel="stylesheet" href="/assets/bemora-finance.css?v=2">
<link rel="stylesheet" href="/assets/bemora-finish.css?v=3">
<?php brand_head_tags(); ?>
<link rel="stylesheet" href="/assets/bemora-sidebar.css?v=6">
<link rel="stylesheet" href="/assets/bemora-family-settings.css?v=3">
</head>
<body class="bemora-finance"><div class="shell">
<?php render_sidebar('minha-conta',$csrf); ?>
<main><?php render_topbar($user); ?>
<div class="content bemora-family-settings">
<?php page_header('MINHA CONTA','Configurações','Personalize como sua família organiza e visualiza as finanças.'); ?>
<?php if($flash): ?>
<div class="family-settings-feedback <?= ($flash['type']??'')==='success'?'is-success':'is-error' ?>" role="<?= ($flash['type']??'')==='success'?'status':'alert' ?>">
<i class="bi <?= ($flash['type']??'')==='success'?'bi-check-circle-fill':'bi-exclamation-circle-fill' ?>" aria-hidden="true"></i>
<span><?= e($flash['message']) ?></span>
</div>
<?php endif; ?>

<section class="family-settings-intro">
<div class="family-settings-intro-icon"><i class="bi bi-house-heart" aria-hidden="true"></i></div>
<div class="family-settings-intro-copy">
<span class="family-settings-kicker">ESPAÇO FINANCEIRO</span>
<h2><?= e($family['name']??'Minha família') ?></h2>
<p>Defina como os membros organizam receitas, despesas e compromissos financeiros.</p>
</div>
<div class="family-settings-mode-pill"><span class="family-settings-dot"></span><?= $isSeparate?'Finanças individuais':'Finanças compartilhadas' ?></div>
</section>

<?php if($familyAdmin): ?>
<section class="family-settings-panel" aria-labelledby="finance-mode-heading">
<div class="family-settings-section-head">
<div><span class="family-settings-kicker">01 · PRIVACIDADE</span><h2 id="finance-mode-heading">Como vocês querem organizar as finanças?</h2><p>Escolha como os registros serão visualizados por quem participa da família.</p></div>
<span class="family-settings-admin-pill"><i class="bi bi-shield-lock" aria-hidden="true"></i> Administrador familiar</span>
</div>
<div class="finance-mode-options">
<article class="finance-mode-option <?= !$isSeparate?'selected':'' ?>">
<div class="finance-mode-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
<div class="finance-mode-copy">
<div class="finance-mode-title"><h3>Finanças compartilhadas</h3><?php if(!$isSeparate): ?><span class="finance-mode-current"><i class="bi bi-check-circle-fill"></i> Em uso</span><?php endif; ?></div>
<p>Receitas, despesas e contas da família ficam disponíveis para todos os membros.</p>
<div class="finance-mode-feature"><i class="bi bi-check2" aria-hidden="true"></i> Visão financeira conjunta</div>
<?php if($isSeparate): ?>
<form method="post" action="/pages/family_settings_action.php" class="finance-mode-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="action" value="finance_mode"><input type="hidden" name="finance_mode" value="shared">
<label class="finance-mode-confirm"><input type="checkbox" name="acknowledge_disclosure" value="yes" required> Entendo que registros pessoais também ficarão visíveis para todos os membros.</label>
<button type="submit" class="finance-mode-action secondary">Usar modo compartilhado <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>
<?php else: ?><span class="finance-mode-locked"><i class="bi bi-check2" aria-hidden="true"></i> Modo atual da família</span><?php endif; ?>
</div>
</article>
<article class="finance-mode-option <?= $isSeparate?'selected':(!$financeReady?'unavailable':'') ?>">
<div class="finance-mode-icon"><i class="bi bi-person-lock" aria-hidden="true"></i></div>
<div class="finance-mode-copy">
<div class="finance-mode-title"><h3>Finanças individuais</h3><?php if($isSeparate): ?><span class="finance-mode-current"><i class="bi bi-check-circle-fill"></i> Em uso</span><?php endif; ?></div>
<p>Cada pessoa vê os próprios registros, além daqueles definidos como compartilhados.</p>
<div class="finance-mode-feature"><i class="bi bi-check2" aria-hidden="true"></i> Privacidade por titular</div>
<?php if(!$isSeparate && $financeReady): ?>
<form method="post" action="/pages/family_settings_action.php" class="finance-mode-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="action" value="finance_mode"><input type="hidden" name="finance_mode" value="separate">
<label class="finance-mode-confirm"><input type="checkbox" name="acknowledge_legacy" value="yes" required> Entendo que registros sem titular continuam visíveis para todos.</label>
<button type="submit" class="finance-mode-action primary">Ativar finanças individuais <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>
<?php elseif($isSeparate): ?><span class="finance-mode-locked"><i class="bi bi-check2" aria-hidden="true"></i> Modo atual da família</span>
<?php else: ?><span class="finance-mode-locked"><i class="bi bi-clock-history" aria-hidden="true"></i> Configuração indisponível</span><?php endif; ?>
</div>
</article>
</div>
<div class="family-settings-tip <?= !$financeReady?'is-alert':'' ?>">
<i class="bi <?= !$financeReady?'bi-exclamation-triangle':'bi-info-circle' ?>" aria-hidden="true"></i>
<div>
<strong><?= !$financeReady?'Atualização do banco necessária':($isSeparate?'Sua privacidade está organizada por titular':'Antes de ativar o modo individual') ?></strong>
<p><?= !$financeReady?'Execute as migrations 015 e 016 em Manutenção para liberar a separação de finanças.':($isSeparate?'Os registros privados pertencem aos titulares. Aqueles sem titular continuam compartilhados.':'Defina os titulares dos registros abaixo. Qualquer lançamento sem titular continuará visível para todos os membros.') ?></p>
</div>
</div>
</section>

<section class="family-settings-panel" aria-labelledby="ownership-heading">
<div class="family-settings-section-head">
<div><span class="family-settings-kicker">02 · TITULARIDADE</span><h2 id="ownership-heading">De quem é cada registro?</h2><p>Escolha o titular das receitas, lançamentos e contas previstas. <strong>Compartilhado</strong> significa visível para todos no modo individual.</p></div>
<?php if($ownershipReady): ?><span class="family-settings-count"><?= count($ownedRecords) ?> registros recentes</span><?php endif; ?>
</div>
<?php if(!$ownershipReady): ?>
<div class="family-settings-empty"><i class="bi bi-database-exclamation"></i><strong>Estrutura de titularidade pendente</strong><p>Execute a migration 015 para atribuir registros.</p></div>
<?php elseif(!$ownedRecords): ?>
<div class="family-settings-empty"><i class="bi bi-inbox"></i><strong>Nenhum registro encontrado</strong><p>Seus lançamentos, receitas e contas previstas aparecerão aqui quando forem cadastrados.</p></div>
<?php else: ?>
<div class="family-settings-toolbar">
<div class="family-settings-filters" role="group" aria-label="Filtrar registros por tipo">
<button type="button" class="active" data-record-filter="all" aria-pressed="true">Todos <span><?= count($ownedRecords) ?></span></button>
<button type="button" data-record-filter="transaction" aria-pressed="false">Lançamentos</button>
<button type="button" data-record-filter="income" aria-pressed="false">Receitas</button>
<button type="button" data-record-filter="bill" aria-pressed="false">Contas previstas</button>
</div>
<div class="family-settings-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="family-record-search" placeholder="Buscar registro..." aria-label="Buscar registro"></div>
</div>
<div class="family-records" id="family-records">
<?php foreach($ownedRecords as $record): ?>
<form method="post" action="/pages/family_settings_action.php" class="family-settings-record" data-record-type="<?= e($record['type']) ?>" data-record-name="<?= e(mb_strtolower((string)$record['label'])) ?>">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="action" value="assign_financial_owner">
<input type="hidden" name="record_type" value="<?= e($record['type']) ?>">
<input type="hidden" name="record_id" value="<?= (int)$record['id'] ?>">
<div class="family-record-identity">
<span class="family-record-icon type-<?= e($record['type']) ?>"><i class="bi <?= ['transaction'=>'bi-arrow-left-right','bill'=>'bi-receipt','income'=>'bi-graph-up-arrow'][$record['type']] ?>" aria-hidden="true"></i></span>
<div><strong><?= e($record['label']) ?></strong><small><?= e(['transaction'=>'Lançamento','bill'=>'Conta prevista','income'=>'Receita recorrente'][$record['type']]) ?></small></div>
</div>
<label class="family-settings-select">Titular
<select name="owner_user_id" class="form-select">
<option value="0">Compartilhado com todos</option>
<?php foreach($members as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$record['owner_user_id']===(int)$member['id']?'selected':'' ?>><?= e($member['name']) ?></option><?php endforeach; ?>
</select>
</label>
<button class="family-settings-save" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Salvar</button>
</form>
<?php endforeach; ?>
</div>
<div class="family-settings-empty" id="family-record-no-results" hidden><i class="bi bi-search"></i><strong>Nenhum registro corresponde à busca</strong><p>Experimente outro termo ou tipo de registro.</p></div>
<p class="family-settings-footnote"><i class="bi bi-info-circle"></i> Exibimos até 30 registros recentes por tipo. As alterações são salvas individualmente.</p>
<?php endif; ?>
</section>

<section class="family-settings-panel" aria-labelledby="responsibility-heading">
<div class="family-settings-section-head">
<div><span class="family-settings-kicker">03 · ORGANIZAÇÃO</span><h2 id="responsibility-heading">Responsáveis pelas contas previstas</h2><p>Indique quem acompanha cada compromisso. A responsabilidade não altera quem pode visualizar o registro.</p></div>
<span class="family-settings-count"><?= count($bills) ?> contas</span>
</div>
<?php if(!$bills): ?>
<div class="family-settings-empty"><i class="bi bi-receipt-cutoff"></i><strong>Nenhuma conta prevista cadastrada</strong><p>Quando você criar uma conta prevista, será possível indicar uma pessoa responsável aqui.</p></div>
<?php else: ?>
<div class="family-records">
<?php foreach($bills as $bill): ?>
<form method="post" action="/pages/family_settings_action.php" class="family-settings-record">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="action" value="bill_responsibility">
<input type="hidden" name="bill_id" value="<?= (int)$bill['id'] ?>">
<div class="family-record-identity"><span class="family-record-icon type-bill"><i class="bi bi-calendar-check" aria-hidden="true"></i></span><div><strong><?= e($bill['name']) ?></strong><small>Conta prevista</small></div></div>
<label class="family-settings-select">Responsável
<select name="user_id" class="form-select">
<option value="0">Sem responsável individual</option>
<?php foreach($members as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$bill['user_id']===(int)$member['id']?'selected':'' ?>><?= e($member['name']) ?></option><?php endforeach; ?>
</select></label>
<button class="family-settings-save" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Salvar</button>
</form>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<?php else: ?>
<section class="family-settings-panel">
<div class="family-settings-section-head"><div><span class="family-settings-kicker">PREFERÊNCIAS DA FAMÍLIA</span><h2>Organização das finanças</h2><p>As configurações de privacidade e titularidade são administradas pelo responsável pela família.</p></div></div>
<div class="family-settings-tip"><i class="bi bi-shield-lock"></i><div><strong>Configurações disponíveis ao administrador familiar</strong><p>Você pode continuar gerenciando seus próprios lançamentos e consultar as informações autorizadas pelo modo financeiro atual.</p></div></div>
</section>
<?php endif; ?>
</div></main></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const filters=[...document.querySelectorAll('[data-record-filter]')];
  const input=document.getElementById('family-record-search');
  const rows=[...document.querySelectorAll('.family-settings-record[data-record-type]')];
  const empty=document.getElementById('family-record-no-results');
  let type='all';
  const render=()=>{
    const query=(input?.value||'').trim().toLocaleLowerCase('pt-BR');
    let visible=0;
    for(const row of rows){
      const matches=(type==='all'||row.dataset.recordType===type)&&((row.dataset.recordName||'').toLocaleLowerCase('pt-BR').includes(query));
      row.hidden=!matches;
      if(matches)visible++;
    }
    if(empty)empty.hidden=visible!==0;
  };
  for(const button of filters)button.addEventListener('click',()=>{
    type=button.dataset.recordFilter;
    for(const el of filters){const chosen=el===button;el.classList.toggle('active',chosen);el.setAttribute('aria-pressed',String(chosen))}
    render();
  });
  input?.addEventListener('input',render);
});
</script>
</body></html>
