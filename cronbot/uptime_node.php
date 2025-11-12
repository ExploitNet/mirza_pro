<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';



$errorreport = select("topicid","idreport","report","errorreport","select")['idreport'];
$setting = select("setting", "*");
$status_cron = json_decode($setting['cron_status'],true);
if(!$status_cron['uptime_node'])return;
$marzbanlist = select("marzban_panel", "*","type" ,"marzban" ,"fetchAll");
$inbounds = [];
foreach($marzbanlist as $location){
$Getdnodes = Get_Nodes($location['name_panel']);
if(!empty($nodes['error']))continue;
if(!empty($nodes['status'])  && $nodes['status'] != 200 )continue;
$Getdnodes = json_decode($Getdnodes['body'],true);
if(count($Getdnodes) == 0)return;
foreach($Getdnodes as $data){
    if(!in_array($data['status'],["connected","disabled"])){
            $textnode = "🚨 ادمین عزیز نود با اسم {$data['name']} متصل نیست.
وضعیت نود : {$data['status']}
✍️ دلیل خطا : <code> {$data['message']}</code>";
        if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $errorreport,
        'text' => $textnode,
        'parse_mode' => "HTML"
        ]);
    }
    }
}
}