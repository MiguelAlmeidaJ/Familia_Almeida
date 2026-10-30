<?php
declare(strict_types=1);
// Public landing page: no authentication required. Pricing is loaded read-only.
$publicPlans=[];
try{
 require_once __DIR__.'/includes/bootstrap.php';
 require_once __DIR__.'/includes/plans.php';
 $pdo=db();
 if(bemora_plans_table_exists($pdo)){
  $publicPlans=$pdo->query('SELECT id,name,billing_cycle,details,max_members,price,promo_price,promo_starts_at,promo_ends_at FROM subscription_plans WHERE active=1 ORDER BY FIELD(billing_cycle,\'monthly\',\'semiannual\',\'annual\'),price,id')->fetchAll(PDO::FETCH_ASSOC);
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
<link rel="stylesheet" href="/assets/bemora-landing.css?v=3">
</head><body id="inicio">
<header class="site-header"><div class="site-container navigation">
<a class="site-brand" href="/" aria-label="Bemora, início"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a>
<nav class="desktop-nav" aria-label="Menu principal"><a href="#recursos">Funcionalidades</a><a href="#para-quem">Para quem é</a><a href="#como-funciona">Como funciona</a><a href="#planos">Planos</a><a href="#duvidas">Dúvidas</a></nav>
<div class="nav-actions"><a class="nav-login" href="/login">Entrar</a><a class="button button-dark nav-cta" href="#contato">Conhecer o Bemora <span aria-hidden="true">↗</span></a></div>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Abrir menu"><span></span><span></span><span></span></button>
</div><nav class="mobile-nav" id="mobile-nav" aria-label="Menu mobile" hidden><a href="#recursos">Funcionalidades</a><a href="#para-quem">Para quem é</a><a href="#como-funciona">Como funciona</a><a href="#duvidas">Dúvidas</a><a href="/login">Entrar na minha conta</a></nav></header>
<main>
<section class="hero"><div class="site-container hero-grid"><div class="hero-copy">
<span class="eyebrow"><span class="mini-star">✳</span> BEM-ESTAR FINANCEIRO, DO SEU JEITO</span>
<h1>Seu dinheiro em ordem.<br><em>Sua vida mais leve.</em></h1>
<p class="hero-description">Seu espaço para cuidar do que importa. Organize ganhos, acompanhe contas e entenda suas finanças — sozinho, a dois ou com toda a família.</p>
<div class="hero-buttons"><a href="#contato" class="button button-primary">Quero conhecer o Bemora <span>↗</span></a><a href="#recursos" class="button button-outline">Explorar recursos <span>↓</span></a></div>
<div class="hero-trust"><span class="trust-dots"><i></i><i></i><i></i></span><span>Mais clareza nas decisões de todos os dias.</span></div>
</div><div class="hero-art"><div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
<div class="mock-main"><div class="mock-top"><div class="mock-symbol">◫</div><span>Visão financeira</span><span class="mock-top-date">Este mês <b>⌄</b></span></div><div class="mock-heading">Seu dinheiro, com mais clareza.</div><div class="mock-kpis"><div><small>Receitas previstas</small><strong>R$ 5.420,00</strong><span class="good">↗ Organizadas</span></div><div><small>Contas previstas</small><strong>R$ 2.180,00</strong><span>Próximos compromissos</span></div></div><div class="mock-chart-title">Distribuição financeira <span>•••</span></div><div class="mock-chart"><div class="mock-ring"></div><div class="mock-legend"><div><i class="dot deep"></i>Contas previstas <strong>40%</strong></div><div><i class="dot warm"></i>Gastos do mês <strong>18%</strong></div><div><i class="dot mint"></i>Disponível <strong>42%</strong></div></div></div><div class="mock-foot">Uma visão simples de tudo o que importa.</div></div>
<div class="float-card float-card-one"><span class="float-icon">✓</span><div><b>Contas organizadas</b><small>Mais tranquilidade para planejar</small></div></div>
<div class="float-card float-card-two"><span class="float-emoji">✧</span><div><b>Seu ritmo, suas escolhas</b><small>Individual ou em família</small></div></div>
</div></div></section>
<section class="strip"><div class="site-container strip-inner"><span>UM JEITO MAIS LEVE DE CUIDAR DAS FINANÇAS</span><div><b>Individual</b><i></i><b>Casal</b><i></i><b>Família</b></div></div></section>
<section id="recursos" class="section features"><div class="site-container"><div class="section-heading"><span class="eyebrow">TUDO EM UM SÓ LUGAR</span><h2>Menos planilhas.<br><em>Mais tempo para viver.</em></h2><p>As ferramentas de que você precisa para ter uma relação mais simples e consciente com seu dinheiro.</p></div>
<div class="feature-grid"><article class="feature-card"><span class="feature-icon">↗</span><span class="feature-index">01 /</span><h3>Lançamentos sem complicação</h3><p>Registre seus ganhos e gastos em poucos passos. Acompanhe o movimento do mês com clareza.</p><div class="feature-lines"><i></i><i></i><i></i></div></article>
<article class="feature-card"><span class="feature-icon">▤</span><span class="feature-index">02 /</span><h3>Contas sob controle</h3><p>Organize vencimentos e acompanhe contas fixas, variáveis e parceladas, mês a mês.</p><div class="feature-dates"><b>05</b><b>15</b><b>25</b></div></article>
<article class="feature-card"><span class="feature-icon">◔</span><span class="feature-index">03 /</span><h3>Relatórios que fazem sentido</h3><p>Entenda sua evolução financeira e compare períodos para planejar os próximos passos.</p><div class="feature-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article>
<article class="feature-card"><span class="feature-icon">♧</span><span class="feature-index">04 /</span><h3>Seu espaço, sua privacidade</h3><p>Escolha entre organizar as finanças em conjunto ou manter informações individuais separadas.</p><div class="feature-pills"><span>Meu espaço</span><span>Família</span></div></article></div></div></section>
<section id="para-quem" class="section audience"><div class="site-container"><div class="section-heading"><span class="eyebrow">FEITO PARA A VIDA REAL</span><h2>Cada família é única.<br><em>O Bemora acompanha.</em></h2></div><div class="audience-grid"><article><span>01</span><div class="audience-icon">◡</div><h3>Para você</h3><p>Organize suas receitas, despesas e compromissos do jeito que funciona na sua rotina.</p></article><article><span>02</span><div class="audience-icon">♡</div><h3>Para o casal</h3><p>Conversem sobre dinheiro com mais clareza e acompanhem as decisões da vida a dois.</p></article><article><span>03</span><div class="audience-icon">♧</div><h3>Para a família</h3><p>Um lugar para organizar as finanças familiares e acompanhar as responsabilidades de cada pessoa.</p></article></div></div></section>
<section id="como-funciona" class="section steps"><div class="site-container steps-grid"><div><span class="eyebrow">SIMPLES DESDE O PRIMEIRO DIA</span><h2>Comece com o que você tem.<br><em>Construa com tranquilidade.</em></h2><p>Nada de sistemas complicados. O Bemora ajuda você a enxergar o presente e se preparar para o futuro.</p><a href="#contato" class="text-link">Quero começar <span>↗</span></a></div><div class="steps-list"><div><span>01</span><article><h3>Entre no seu espaço</h3><p>Acesse seu ambiente pessoal ou familiar com segurança.</p></article></div><div><span>02</span><article><h3>Organize sua rotina financeira</h3><p>Cadastre fontes de renda, contas previstas e movimentações.</p></article></div><div><span>03</span><article><h3>Acompanhe sua evolução</h3><p>Veja indicadores e relatórios para tomar decisões com mais confiança.</p></article></div></div></div></section>
<section class="section statement"><div class="site-container statement-inner"><span>✳</span><p>Organizar o dinheiro não precisa ser complicado. <em>Precisa fazer sentido para a sua vida.</em></p><small>BEMORA · BY ANOAR</small></div></section>
<?php if($publicPlans): ?>
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
<div class="landing-pricing-heading"><div class="section-heading"><span class="eyebrow">PLANOS BEMORA</span><h2>Um plano para<br><em>cada momento da vida.</em></h2><p>Do seu espaço individual à família toda. Escolha a modalidade que combina com você.</p></div>
<div class="landing-pricing-benefit"><span aria-hidden="true">✳</span><p>Organização financeira com a liberdade de escolher como e com quem cuidar do seu dinheiro.</p></div></div>
<div class="landing-billing-switch" role="tablist" aria-label="Modalidade de contratação">
<?php foreach(['monthly'=>'Mensal','semiannual'=>'Semestral','annual'=>'Anual'] as $cycleKey=>$cycleName): ?>
<button type="button" role="tab" id="billing-tab-<?= $cycleKey ?>" aria-controls="billing-panel-<?= $cycleKey ?>" aria-selected="<?= $cycleKey===$initialCycle?'true':'false' ?>" tabindex="<?= $cycleKey===$initialCycle?'0':'-1' ?>" data-billing-tab="<?= $cycleKey ?>" <?= !$plansByCycle[$cycleKey]?'disabled':'' ?>><?= $cycleName ?></button>
<?php endforeach; ?>
</div>
<?php foreach(['monthly'=>'Mensal','semiannual'=>'Semestral','annual'=>'Anual'] as $cycleKey=>$cycleName): ?>
<div role="tabpanel" id="billing-panel-<?= $cycleKey ?>" aria-labelledby="billing-tab-<?= $cycleKey ?>" data-billing-panel="<?= $cycleKey ?>" <?= $cycleKey!==$initialCycle?'hidden':'' ?>>
<div class="landing-billing-caption"><span><?= $cycleKey==='monthly'?'Flexibilidade para organizar cada mês.':($cycleKey==='semiannual'?'Seis meses para acompanhar sua evolução.':'Um ano para viver com mais tranquilidade.') ?></span><span><?= count($plansByCycle[$cycleKey]) ?> <?= count($plansByCycle[$cycleKey])===1?'opção disponível':'opções disponíveis' ?></span></div>
<div class="landing-pricing-grid">
<?php foreach($plansByCycle[$cycleKey] as $plan): $promoted=bemora_plan_promo_active($plan);$finalPrice=$promoted?(float)$plan['promo_price']:(float)$plan['price']; ?>
<article class="landing-plan-card">
<div class="landing-plan-upper"><span class="landing-plan-cycle"><?= (int)$plan['max_members']===1?'Para você':((int)$plan['max_members']===2?'Para o casal':'Para a família') ?></span><?php if($promoted): ?><span class="landing-plan-offer">Oferta especial</span><?php endif; ?></div>
<h3><?= htmlspecialchars($plan['name'],ENT_QUOTES,'UTF-8') ?></h3>
<div class="landing-plan-price"><?php if($promoted): ?><del><?= htmlspecialchars(bemora_plan_price((float)$plan['price']),ENT_QUOTES,'UTF-8') ?></del><?php endif; ?><strong><?= htmlspecialchars(bemora_plan_price($finalPrice),ENT_QUOTES,'UTF-8') ?></strong><span>/ <?= htmlspecialchars(strtolower($cycleName),ENT_QUOTES,'UTF-8') ?></span></div>
<p class="landing-plan-members"><span aria-hidden="true">♧</span> Até <?= (int)$plan['max_members'] ?> <?= (int)$plan['max_members']===1?'pessoa':'pessoas' ?></p>
<div class="landing-plan-details"><?= nl2br(htmlspecialchars($plan['details'],ENT_QUOTES,'UTF-8')) ?></div>
<?php if($promoted): ?><p class="landing-plan-expires">Oferta válida até <?= htmlspecialchars(date('d/m/Y H:i',strtotime((string)$plan['promo_ends_at'])),ENT_QUOTES,'UTF-8') ?></p><?php endif; ?>
<a class="button button-dark landing-plan-cta" href="/checkout?plano=<?= (int)$plan['id'] ?>">Escolher este plano <span aria-hidden="true">↗</span></a>
</article>
<?php endforeach; ?>
</div></div>
<?php endforeach; ?>
<p class="landing-plan-legal">Valores por período contratado. Atendimento e contratação pelo WhatsApp comercial. O pagamento não é processado automaticamente pelo site.</p>
</div></section>
<?php endif; ?>
<section id="duvidas" class="section faq"><div class="site-container faq-grid"><div><span class="eyebrow">AINDA TEM DÚVIDAS?</span><h2>Estamos aqui<br><em>para simplificar.</em></h2><p>Algumas respostas para começar com tranquilidade.</p></div><div class="faq-list"><details><summary>O Bemora é para uma pessoa ou para famílias?<span>+</span></summary><p>Para os dois! Você pode organizar suas finanças pessoais, em casal ou com a família, conforme o ambiente configurado.</p></details><details><summary>Posso separar minhas finanças das outras pessoas?<span>+</span></summary><p>Sim. O Bemora oferece opções para informações financeiras compartilhadas e individuais, conforme a configuração da sua família.</p></details><details><summary>Preciso instalar algum programa?<span>+</span></summary><p>Não. O Bemora funciona pelo navegador, no computador ou no celular.</p></details><details><summary>Como posso conhecer os planos?<span>+</span></summary><p>Entre em contato com nossa equipe comercial pelo WhatsApp para conhecer as condições de contratação disponíveis.</p></details></div></div></section>
<section id="contato" class="cta-section"><div class="site-container cta-box"><div class="cta-flower">✳</div><span class="eyebrow">COMECE UMA NOVA RELAÇÃO COM O SEU DINHEIRO</span><h2>Mais organização.<br><em>Mais leveza todos os dias.</em></h2><p>Converse com a nossa equipe e descubra como o Bemora pode fazer parte da sua rotina.</p><a class="button button-white" href="https://wa.me/5532984075039?text=Ol%C3%A1%21%20Quero%20conhecer%20o%20Bemora." target="_blank" rel="noopener noreferrer">Falar com o comercial <span>↗</span></a><small>Atendimento pelo WhatsApp · (32) 98407-5039</small></div></section>
</main>
<footer class="footer"><div class="site-container footer-main"><div><a href="/"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a><p>Seu dinheiro em ordem.<br>Sua vida mais leve.</p></div><div><strong>Navegação</strong><a href="#recursos">Funcionalidades</a><a href="#para-quem">Para quem é</a><a href="#como-funciona">Como funciona</a><a href="#planos">Planos</a><a href="/login">Acessar conta</a></div><div><strong>Fale com a gente</strong><a href="https://wa.me/5532984075039" target="_blank" rel="noopener noreferrer">WhatsApp: (32) 98407-5039</a><a href="mailto:bemora@anoar.com.br">bemora@anoar.com.br</a></div></div><div class="site-container footer-bottom"><span>© <?= date('Y') ?> Bemora. Todos os direitos reservados.</span><span>Um produto <a href="https://anoar.com.br" target="_blank" rel="noopener noreferrer">Anoar</a> ♡</span></div></footer>
<script src="/assets/bemora-landing.js?v=2" defer></script></body></html>