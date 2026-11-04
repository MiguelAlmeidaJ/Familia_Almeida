<?php
declare(strict_types=1);
// Public landing page: no authentication required. Pricing is loaded read-only.
$publicPlans=[];$publicBenefits=[];$singleOffer=null;
try{
 require_once __DIR__.'/includes/bootstrap.php';
 require_once __DIR__.'/includes/plans.php';
 require_once __DIR__.'/includes/plan_modules.php';
 require_once __DIR__.'/includes/single_offer.php';
 require_once __DIR__.'/includes/plan_benefits.php';
 $pdo=db();
 $singleOffer=bemora_single_offer_enabled($pdo)?bemora_single_offer($pdo):null;
 if($singleOffer){$singleBenefits=bemora_plan_benefits($pdo,[bemora_single_offer_plan_id($singleOffer,'monthly')]);$singleBenefitList=$singleBenefits[bemora_single_offer_plan_id($singleOffer,'monthly')]??[];}
 if(!$singleOffer && bemora_plans_table_exists($pdo)){
  $publicPlans=$pdo->query('SELECT id,name,billing_cycle,details,max_members,price,promo_price,promo_starts_at,promo_ends_at FROM subscription_plans WHERE active=1 AND public_sale=1 ORDER BY FIELD(billing_cycle,\'monthly\',\'semiannual\',\'annual\'),price,id')->fetchAll(PDO::FETCH_ASSOC);
  $publicBenefits=bemora_plan_benefits($pdo,array_column($publicPlans,'id'));
 }
}catch(Throwable $e){error_log('Bemora landing: plan catalog unavailable.');}
?><!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Bemora: organize suas finanças pessoais, em casal ou em família. Lançamentos, contas previstas, relatórios e privacidade em um só lugar.">
<meta name="theme-color" content="#064e41">
<title>Bemora — Sua vida financeira mais leve</title>
<link rel="icon" href="/assets/brand/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/bemora-landing.css?v=11">
</head><body id="inicio">
<svg class="landing-icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" width="0" height="0">
 <defs>
  <symbol id="bemora-icon-sparkles" viewBox="0 0 24 24"><path d="m12 3 1.5 6.5L20 11l-6.5 1.5L12 19l-1.5-6.5L4 11l6.5-1.5L12 3Z"/><path d="m19 18 .6 1.4L21 20l-1.4.6L19 22l-.6-1.4L17 20l1.4-.6L19 18ZM4 2l.5 1.5L6 4l-1.5.5L4 6l-.5-1.5L2 4l1.5-.5L4 2Z"/></symbol>
  <symbol id="bemora-icon-wallet" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M3 10h18M6 6V4a2 2 0 0 1 2-2h10"/><path d="M17 15h1"/></symbol>
  <symbol id="bemora-icon-trending-up" viewBox="0 0 24 24"><path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/></symbol>
  <symbol id="bemora-icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18M8 15h3"/></symbol>
  <symbol id="bemora-icon-chart" viewBox="0 0 24 24"><path d="M3 20h18M6 16v-5m6 5V5m6 11V9"/><path d="M5 7 9 4l4 3 5-4"/></symbol>
  <symbol id="bemora-icon-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="bemora-icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21v-2a7.5 7.5 0 0 1 15 0v2"/></symbol>
  <symbol id="bemora-icon-heart" viewBox="0 0 24 24"><path d="M20.8 4.8a5.5 5.5 0 0 0-7.8 0l-1 1-1-1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"/></symbol>
  <symbol id="bemora-icon-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5v1"/></symbol>
  <symbol id="bemora-icon-check" viewBox="0 0 24 24"><path d="m5 12 4.5 4.5L19 7"/></symbol>
  <symbol id="bemora-icon-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 7 9-7"/></symbol>
  <symbol id="bemora-icon-message" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.4a9 9 0 0 1-.9-4.1 8.5 8.5 0 0 1 17 0Z"/><path d="M8 12h8"/></symbol>
  <symbol id="bemora-icon-sliders" viewBox="0 0 24 24"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="16" cy="17" r="2"/></symbol>
  <symbol id="bemora-icon-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
 </defs>
