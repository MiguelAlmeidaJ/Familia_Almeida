<?php
declare(strict_types=1);

/** Brazilian taxpayer documents: validate both length and check digits. */
function bemora_checkout_valid_document(string $value): bool {
    $digits=preg_replace('/\\D/','',$value);
    if(!is_string($digits)||!in_array(strlen($digits),[11,14],true)||preg_match('/^(\\d)\\1+$/',$digits))return false;
    if(strlen($digits)===11){
        for($size=9;$size<=10;$size++){
            $sum=0;
            for($i=0;$i<$size;$i++)$sum+=(int)$digits[$i]*($size+1-$i);
            $digit=($sum*10)%11;
            if($digit===10)$digit=0;
            if($digit!==(int)$digits[$size])return false;
        }
        return true;
    }
    foreach([[5,4,3,2,9,8,7,6,5,4,3,2],[6,5,4,3,2,9,8,7,6,5,4,3,2]] as $weights){
        $sum=0;
        foreach($weights as $i=>$weight)$sum+=(int)$digits[$i]*$weight;
        $remainder=$sum%11;
        $digit=$remainder<2?0:11-$remainder;
        if($digit!==(int)$digits[count($weights)])return false;
    }
    return true;
}
function bemora_checkout_valid_phone(string $value): bool {
    $digits=preg_replace('/\\D/','',$value);
    if(!is_string($digits))return false;
    if(str_starts_with($digits,'55') && strlen($digits)===13)$digits=substr($digits,2);
    return (bool)preg_match('/^[1-9][0-9]9[0-9]{8}$/D',$digits);
}
