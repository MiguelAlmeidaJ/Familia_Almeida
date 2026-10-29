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
    if($scope['finance_mode']!=='shared')throw new RuntimeException('O modo individual ainda não está liberado.');
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
    $sql="SELECT owner_user_id FROM `{$table}` WHERE id=? AND tenant_id=?";
    $q=$pdo->prepare($sql);$q->execute([$id,(int)$scope['tenant_id']]);
    $owner=$q->fetchColumn();
    if($owner===false)throw new RuntimeException('Registro não encontrado nesta família.');
    if($scope['mode']==='separate' && $owner!==null && (int)$owner!==(int)$scope['user_id']) {
        throw new RuntimeException('Você não pode alterar dados privados de outro membro.');
    }
}
