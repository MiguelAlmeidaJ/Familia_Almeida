<?php
declare(strict_types=1);

/**
 * Finance authorization uses server-side membership, never supplied owner IDs.
 * Individual finance stays unavailable until every module supports owner scope.
 */
function finance_scope(PDO $pdo, array $user): array {
    $tenantId=(int)($user['tenant_id']??0);
    $userId=(int)($user['id']??0);
    if($tenantId<1 || $userId<1)throw new RuntimeException('Família não autorizada.');
    $q=$pdo->prepare("SELECT t.id,t.finance_mode,tu.role FROM tenants t INNER JOIN tenant_users tu ON tu.tenant_id=t.id AND tu.user_id=? WHERE t.id=? AND t.active=1");
    $q->execute([$userId,$tenantId]);$scope=$q->fetch(PDO::FETCH_ASSOC);
    if(!$scope)throw new RuntimeException('Acesso financeiro não autorizado.');
    if(!in_array($scope['finance_mode'],['shared','separate'],true))throw new RuntimeException('Modo financeiro inválido.');
    if($scope['finance_mode']==='separate' && !finance_separate_schema_ready($pdo))
        throw new RuntimeException('Execute todas as migrations de titularidade antes de usar finanças individuais.');
    return ['tenant_id'=>$tenantId,'user_id'=>$userId,'role'=>$scope['role'],'mode'=>$scope['finance_mode']];
}
function finance_assert_transaction(PDO $pdo,int $transactionId,int $tenantId):void {
    if($transactionId<1 || $tenantId<1)throw new RuntimeException('Lançamento inválido.');
    $q=$pdo->prepare('SELECT 1 FROM transactions WHERE id=? AND tenant_id=? LIMIT 1');
    $q->execute([$transactionId,$tenantId]);
    if(!$q->fetchColumn())throw new RuntimeException('Lançamento não encontrado nesta família.');
}

/**
 * Ownership visibility policy for future individual mode.
 * NULL ownership represents explicitly shared household records.
 * The caller must have authenticated tenant membership.
 */
function finance_owner_predicate(string $alias, bool $individual):string {
    if(!preg_match('/^[a-z][a-z0-9_]*$/i',$alias))throw new InvalidArgumentException('Alias inválido.');
    return $individual ? " AND ({$alias}.owner_user_id IS NULL OR {$alias}.owner_user_id = ?)" : '';
}
function finance_assert_owned_record(PDO $pdo,string $table,int $id,array $scope):void {
    $allowed=['transactions','fixed_bills','recurring_incomes'];
    if(!in_array($table,$allowed,true)||$id<1)throw new RuntimeException('Registro inválido.');
    $col=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
    $col->execute([$table]);
    if(!(int)$col->fetchColumn()){
        if($scope['mode']!=='shared')throw new RuntimeException('Migração de titularidade pendente.');
        $q=$pdo->prepare("SELECT 1 FROM `{$table}` WHERE id=? AND tenant_id=?");
        $q->execute([$id,(int)$scope['tenant_id']]);
        if(!$q->fetchColumn())throw new RuntimeException('Registro não encontrado nesta família.');
        return;
    }
    $sql="SELECT owner_user_id FROM `{$table}` WHERE id=? AND tenant_id=?";
    $q=$pdo->prepare($sql);$q->execute([$id,(int)$scope['tenant_id']]);
    $owner=$q->fetchColumn();
    if($owner===false)throw new RuntimeException('Registro não encontrado nesta família.');
    if($scope['mode']==='separate' && $owner!==null && (int)$owner!==(int)$scope['user_id']) {
        throw new RuntimeException('Você não pode alterar dados privados de outro membro.');
    }
}

/**
 * Server-only owner predicate, suitable for appending to an existing WHERE.
 * This deliberately does not unlock separate mode in auth.php.
 */
function finance_read_owner_clause(PDO $pdo,string $table,string $alias=''):string {
    $allowed=['transactions','fixed_bills','recurring_incomes','monthly_allocations','spending_goals','debts'];
    if(!in_array($table,$allowed,true))throw new InvalidArgumentException('Tabela financeira inválida.');
    if($alias!==''&&!preg_match('/^[a-z][a-z0-9_]*$/i',$alias))throw new InvalidArgumentException('Alias inválido.');
    $userId=(int)($_SESSION['user_id']??0);
    if($userId<=0)throw new RuntimeException('Sessão necessária.');
    $tenantId=require_tenant_id();
    $check=$pdo->prepare("SELECT t.finance_mode FROM tenants t INNER JOIN tenant_users tu ON tu.tenant_id=t.id WHERE tu.user_id=? AND t.id=? AND t.active=1");
    $check->execute([$userId,$tenantId]);
    $mode=$check->fetchColumn();
    if($mode===false)throw new RuntimeException('Família não autorizada.');
    if($mode==='shared')return '';
    if($mode!=='separate')throw new RuntimeException('Modo financeiro inválido.');
    $column=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
    $column->execute([$table]);
    if(!(int)$column->fetchColumn())throw new RuntimeException('Titularidade não configurada nesta tabela.');
    $prefix=$alias!==''?$alias.'.':'';
    // IDs are server-derived integers, not raw request text.
    if($table==='monthly_allocations')return " AND ({$prefix}owner_user_id=0 OR {$prefix}owner_user_id={$userId})";
    return " AND ({$prefix}owner_user_id IS NULL OR {$prefix}owner_user_id={$userId})";
}

/**
 * Release gate: the individual mode is available only after the ownership
 * migrations, including the per-user allocation unique key, are installed.
 * Schema readiness does not replace integration testing.
 */
function finance_separate_schema_ready(PDO $pdo):bool {
    foreach(['transactions','fixed_bills','recurring_incomes','monthly_allocations'] as $table){
        $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
        $q->execute([$table]);
        if(!(int)$q->fetchColumn())return false;
    }
    $q=$pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='monthly_allocations' AND index_name='PRIMARY'");
    if($q->fetchColumn()!=='tenant_id,month,category,owner_user_id')return false;
    $q=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='categories' AND index_name='categories_tenant_type_name_unique'");
    if(!(int)$q->fetchColumn())return false;
    $q=$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration IN ('20261029_015_financial_ownership','20261029_016_finance_owner_keys')");
    return (int)$q->fetchColumn()===2;
}
