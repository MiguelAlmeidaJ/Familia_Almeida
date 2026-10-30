<?php
declare(strict_types=1);

function bemora_plans_table_exists(PDO $pdo):bool {
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_plans'");
 return (int)$q->fetchColumn()===1;
}
function bemora_plan_cycles():array {return ['monthly'=>'Mensal','semiannual'=>'Semestral','annual'=>'Anual'];}
function bemora_promo_durations():array {return ['24h'=>'24 horas','1w'=>'1 semana','1m'=>'1 mês','3m'=>'3 meses'];}
function bemora_plan_price(float $value):string {return 'R$ '.number_format($value,2,',','.');}
function bemora_plan_promo_active(array $p):bool {
 if($p['promo_price']===null || !$p['promo_starts_at'] || !$p['promo_ends_at'])return false;
 $now=new DateTimeImmutable('now');
 return $now>=new DateTimeImmutable((string)$p['promo_starts_at']) && $now<new DateTimeImmutable((string)$p['promo_ends_at']);
}
function bemora_plan_ends_at(string $duration):string {
 $interval=['24h'=>'+24 hours','1w'=>'+7 days','1m'=>'+1 month','3m'=>'+3 months'][$duration]??null;
 if($interval===null)throw new InvalidArgumentException('Duração promocional inválida.');
 return (new DateTimeImmutable('now'))->modify($interval)->format('Y-m-d H:i:s');
}
