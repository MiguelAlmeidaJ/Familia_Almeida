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
