<?php
declare(strict_types=1);

/** Pure templates for simulated operational emails; never describe a real outage. */
function bemora_ops_test_email(string $scenario,string $stamp):array {
    $messages=[
        'failure'=>[
            'subject'=>'[TESTE] Bemora · Falha operacional simulada',
            'headline'=>'Teste de alerta de falha',
            'body'=>'SIMULAÇÃO CONTROLADA: uma rotina operacional fictícia apresentou falha. Este evento não corresponde a uma falha real de backup, conciliação financeira ou cobrança. Nenhuma rotina ou conta foi alterada.',
        ],
        'recovery'=>[
            'subject'=>'[TESTE] Bemora · Recuperação operacional simulada',
            'headline'=>'Teste de alerta de recuperação',
            'body'=>'SIMULAÇÃO CONTROLADA: a rotina fictícia voltou a funcionar. Este evento não corresponde à recuperação de uma falha real. Nenhuma rotina ou conta foi alterada.',
        ],
    ];
    if(!isset($messages[$scenario]))throw new InvalidArgumentException('Cenário inválido; use failure ou recovery.');
    $entry=$messages[$scenario];
    $entry['body'].=' Horário UTC do teste: '.$stamp.'. Não é necessário executar qualquer ação.';
    return $entry;
}