</svg>
<header class="site-header"><div class="site-container navigation">
<a class="site-brand" href="/" aria-label="Bemora, início"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a>
<nav class="desktop-nav" aria-label="Menu principal"><a href="#recursos">Funcionalidades</a><a href="#para-quem">Para quem é</a><a href="#como-funciona">Como funciona</a><a href="#planos">Planos</a><a href="#duvidas">Dúvidas</a></nav>
<div class="nav-actions"><a class="nav-login" href="/login">Entrar</a><a class="button button-dark nav-cta" href="#contato">Conhecer o Bemora <span aria-hidden="true">↗</span></a></div>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Abrir menu"><span></span><span></span><span></span></button>
</div><nav class="mobile-nav" id="mobile-nav" aria-label="Menu mobile" hidden><a href="#recursos">Funcionalidades</a><a href="#para-quem">Para quem é</a><a href="#como-funciona">Como funciona</a><a href="#duvidas">Dúvidas</a><a href="/login">Entrar na minha conta</a></nav></header>
<main>
<section class="hero"><div class="site-container hero-grid"><div class="hero-copy">
<span class="eyebrow"><span class="mini-star"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-sparkles"></use></svg></span> BEM-ESTAR FINANCEIRO, DO SEU JEITO</span>
<h1>Seu dinheiro em ordem.<br><em>Sua vida mais leve.</em></h1>
<p class="hero-description">Seu espaço para cuidar do que importa. Organize ganhos, acompanhe contas e entenda suas finanças — sozinho, a dois ou com toda a família.</p>
<div class="hero-buttons"><a href="#contato" class="button button-primary">Quero conhecer o Bemora <span>↗</span></a><a href="#recursos" class="button button-outline">Explorar recursos <span>↓</span></a></div>
<div class="hero-trust"><span class="trust-dots"><i></i><i></i><i></i></span><span>Mais clareza nas decisões de todos os dias.</span></div>
</div><div class="hero-art"><div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
<div class="mock-main"><div class="mock-top"><div class="mock-symbol"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-wallet"></use></svg></div><span>Visão financeira</span><span class="mock-top-date">Este mês <b>⌄</b></span></div><div class="mock-heading">Seu dinheiro, com mais clareza.</div><div class="mock-kpis"><div><small>Receitas previstas</small><strong>R$ 5.420,00</strong><span class="good">↗ Organizadas</span></div><div><small>Contas previstas</small><strong>R$ 2.180,00</strong><span>Próximos compromissos</span></div></div><div class="mock-chart-title">Distribuição financeira <span>•••</span></div><div class="mock-chart"><div class="mock-ring"></div><div class="mock-legend"><div><i class="dot deep"></i>Contas previstas <strong>40%</strong></div><div><i class="dot warm"></i>Gastos do mês <strong>18%</strong></div><div><i class="dot mint"></i>Disponível <strong>42%</strong></div></div></div><div class="mock-foot">Uma visão simples de tudo o que importa.</div></div>
<div class="float-card float-card-one"><span class="float-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-check"></use></svg></span><div><b>Contas organizadas</b><small>Mais tranquilidade para planejar</small></div></div>
<div class="float-card float-card-two"><span class="float-emoji"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-sparkles"></use></svg></span><div><b>Seu ritmo, suas escolhas</b><small>Individual ou em família</small></div></div>
</div></div></section>

