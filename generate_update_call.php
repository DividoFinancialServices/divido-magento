<?php

$quote_id = $argv[1];
$status = $argv[2];

$db = new mysqli('magento19.divido.dev', 'root', '', 'magento_19');
if ($db->connect_errno) {
    die($db->connect_error);
}

$s = $db->prepare("select salt from divido_lookup where quote_id = ?");
$s->bind_param('i', $quote_id);
$s->execute();
$s->bind_result($salt);
$s->fetch();
$s->close();
$db->close();

$url = 'http://magento19.divido.dev/divido_callback.php';
$statuses = [
   0 => 'ACCEPTED',
   1 => 'DEPOSIT-PAID',
   2 => 'SIGNED',
   3 => 'FULFILLED',
   4 => 'COMPLETED',
   5 => 'DEFERRED',
   6 => 'REFERRED',
   7 => 'DECLINED',
   8 => 'CANCELED',
];

$hash = hash('sha256', $salt.$quote_id);

$req_tpl = [
    "application" => 'C84047A6D-89B2-FECF-D2B4-168444F5178C',
    "reference" => 100024,
    "status" => "{$status}",
    "live" => false,
    "metadata" =>  [
       "quote_id" => $quote_id,
       "quote_hash" => $hash,
    ],
];

$data = json_encode($req_tpl);

if ($status == 'PROPOSAL') {
    $data = '
        {
            "event": "proposal-new-session",
            "name": " ",
            "proposal": "P519F15A7-D7FE-D354-CA56-ED2505B5A7E7",
            "reference": "",
            "metadata": {
                "quote_id": "' . $quote_id . '",
                "quote_hash": "8f0558149240dc7db2466314b141ebbbf9c9acfe87a17853325c9e0bb55b5355"
            },
            "live": false
        }
    ';
}

$cmd = "curl -v -X POST -d '{$data}' -H 'Content-Type: application/json' {$url}";

system($cmd);

echo "\n\nhttp://magento19.divido.dev/pay/payment/return/?quote_id={$quote_id}";
