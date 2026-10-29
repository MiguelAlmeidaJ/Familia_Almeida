<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_access();
admin_shell_start('settings','Configurações','Ferramentas e parâmetros globais da plataforma.');
?>
<section class="panel"><div class="panel-head"><div><h2>Gestão do sistema</h2><p class="sub">Operações exclusivas do administrador global</p></div></div><div class="admin-tiles">
<a class="admin-tile" href="/configuracoes/manutencao"><span class="tile-icon">⚙</span><strong>Manutenção</strong><small>Atualizações, migrations e diagnóstico de banco de dados</small><span class="tile-arrow">→</span></a>
<a class="admin-tile" href="/admin/tenants"><span class="tile-icon">♧</span><strong>Gerenciar tenants</strong><small>Consulte famílias e a situação dos ambientes</small><span class="tile-arrow">→</span></a>
</div></section>
<section class="panel"><h2>Segurança e isolamento</h2><p class="sub">A administração global permanece separada das configurações pessoais dos usuários.</p><p class="notice">A plataforma ainda está em transição multi-tenant. Não habilite o cadastro de novas famílias até terminar os filtros de isolamento e os testes de acesso cruzado.</p></section>
<?php admin_shell_end(); ?>