<section id="recursos" class="section features"><div class="site-container"><div class="section-heading"><span class="eyebrow">TUDO EM UM SÓ LUGAR</span><h2>Menos planilhas.<br><em>Mais tempo para viver.</em></h2><p>As ferramentas de que você precisa para ter uma relação mais simples e consciente com seu dinheiro.</p></div>
<div class="feature-grid"><article class="feature-card"><span class="feature-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-trending-up"></use></svg></span><span class="feature-index">01 /</span><h3>Lançamentos sem complicação</h3><p>Registre seus ganhos e gastos em poucos passos. Acompanhe o movimento do mês com clareza.</p><div class="feature-lines"><i></i><i></i><i></i></div></article>
<article class="feature-card"><span class="feature-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-calendar"></use></svg></span><span class="feature-index">02 /</span><h3>Contas sob controle</h3><p>Organize vencimentos e acompanhe contas fixas, variáveis e parceladas, mês a mês.</p><div class="feature-dates"><b>05</b><b>15</b><b>25</b></div></article>
<article class="feature-card"><span class="feature-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-chart"></use></svg></span><span class="feature-index">03 /</span><h3>Relatórios que fazem sentido</h3><p>Entenda sua evolução financeira e compare períodos para planejar os próximos passos.</p><div class="feature-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article>
<article class="feature-card"><span class="feature-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-shield"></use></svg></span><span class="feature-index">04 /</span><h3>Seu espaço, sua privacidade</h3><p>Escolha entre organizar as finanças em conjunto ou manter informações individuais separadas.</p><div class="feature-pills"><span>Meu espaço</span><span>Família</span></div></article></div></div></section>
<section id="para-quem" class="section audience"><div class="site-container"><div class="section-heading"><span class="eyebrow">FEITO PARA A VIDA REAL</span><h2>Cada família é única.<br><em>O Bemora acompanha.</em></h2><p>Sozinho, a dois ou em família. O Bemora se adapta à forma como você organiza seu dinheiro.</p></div><div class="audience-grid"><article><div class="audience-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-user"></use></svg></div><h3>Para você</h3><p>Organize suas receitas, despesas e compromissos do jeito que funciona na sua rotina.</p></article><article><div class="audience-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-heart"></use></svg></div><h3>Para o casal</h3><p>Conversem sobre dinheiro com mais clareza e acompanhem as decisões da vida a dois.</p></article><article><div class="audience-icon"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-users"></use></svg></div><h3>Para a família</h3><p>Um lugar para organizar as finanças familiares e acompanhar as responsabilidades de cada pessoa.</p></article></div></div></section>
<section id="como-funciona" class="section steps"><div class="site-container steps-grid"><div><span class="eyebrow">SIMPLES DESDE O PRIMEIRO DIA</span><h2>Comece com o que você tem.<br><em>Construa com tranquilidade.</em></h2><p>Nada de sistemas complicados. O Bemora ajuda você a enxergar o presente e se preparar para o futuro.</p><a href="#contato" class="text-link">Quero começar <span>↗</span></a></div><div class="steps-list"><div><span>01</span><article><h3>Entre no seu espaço</h3><p>Acesse seu ambiente pessoal ou familiar com segurança.</p></article></div><div><span>02</span><article><h3>Organize sua rotina financeira</h3><p>Cadastre fontes de renda, contas previstas e movimentações.</p></article></div><div><span>03</span><article><h3>Acompanhe sua evolução</h3><p>Veja indicadores e relatórios para tomar decisões com mais confiança.</p></article></div></div></div></section>
<section class="section statement"><div class="site-container statement-inner"><span>✳</span><p>Organizar o dinheiro não precisa ser complicado. <em>Precisa fazer sentido para a sua vida.</em></p><small>BEMORA · BY ANOAR</small></div></section>
<?php if($singleOffer): ?>
<section id="planos" class="section landing-pricing single-public-pricing"><div class="site-container">
 <div class="single-pricing-heading"><div><span class="eyebrow">ASSINATURA BEMORA</span><h2>Seu dinheiro, <em>do seu jeito.</em></h2><p>Um acesso completo para começar. Personalize com mais pessoas e o módulo de Compras quando precisar.</p></div></div>
 <div class="single-public-layout">
  <article class="single-public-card">
   <div class="single-plan-identity"><span class="single-plan-symbol" aria-hidden="true"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-wallet"></use></svg></span><div><span class="single-plan-eyebrow">SEU PLANO</span><h3><?= e($singleOffer['name']) ?></h3></div><span class="single-plan-tag">1 pessoa inclusa</span></div>
   <p class="single-plan-description">Organização financeira simples para você e para quem compartilha a vida com você.</p>
   <?php if(!empty($singleBenefitList)): ?><ul class="landing-plan-features" aria-label="Benefícios incluídos"><?php foreach($singleBenefitList as $benefit): ?><li><?= e($benefit['title']) ?></li><?php endforeach; ?></ul><?php endif; ?>
   <div class="single-plan-footnote"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-plus"></use></svg> Acrescente integrantes e Compras quando quiser.</div>
  </article>
  <form class="single-public-builder" method="get" action="/checkout" id="single-public-builder"
   data-base="<?= e((string)$singleOffer['base_monthly']) ?>" data-seat="<?= e((string)$singleOffer['extra_member_monthly']) ?>"
   data-shop="<?= e((string)$singleOffer['shopping_monthly']) ?>" data-semi="<?= e((string)$singleOffer['semi_discount']) ?>"
   data-annual="<?= e((string)$singleOffer['annual_discount']) ?>">
   <div class="single-builder-heading"><div><span class="single-builder-kicker">PERSONALIZE SUA EXPERIÊNCIA</span><h3>Monte sua assinatura</h3></div><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-sliders"></use></svg></div>
   <div class="single-builder-fields">
    <label><span>Periodicidade</span><select name="ciclo"><option value="monthly">Mensal</option><option value="semiannual">Semestral</option><option value="annual">Anual</option></select></label>
    <label><span>Quantidade de pessoas</span><select name="integrantes"><?php for($n=1;$n<=(int)$singleOffer['max_members'];$n++): ?><option value="<?= $n ?>"><?= $n ?> <?= $n===1?'pessoa':'pessoas' ?></option><?php endfor; ?></select></label>
   </div>
   <label class="single-public-option"><input type="checkbox" name="compras" value="1"><span><strong>Compras + scanner</strong><small>Listas, modo mercado e leitura de etiquetas</small></span><span class="single-option-extra" aria-hidden="true">Adicional</span></label>
   <div class="single-public-bottom">
    <div class="single-public-total"><div><span>Total por período</span><strong id="single-public-amount"><?= e(bemora_plan_price((float)$singleOffer['base_monthly'])) ?></strong></div><small id="single-public-frequency">Cobrança mensal</small></div>
    <button type="submit" class="button button-dark landing-plan-cta">Continuar para assinatura <span class="landing-button-arrow" aria-hidden="true">→</span></button>
   </div>
  </form>
 </div>
 <p class="landing-plan-legal">Cobrança recorrente conforme a periodicidade escolhida. Valor final confirmado no checkout, com pagamento via Asaas.</p>
