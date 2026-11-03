<?php
declare(strict_types=1);
require_once __DIR__.'/includes/legal.php';
$approved=bemora_legal_approved();
$path=rtrim((string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??''),'/');
$privacy=$path==='/privacidade';
$version=$privacy?BEMORA_PRIVACY_VERSION:BEMORA_TERMS_VERSION;
$title=$privacy?'Política de Privacidade':'Termos de Uso e Contratação';
$company=bemora_legal_label('company_name','Dados empresariais aguardando confirmação');
$taxId=bemora_legal_label('company_tax_id','CNPJ pendente');
$address=bemora_legal_label('company_address','Endereço comercial pendente');
$support=trim((string)config('legal','support_email',''));
$privacyEmail=trim((string)config('legal','privacy_email',''));
$contactHtml=static function(string $email):string {
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return '<span>Canal em configuração</span>';
    $safe=e($email);
    return '<a href="mailto:'.$safe.'">'.$safe.'</a>';
};
?><!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="<?= $approved?'index,follow':'noindex,nofollow' ?>">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="description" content="Consulte <?= e($title) ?> do Bemora, serviço de organização financeira pessoal e familiar.">
<title><?= e($title) ?> · Bemora</title>
<link rel="icon" href="/assets/brand/favicon.png"><link rel="stylesheet" href="/assets/bemora-legal.css?v=2">
</head><body>
<a class="legal-skip" href="#legal-content">Ir para o conteúdo</a>
<header class="legal-header">
<a href="/" class="legal-logo" aria-label="Bemora - início"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a>
<nav aria-label="Documentos e navegação">
<a href="/termos" <?= !$privacy?'aria-current="page"':'' ?>>Termos de uso</a>
<a href="/privacidade" <?= $privacy?'aria-current="page"':'' ?>>Privacidade</a>
<a href="/">Voltar ao Bemora</a></nav>
</header>
<main id="legal-content" class="legal-main">
<div class="legal-hero"><span class="legal-kicker"><span class="legal-dot"></span> TRANSPARÊNCIA BEMORA</span>
<h1><?= e($title) ?></h1>
<p>Informações sobre o uso do Bemora, seus dados e os seus direitos.</p>
<div class="legal-meta"><span><i aria-hidden="true">◷</i> Versão <?= e($version) ?></span><span><i aria-hidden="true">✓</i> <?= $approved?'Documento publicado':'Em revisão' ?></span></div>
</div>
<?php if(!$approved): ?>
<div class="legal-draft" role="status"><strong>Minuta aguardando aprovação.</strong>
<p>O texto abaixo foi preparado como base de publicação, mas os dados do fornecedor e a revisão jurídica ainda precisam ser confirmados. Ele não substitui a aprovação formal das condições de contratação.</p></div>
<?php endif; ?>
<div class="legal-columns">
<aside class="legal-index" aria-label="Índice do documento">
<strong>Nesta página</strong>
<?php if($privacy): ?>
<a href="#dados">Dados tratados</a><a href="#finalidades">Finalidades e bases legais</a><a href="#compartilhamento">Compartilhamento</a><a href="#familia">Privacidade da família</a><a href="#seguranca">Segurança</a><a href="#retencao">Conservação</a><a href="#direitos">Seus direitos</a><a href="#contato">Contato</a>
<?php else: ?>
<a href="#fornecedor">Quem oferece</a><a href="#servico">Sobre o serviço</a><a href="#cadastro">Cadastro e responsabilidades</a><a href="#planos">Planos e cobranças</a><a href="#cancelamento">Cancelamento</a><a href="#arrependimento">Arrependimento</a><a href="#dados-servico">Dados e disponibilidade</a><a href="#contato">Atendimento</a>
<?php endif; ?>
</aside>
<div class="legal-body">
<?php if($privacy): ?>
<section class="legal-section" id="dados"><span class="legal-number">01</span><h2>Quais dados utilizamos?</h2>
<p>O Bemora trata dados de identificação e cadastro, como nome, e-mail, telefone, dados de perfil e de membros da família. Para contratar, podemos tratar CPF/CNPJ do pagador, informações do plano, cobranças e respectivos estados.</p>
<p>Também tratamos os dados financeiros que os próprios usuários registram (receitas, despesas, contas previstas, categorias e comprovantes), chamados de suporte, solicitações de privacidade e registros técnicos necessários para autenticação, prevenção de abuso e manutenção do sistema.</p>
<p>Não solicitamos os dados completos de cartão de crédito no formulário do Bemora. Quando aplicável, a coleta e o processamento de cartões ocorrem no provedor de pagamentos.</p></section>
<section class="legal-section" id="finalidades"><span class="legal-number">02</span><h2>Finalidades e fundamentos do tratamento</h2>
<p>Usamos as informações necessárias para oferecer a plataforma contratada, administrar acessos e permissões, registrar informações inseridas pelos usuários, processar pagamentos, atender solicitações e manter a segurança da operação.</p>
<p>Dependendo da operação, o tratamento poderá decorrer da execução de contrato, do cumprimento de obrigação legal ou regulatória, de interesses legítimos avaliados nos limites da LGPD ou de outra hipótese legal adequada. O uso da plataforma não é apresentado como autorização irrestrita para tratar dados para qualquer finalidade.</p>
<p>As bases legais por categoria devem corresponder ao inventário interno de tratamento aprovado pelo fornecedor.</p></section>
<section class="legal-section" id="compartilhamento"><span class="legal-number">03</span><h2>Com quem os dados podem ser compartilhados?</h2>
<p>As informações necessárias à prestação do serviço podem ser tratadas por fornecedores de hospedagem, envio de e-mails, manutenção técnica e pelo <strong>Asaas</strong>, responsável pela intermediação das cobranças. O compartilhamento deve ser limitado às finalidades pertinentes e sujeito aos controles e contratos cabíveis.</p>
<p>Alguns serviços técnicos podem envolver tratamento de dados em outras localidades. Eventuais transferências internacionais precisam observar os mecanismos legais aplicáveis e ser detalhadas quando confirmadas no inventário de fornecedores.</p></section>
<section class="legal-section" id="familia"><span class="legal-number">04</span><h2>Como funciona a privacidade entre familiares?</h2>
<p>O Bemora oferece modos de finanças compartilhadas e individuais. No modo compartilhado, os registros da família ficam visíveis aos membros autorizados. No modo individual, registros atribuídos a um titular ficam restritos conforme as permissões do sistema; itens sem titular ou marcados como compartilhados podem continuar visíveis para os demais integrantes.</p>
<p>O administrador familiar gerencia os membros e o modo financeiro. Antes de compartilhar dados de outras pessoas, respeite a privacidade e as regras de acesso de cada integrante.</p></section>
<section class="legal-section" id="seguranca"><span class="legal-number">05</span><h2>Medidas de segurança</h2>
<p>Adotamos controles de autenticação, gerenciamento de sessões, permissões por família e titular, proteção de acesso a arquivos privados, rotinas de backup e conciliação operacional. A segurança é revisada continuamente e nenhuma solução elimina todos os riscos.</p>
<p>Se identificar acesso indevido ou possível incidente envolvendo dados pessoais, procure imediatamente o canal de privacidade descrito abaixo.</p></section>
<section class="legal-section" id="retencao"><span class="legal-number">06</span><h2>Por quanto tempo conservamos os dados?</h2>
<p>Conservamos dados enquanto forem necessários à relação contratual, ao funcionamento solicitado da conta, ao suporte e ao cumprimento de obrigações legais e fiscais, observando necessidade e proporcionalidade.</p>
<p>Os prazos específicos por tipo de dado, inclusive retenção em backups, devem ser formalizados no inventário de tratamento. Solicitações de exclusão serão avaliadas considerando obrigações de guarda, exercício regular de direitos e informações de terceiros. O cancelamento da assinatura não apaga automaticamente os lançamentos financeiros.</p></section>
<section class="legal-section" id="direitos"><span class="legal-number">07</span><h2>Seus direitos como titular</h2>
<p>Você pode solicitar confirmação de tratamento, acesso, correção de dados inexatos, informações sobre compartilhamento, portabilidade, oposição e eliminação quando cabível, além dos demais direitos previstos na LGPD.</p>
<p>Para proteger a sua identidade e a de outros integrantes, poderemos solicitar verificação adicional antes de fornecer ou modificar informações. Alguns pedidos poderão ser atendidos parcialmente ou não poderão resultar em eliminação imediata, com justificativa.</p>
<p>Se estiver autenticado, utilize a <a href="/meus-dados">Central de Privacidade e Meus Dados</a>. Caso não tenha acesso à conta, utilize o e-mail de privacidade abaixo.</p></section>
<?php else: ?>
<section class="legal-section" id="fornecedor"><span class="legal-number">01</span><h2>Quem oferece o Bemora?</h2>
<p>O Bemora é um produto digital da Anoar, comercializado pelo fornecedor identificado ao final deste documento, que deve corresponder à razão social e ao CNPJ da empresa responsável pela contratação.</p></section>
<section class="legal-section" id="servico"><span class="legal-number">02</span><h2>O que é o Bemora?</h2>
<p>O Bemora é uma plataforma de organização financeira para uso pessoal, em casal ou em família. O serviço permite registrar movimentações, acompanhar contas previstas e consultar informações e relatórios.</p>
<p>A plataforma não é instituição financeira, não executa transferências bancárias nem oferece aconselhamento personalizado de investimentos. As informações cadastradas e as decisões financeiras são de responsabilidade dos respectivos usuários.</p></section>
<section class="legal-section" id="cadastro"><span class="legal-number">03</span><h2>Conta, membros e permissões</h2>
<p>É necessário fornecer informações verdadeiras, manter as credenciais em sigilo e utilizar o serviço de maneira lícita. O administrador familiar pode administrar os membros e configurar a visualização das finanças conforme o plano. Cada usuário deve respeitar as permissões e a privacidade dos outros integrantes.</p>
<p>O acesso a funcionalidades pode ficar temporariamente limitado por razões de segurança, manutenção, inadimplência ou contestação de pagamentos, observadas as condições contratadas e os direitos aplicáveis.</p></section>
<section class="legal-section" id="planos"><span class="legal-number">04</span><h2>Planos, valores e renovação</h2>
<p>O preço, a periodicidade (mensal, semestral ou anual), os limites de membros e eventuais condições promocionais são apresentados antes da confirmação da contratação. O pagamento é intermediado pelo Asaas, e a assinatura poderá ter cobrança recorrente conforme o plano selecionado.</p>
<p>Antes de finalizar, confira o valor total do período contratado, os benefícios, a data da próxima cobrança e as condições de renovação. Comprovantes, cobranças e informações da assinatura podem ser consultados na área Minha assinatura, conforme disponibilidade do provedor.</p></section>
<section class="legal-section" id="cancelamento"><span class="legal-number">05</span><h2>Como cancelar uma assinatura?</h2>
<p>O administrador familiar pode solicitar o encerramento da renovação automática pela área Minha assinatura. Isso interrompe futuras cobranças recorrentes, sem necessariamente cancelar faturas já emitidas e sem excluir automaticamente os registros financeiros.</p>
<p>O acesso por período comprovadamente pago poderá permanecer até o seu encerramento, sujeito a situações como estornos ou pendências financeiras. Cancelamentos e pedidos de reembolso são procedimentos distintos; nenhum deles exclui direitos garantidos ao consumidor.</p></section>
<section class="legal-section" id="arrependimento"><span class="legal-number">06</span><h2>Direito de arrependimento e reembolsos</h2>
<p>Nas contratações realizadas fora do estabelecimento comercial, como pela internet, o consumidor tem, em regra, <strong>sete dias</strong> para exercer o direito de arrependimento previsto no artigo 49 do Código de Defesa do Consumidor, contado da assinatura ou do recebimento do serviço, conforme o caso.</p>
<p>Se esse direito for aplicável e exercido no prazo legal, os valores pagos devem ser restituídos nos termos da legislação. Para solicitar arrependimento ou reembolso, contate o atendimento comercial informado nesta página, identificando a contratação. A solicitação será analisada e encaminhada ao procedimento financeiro cabível, sem prejuízo de outros direitos legais.</p></section>
<section class="legal-section" id="dados-servico"><span class="legal-number">07</span><h2>Disponibilidade, histórico e privacidade</h2>
<p>Buscamos manter o serviço disponível e seguro, mas podem ocorrer manutenções e interrupções. A plataforma não promete disponibilidade absoluta nem substitui a guarda pessoal de documentos importantes.</p>
<p>O tratamento de dados pessoais, o compartilhamento e os direitos dos titulares são descritos na <a href="/privacidade">Política de Privacidade</a>. O encerramento da assinatura não implica eliminação instantânea de registros sujeitos a obrigações legais ou direitos de terceiros.</p></section>
<section class="legal-section" id="alteracoes"><span class="legal-number">08</span><h2>Alterações dos termos</h2>
<p>As condições contratuais poderão ser atualizadas mediante comunicação apropriada quando houver mudanças relevantes. Condições de períodos já contratados e direitos assegurados em lei devem ser respeitados. A versão do documento aceita na contratação é registrada pelo Bemora.</p></section>
<?php endif; ?>
<section class="legal-section legal-company" id="contato"><span class="legal-number"><?= $privacy?'08':'09' ?></span>
<h2>Fornecedor e canais de atendimento</h2>
<div class="legal-company-grid">
<div><small>Razão social / empresa</small><strong><?= $company ?></strong></div>
<div><small>CNPJ</small><strong><?= $taxId ?></strong></div>
<div class="legal-company-address"><small>Endereço comercial</small><strong><?= $address ?></strong></div>
<div><small>Suporte e contratação</small><strong><?= $contactHtml($support) ?></strong></div>
<div><small>Solicitações sobre dados pessoais</small><strong><?= $contactHtml($privacyEmail) ?></strong></div>
</div>
<p>O Bemora é uma marca/produto da Anoar. As condições de atendimento e os dados públicos do fornecedor devem ser mantidos atualizados.</p>
</section>
<div class="legal-next"><strong><?= $privacy?'Quer entender as condições de uso?':'Quer saber como protegemos seus dados?' ?></strong>
<p>Consulte também o documento complementar antes de continuar.</p><a href="<?= $privacy?'/termos':'/privacidade' ?>"><?= $privacy?'Ler os Termos de Uso':'Ler a Política de Privacidade' ?> <span aria-hidden="true">→</span></a></div>
</div></div>
<footer class="legal-foot"><span>Bemora <small>by Anoar</small></span><div><a href="/termos">Termos</a><a href="/privacidade">Privacidade</a><a href="/">Página inicial</a></div></footer>
</main></body></html>