<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tenant.php';
require_auth();
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit('Método não permitido');}
verify_csrf();
switch_tenant((int)($_POST['tenant_id'] ?? 0));
redirect_to('/');