</div></section>
<?php elseif($publicPlans): ?>
<?php
$plansByCycle=['monthly'=>[],'semiannual'=>[],'annual'=>[]];
foreach($publicPlans as $offer){
 if(isset($plansByCycle[$offer['billing_cycle']]))$plansByCycle[$offer['billing_cycle']][]=$offer;
}
foreach($plansByCycle as &$offers)usort($offers,static fn($a,$b)=>(int)$a['max_members']<=>(int)$b['max_members'] ?: (float)$a['price']<=>(float)$b['price']);
unset($offers);
$initialCycle=array_key_first(array_filter($plansByCycle,static fn($offers)=>count($offers)>0))??'monthly';
?>
<section id="planos" class="section landing-pricing"><div class="site-container">
<div class="landing-pricing-heading">
 <div class="section-heading"><span class="eyebrow">PLANOS BEMORA</span><h2>Mais clareza para cuidar<br><em>do que realmente importa.</em></h2><p>Escolha quantas pessoas vão organizar as finanças com você. Planos simples, para cada fase da vida.</p></div>
 <div class="landing-pricing-benefit" aria-label="A organização acompanha você"><span aria-hidden="true">✳</span><p>Seu dinheiro, suas escolhas. Comece no seu ritmo e cresça com o Bemora.</p></div>
</div>
<div class="landing-billing-switch" role="tablist" aria-label="Periodicidade da assinatura">
<?php foreach(['monthly'=>'Mensal','semiannual'=>'Semestral','annual'=>'Anual'] as $cycleKey=>$cycleName): ?>
 <button type="button" role="tab" id="billing-tab-<?= $cycleKey ?>" aria-controls="billing-panel-<?= $cycleKey ?>" aria-selected="<?= $cycleKey===$initialCycle?'true':'false' ?>" tabindex="<?= $cycleKey===$initialCycle?'0':'-1' ?>" data-billing-tab="<?= $cycleKey ?>" <?= !$plansByCycle[$cycleKey]?'disabled':'' ?>><?= $cycleName ?></button>
<?php endforeach; ?>
</div>
<?php foreach(['monthly'=>'Mensal','semiannual'=>'Semestral','annual'=>'Anual'] as $cycleKey=>$cycleName): ?>
<div role="tabpanel" id="billing-panel-<?= $cycleKey ?>" aria-labelledby="billing-tab-<?= $cycleKey ?>" data-billing-panel="<?= $cycleKey ?>" <?= $cycleKey!==$initialCycle?'hidden':'' ?>>
<div class="landing-billing-caption"><span><?= $cycleKey==='monthly'?'Pagamento mensal, sem complicação.':($cycleKey==='semiannual'?'Uma cobrança a cada seis meses.':'Uma cobrança por ano.') ?></span><span><?= count($plansByCycle[$cycleKey]) ?> <?= count($plansByCycle[$cycleKey])===1?'opção':'opções' ?></span></div>
<div class="landing-pricing-grid">
<?php foreach($plansByCycle[$cycleKey] as $plan):
 $promoted=bemora_plan_promo_active($plan);
 $finalPrice=$promoted?(float)$plan['promo_price']:(float)$plan['price'];
 $planModules=bemora_plan_modules($pdo,(int)$plan['id']);
 $months=['monthly'=>1,'semiannual'=>6,'annual'=>12][$cycleKey];
 $highlight=(int)$plan['max_members']===4;
 $benefits=$publicBenefits[(int)$plan['id']]??[];
?>
<article class="landing-plan-card<?= $highlight?' landing-plan-featured':'' ?>">
 <div class="landing-plan-upper"><span class="landing-plan-cycle"><?= (int)$plan['max_members']===1?'Para você':((int)$plan['max_members']===2?'Para o casal':'Para a família') ?></span>
 <?php if($promoted): ?><span class="landing-plan-offer">Oferta especial</span><?php elseif($highlight): ?><span class="landing-plan-recommended">Para viver em família</span><?php endif; ?></div>
 <h3><?= htmlspecialchars($plan['name'],ENT_QUOTES,'UTF-8') ?></h3>
 <p class="landing-plan-tagline"><?= (int)$plan['max_members']===1?'Seu dinheiro organizado do seu jeito.':((int)$plan['max_members']===2?'Organização para construir juntos.':((int)$plan['max_members']<=4?'Mais clareza para a rotina da casa.':'Um espaço para famílias maiores.')) ?></p>
 <div class="landing-plan-pricing">
  <span class="landing-plan-price-label"><?= $cycleKey==='monthly'?'Valor mensal':'Valor por '.($cycleKey==='semiannual'?'semestre':'ano') ?></span>
  <div class="landing-plan-price"><?php if($promoted): ?><del><?= htmlspecialchars(bemora_plan_price((float)$plan['price']),ENT_QUOTES,'UTF-8') ?></del><?php endif; ?><strong><?= htmlspecialchars(bemora_plan_price($finalPrice),ENT_QUOTES,'UTF-8') ?></strong><span>/ <?= $cycleKey==='monthly'?'mês':($cycleKey==='semiannual'?'6 meses':'ano') ?></span></div>
  <?php if($months>1): ?><p class="landing-plan-equivalent">Equivale a <strong><?= htmlspecialchars(bemora_plan_price($finalPrice/$months),ENT_QUOTES,'UTF-8') ?>/mês</strong> · cobrado <?= $cycleKey==='semiannual'?'a cada 6 meses':'anualmente' ?></p><?php else: ?><p class="landing-plan-equivalent">Cobrança recorrente mensal</p><?php endif; ?>
 </div>
 <p class="landing-plan-members"><span aria-hidden="true">♧</span> Até <strong><?= (int)$plan['max_members'] ?> <?= (int)$plan['max_members']===1?'pessoa':'pessoas' ?></strong> no seu espaço</p>
 <?php if($benefits): ?>
 <ul class="landing-plan-features" aria-label="Benefícios incluídos">
  <?php foreach($benefits as $benefit): ?><li><?= htmlspecialchars($benefit['title'],ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?>
 </ul>
 <?php endif; ?>
 <?php if($promoted && !empty($plan['promo_ends_at'])): ?><p class="landing-plan-expires">Oferta válida até <?= htmlspecialchars(date('d/m/Y H:i',strtotime((string)$plan['promo_ends_at'])),ENT_QUOTES,'UTF-8') ?></p><?php endif; ?>
 <a class="button button-dark landing-plan-cta" href="/checkout?plano=<?= (int)$plan['id'] ?>">Escolher <?= htmlspecialchars($plan['name'],ENT_QUOTES,'UTF-8') ?> <span aria-hidden="true">↗</span></a>
</article>
<?php endforeach; ?>
</div></div>
<?php endforeach; ?>
<p class="landing-plan-legal">Os valores exibidos são por período de cobrança, com renovação recorrente conforme o plano escolhido. Pagamento intermediado pelo Asaas. Consulte as condições, promoções e documentos legais no checkout.</p>
</div></section>
<?php endif; ?>
<section id="duvidas" class="section faq"><div class="site-container faq-grid"><div><span class="eyebrow">AINDA TEM DÚVIDAS?</span><h2>Estamos aqui<br><em>para simplificar.</em></h2><p>Algumas respostas para começar com tranquilidade.</p></div><div class="faq-list"><details><summary>O Bemora é para uma pessoa ou para famílias?<span>+</span></summary><p>Para os dois! Você pode organizar suas finanças pessoais, em casal ou com a família, conforme o ambiente configurado.</p></details><details><summary>Posso separar minhas finanças das outras pessoas?<span>+</span></summary><p>Sim. O Bemora oferece opções para informações financeiras compartilhadas e individuais, conforme a configuração da sua família.</p></details><details><summary>Preciso instalar algum programa?<span>+</span></summary><p>Não. O Bemora funciona pelo navegador, no computador ou no celular.</p></details><details><summary>Como posso conhecer os planos?<span>+</span></summary><p>Entre em contato com nossa equipe comercial pelo WhatsApp para conhecer as condições de contratação disponíveis.</p></details></div></div></section>
<section id="contato" class="cta-section"><div class="site-container cta-box"><div class="cta-flower">✳</div><span class="eyebrow">COMECE UMA NOVA RELAÇÃO COM O SEU DINHEIRO</span><h2>Mais organização.<br><em>Mais leveza todos os dias.</em></h2><p>Converse com a nossa equipe e descubra como o Bemora pode fazer parte da sua rotina.</p><a class="button button-white" href="https://wa.me/5532984075039?text=Ol%C3%A1%21%20Quero%20conhecer%20o%20Bemora." target="_blank" rel="noopener noreferrer">Falar com o comercial <span>↗</span></a><small>Atendimento pelo WhatsApp · (32) 98407-5039</small></div></section>
</main>
<footer class="footer footer-premium" aria-label="Rodapé do Bemora">
<div class="site-container">
  <div class="footer-topline">
    <div><span class="footer-topline-kicker">BEMORA BY ANOAR</span><h2>Mais clareza para cuidar<br>do que importa.</h2></div>
    <a class="footer-contact-cta" href="https://wa.me/5532984075039?text=Ol%C3%A1%21%20Quero%20saber%20mais%20sobre%20o%20Bemora." target="_blank" rel="noopener noreferrer">Converse com nossa equipe <span aria-hidden="true">↗</span></a>
  </div>
  <div class="footer-main footer-premium-grid">
    <div class="footer-about">
      <a href="/" class="footer-brand" aria-label="Bemora, voltar ao início"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a>
      <p>Seu dinheiro em ordem.<br>Sua vida mais leve.</p>
      <div class="footer-about-note"><span class="footer-online-dot" aria-hidden="true"></span> Para você, para dois ou para toda a família.</div>
    </div>
    <nav class="footer-column" aria-label="Conheça o Bemora">
      <h3>Explore</h3>
      <a href="/#recursos">Funcionalidades</a>
      <a href="/#para-quem">Para quem é</a>
      <a href="/#como-funciona">Como funciona</a>
      <a href="/#planos">Planos e preços</a>
      <a href="/#duvidas">Dúvidas frequentes</a>
    </nav>
    <nav class="footer-column" aria-label="Conta e informações legais">
      <h3>Informações</h3>
      <a href="/login">Acessar minha conta</a>
      <a href="/termos">Termos de uso</a>
      <a href="/privacidade">Política de privacidade</a>
      <a href="/#contato">Atendimento</a>
    </nav>
    <div class="footer-column footer-contact">
      <h3>Fale com a gente</h3>
      <p>Tem dúvidas? Estamos aqui para ajudar.</p>
      <a href="https://wa.me/5532984075039" target="_blank" rel="noopener noreferrer"><span class="footer-contact-icon" aria-hidden="true"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-message"></use></svg></span><span>WhatsApp<small>(32) 98407-5039</small></span></a>
      <a href="mailto:bemora@anoar.com.br"><span class="footer-contact-icon" aria-hidden="true"><svg class="landing-icon" aria-hidden="true" focusable="false"><use href="#bemora-icon-mail"></use></svg></span><span>E-mail<small>bemora@anoar.com.br</small></span></a>
    </div>
  </div>
  <div class="footer-bottom footer-premium-bottom">
    <span>© <?= date('Y') ?> Bemora. Todos os direitos reservados.</span>
    <span class="footer-made-by">Um produto <a href="https://anoar.com.br" target="_blank" rel="noopener noreferrer">Anoar</a><span class="footer-brand-heart" aria-hidden="true">✳</span></span>
  </div>
</div>
</footer>
<script src="/assets/bemora-single-public.js?v=1" defer></script><script src="/assets/bemora-landing.js?v=2" defer></script></body></html>