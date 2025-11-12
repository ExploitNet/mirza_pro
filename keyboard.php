<?php
require_once 'config.php';
$setting = select("setting", "*", null, null,"select");
$textbotlang = languagechange(__DIR__.'/text.json');
if (!function_exists('getPaySettingValue')) {
    function getPaySettingValue($name)
    {
        $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
        return $result['ValuePay'] ?? null;
    }
}
//-----------------------------[  text panel  ]-------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'textbot'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_dec_usertest' => '',
    'text_fq' => '',
    'accountwallet' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',
    'text_affiliates' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'aqayepardakht' => '',
    'zarinpal' => '',
    'text_fq' => '',
    'textpaymentnotverify' =>"",
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_star_telegram' => "",
    'text_extend' => '',
    'textsnowpayment' => ''

);
if ($table_exists) {
    $textdatabot =  select("textbot", "*", null, null,"fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
$adminrulecheck = select("admin", "*", "id_admin", $from_id,"select");
if (!$adminrulecheck) {
    $adminrulecheck = array(
        'rule' => '',
    );
}
$users = select("user", "*", "id", $from_id,"select");
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
        'agent' => '',
        'limit_usertest' => '',
        'Processing_value' => '',
        'Processing_value_four' => '',
        'cardpayment' => ""
    );
}
$replacements = [
    'text_usertest' => $datatextbot['text_usertest'],
    'text_Purchased_services' => $datatextbot['text_Purchased_services'],
    'text_support' => $datatextbot['text_support'],
    'text_help' => $datatextbot['text_help'],
    'accountwallet' => $datatextbot['accountwallet'],
    'text_sell' => $datatextbot['text_sell'],
    'text_Tariff_list' => $datatextbot['text_Tariff_list'],
    'text_affiliates' => $datatextbot['text_affiliates'],
    'text_wheel_luck' => $datatextbot['text_wheel_luck'],
    'text_extend' => $datatextbot['text_extend']
];
$admin_idss = select("admin", "*", "id_admin", $from_id,"count");
$temp_addtional_key = [];
$keyboardLayout = json_decode($setting['keyboardmain'], true);
$keyboardRows = [];
if (is_array($keyboardLayout) && isset($keyboardLayout['keyboard']) && is_array($keyboardLayout['keyboard'])) {
    $keyboardRows = $keyboardLayout['keyboard'];
}

if ($setting['inlinebtnmain'] == "oninline" && !empty($keyboardRows)) {
    $trace_keyboard = $keyboardRows;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == "text_sell") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "buy";
            }
            if ($keyboard['text'] == "accountwallet") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "account";
            }
            if ($keyboard['text'] == "text_Tariff_list") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
            }
            if ($keyboard['text'] == "text_wheel_luck") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "wheel_luck";
            }
            if ($keyboard['text'] == "text_affiliates") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "affiliatesbtn";
            }
            if ($keyboard['text'] == "text_extend") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "extendbtn";
            }
            if ($keyboard['text'] == "text_support") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "supportbtns";
            }
            if ($keyboard['text'] == "text_Purchased_services") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "backorder";
            }
            if ($keyboard['text'] == "text_help") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "helpbtns";
            }
            if ($keyboard['text'] == "text_usertest") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "usertestbtn";
            }
        }
    }
    if ($admin_idss != 0) {
        $temp_addtional_key[] = ['text' => $textbotlang['Admin']['textpaneladmin'], 'callback_data' => "admin"];
    }
    if ($users['agent'] != "f") {
        $temp_addtional_key[] = ['text' => $datatextbot['textpanelagent'], 'callback_data' => "agentpanel"];
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $temp_addtional_key[] = ['text' => $datatextbot['textrequestagent'], 'callback_data' => "requestagent"];
    }
    $keyboard = ['inline_keyboard' => []];
    $keyboardcustom = $trace_keyboard;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    $keyboardcustom[] = $temp_addtional_key;
    $keyboard['inline_keyboard'] = $keyboardcustom;
    $keyboard = json_encode($keyboard);
} else {
    if ($admin_idss != 0) {
        $temp_addtional_key[] = ['text' => $textbotlang['Admin']['textpaneladmin']];
    }
    if ($users['agent'] != "f") {
        $temp_addtional_key[] = ['text' => $datatextbot['textpanelagent']];
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $temp_addtional_key[] = ['text' => $datatextbot['textrequestagent']];
    }
    $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
    $keyboardcustom = $keyboardRows;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    $keyboardcustom[] = $temp_addtional_key;
    $keyboard['keyboard'] = $keyboardcustom;
    $keyboard = json_encode($keyboard);
}

$keyboardPanel = json_encode([
    'inline_keyboard' => [
        [['text' => $datatextbot['text_Discount'] ,'callback_data' => "Discount"],
        ['text' => $datatextbot['text_Add_Balance'] ,'callback_data' => "Add_Balance"]
        ],
        [['text' => $textbotlang['users']['backbtn'] ,'callback_data' => "backuser"]],
    ],
    'resize_keyboard' => true
]);
if($adminrulecheck['rule'] == "administrator"){
$keyboardadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Status']['btn']]],
        [['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel']],['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel']]],
        [['text' => "⏳ تنظیم سریع قیمت زمان"],['text' => "🔋 تنظیم سریع قیمت حجم"]],
        [['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser']],['text' => "🏬 تنظیمات فروشگاه"]],
        [['text' => "💎 مالی"]],
        [['text' => "🤙 بخش پشتیبانی"],['text' => "📚 بخش آموزش"]],
        [['text' => "📬 گزارش ربات"],['text' => "🛠 قابلیت های پنل"]],
        [['text' => "⚙️ تنظیمات عمومی"],['text' => "💵 رسید های تایید نشده"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
]);
}
if($adminrulecheck['rule'] == "Seller"){
$keyboardadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Status']['btn']]],
        [['text' => "👤 مدیریت کاربر"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
]);
}
if($adminrulecheck['rule'] == "support"){
$keyboardadmin = json_encode([
    'keyboard' => [
        [['text' => "👤 مدیریت کاربر"],['text' =>"👁‍🗨 جستجو کاربر"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
]);
}
$CartManage = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه کارت به کارت"]],
        [['text' => "💳 تنظیم شماره کارت"],['text' => "❌ حذف شماره کارت"]],
        [['text' => "👤 آیدی پشتیبانی", ],['text' => "💳 درگاه آفلاین در پیوی"]],
        [['text' => "💰  غیرفعالسازی  نمایش شماره کارت"],['text' => "💰 فعالسازی نمایش شماره کارت"]],
        [['text' => "♻️ نمایش گروهی شماره کارت"]],
        [['text' => "📄 خروجی افراد شماره کارت فعال"]],
        [['text' => "♻️ تایید خودکار رسید"],['text' => "💰 کش بک کارت به کارت"]],
        [['text' => "🔒 نمایش کارت به کارت پس از اولین پرداخت"]],
        [['text' => "⬇️ حداقل مبلغ کارت به کارت"],['text' => "⬆️ حداکثر مبلغ کارت به کارت"]],
        [['text' => "📚 تنظیم آموزش کارت به کارت"]],
        [['text' => "🤖 تایید رسید  بدون بررسی"]],
        [['text' => "💳 استثناء کردن کاربر از تایید خودکار"]],
        [['text' => "⏳ زمان تایید خودکار بدون بررسی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$trnado = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه ارزی ریالی دوم"]],
        [['text' => "API T"]],
        [['text' => "تنظیم آدرس api"]],
        [['text' => "💰 کش بک ارزی ریالی دوم"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی دوم"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی دوم"]],
        [['text' => "📚 تنظیم آموزش ارزی ریالی  دوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardzarinpal = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه زرین پال"],['text' => "مرچنت زرین پال"]],
        [['text' => "💰 کش بک زرین پال"]],
        [['text' => "⬇️ حداقل مبلغ زرین پال"],['text' => "⬆️ حداکثر مبلغ زرین پال"]],
        [['text' => "📚 تنظیم آموزش زرین پال"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$aqayepardakht = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه آقای پرداخت"]],
        [['text' => "تنظیم مرچنت آقای پرداخت"],['text' => "💰 کش بک آقای پرداخت"]],
        [['text' => "⬇️ حداقل مبلغ آقای پرداخت"],['text' => "⬆️ حداکثر مبلغ آقای پرداخت"]],
        [['text' => "📚 تنظیم آموزش درگاه اقای پرداخت"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$NowPaymentsManage = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه   plisio"]],
        [['text' => "🧩 api plisio"],['text'=> "💰 کش بک plisio"]],
        [['text' => "⬇️ حداقل مبلغ plisio"],['text' =>"⬆️ حداکثر مبلغ plisio"]],
        [['text' => "📚 تنظیم آموزش plisio"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$setting_panel =  json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها"]],
        [['text' => "📣 گزارشات ربات"], ['text' => "📯 تنظیمات کانال"]],
        [['text' => "✅ فعالسازی پنل تحت وب"]],
        [['text' => "🗑 بهینه سازی ربات "]],
        [['text' => "📝 تنظیم متن ربات"],['text' => "👨‍🔧 بخش ادمین"]],
        [['text' => "➕ محدودیت ساخت اکانت تست برای همه"]],
        [['text' => "💰 مبلغ عضویت نمایندگی"],['text' => "🖼 پس زمینه کیوآرکد"]],
        [['text' => "🔗 وبهوک مجدد ربات های نماینده"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingaqayepardakht = getPaySettingValue("statusaqayepardakht");
$PaySettingpv = getPaySettingValue("Cartstatuspv");
$usernamecart = getPaySettingValue("CartDirect");
$Swapino = getPaySettingValue("statusSwapWallet");
$trnadoo = getPaySettingValue("statustarnado");
$paymentverify = getPaySettingValue("checkpaycartfirst");
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = '$from_id' AND payment_Status = 'paid' ");
$stmt->execute();
$paymentexits = $stmt->rowCount();
$zarinpal = getPaySettingValue("zarinpalstatus");
$affilnecurrency = getPaySettingValue("digistatus");
$arzireyali3 = getPaySettingValue("statusiranpay3");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$step_payment = [
    'inline_keyboard' => []
    ];
   if($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1){
        if($PaySettingpv == "oncardpv"){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['carttocart'] ,'url' => "https://t.me/$usernamecart"],
    ];
        }else{
                    $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['carttocart'] ,'callback_data' => "cart_to_offline"],
    ];
        }
    }
    if(($paymentexits == 0 && $paymentverify == "onpayverify"))unset($step_payment['inline_keyboard']);
   if($PaySettingnow == "onnowpayment"){
        $step_payment['inline_keyboard'][] = [
    ['text' => $datatextbot['textnowpayment'], 'callback_data' => "plisio" ]
    ];
    }
    if($payment_status_nowpayment == "1"){
        $step_payment['inline_keyboard'][] = [
    ['text' => $datatextbot['textsnowpayment'], 'callback_data' => "nowpayment" ]
    ];
    }
   if($affilnecurrency == "ondigi"){
        $step_payment['inline_keyboard'][] = [
            ['text' =>  $datatextbot['textnowpaymenttron'], 'callback_data' => "digitaltron" ]
    ];
    }
   if($Swapino == "onSwapinoBot"){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['iranpay2'] , 'callback_data' => "iranpay1" ]
    ];
    }
   if($trnadoo == "onternado"){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['iranpay3'] , 'callback_data' => "iranpay2" ]
    ];
    }
     if($arzireyali3 == "oniranpay3"  && $paymentexits >= 2){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['iranpay1'] , 'callback_data' => "iranpay3" ]
    ];
    }
   if($PaySettingaqayepardakht == "onaqayepardakht"){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['aqayepardakht'] , 'callback_data' => "aqayepardakht" ]
    ];
    }
    if($zarinpal == "onzarinpal"){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['zarinpal'] , 'callback_data' => "zarinpal" ]
    ];
    }
    if($paymentstatussnotverify == "onverifypay"){
        $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['textpaymentnotverify'] , 'callback_data' => "paymentnotverify" ]
    ];
    }
    if(intval($paymentsstartelegram) == 1){
     $step_payment['inline_keyboard'][] = [
            ['text' => $datatextbot['text_star_telegram'] , 'callback_data' => "startelegrams" ]
    ];   
    }
    $step_payment['inline_keyboard'][] = [
            ['text' => "❌ بستن لیست" , 'callback_data' => "colselist" ]
    ];
    $step_payment = json_encode($step_payment);
$keyboardhelpadmin = json_encode([
    'keyboard' => [
        [['text' => "📚 اضافه کردن آموزش"], ['text' => "❌ حذف آموزش"]],
        [['text' => "✏️ ویرایش آموزش"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$shopkeyboard = json_encode([
    'keyboard' => [
        [['text' => "🛒 وضعیت قابلیت های فروشگاه"]],
        [['text' => "🗂 مدیریت دسته بندی"],['text' => "🛍 مدیریت محصولات"]],
        [['text' => "🎁 ساخت کد هدیه"],['text' => "❌ حذف کد هدیه"]],
        [['text' => "🎁 ساخت کد تخفیف"],['text' => "❌ حذف کد تخفیف"]],
        [['text' => "⬇️ حداقل موجودی خرید عمده"],['text' => "🎁 کش بک تمدید"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboard_Category_manage = json_encode([
    'keyboard' => [
        [['text' => "🛒 اضافه کردن دسته بندی"],['text' => "❌ حذف دسته بندی"]],
        [['text' => "✏️ ویرایش دسته بندی"]],
        [['text' => "⬅️ بازگشت به منوی فروشگاه"]]
    ],
    'resize_keyboard' => true
    ]);
$keyboard_shop_manage = json_encode([
    'keyboard' => [
        [['text' => "🛍 اضافه کردن محصول"], ['text' => "❌ حذف محصول"]],
        [['text' => "✏️ ویرایش محصول"]],
        [['text' => "⬆️ افزایش گروهی قیمت"],['text' => "⬇️ کاهش  گروهی قیمت"]],
        [['text' => "⬅️ بازگشت به منوی فروشگاه"]]
    ],
    'resize_keyboard' => true
]);
if($setting['inlinebtnmain'] == "oninline"){
    $confrimrolls = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "✅ قوانین را می پذیرم", 'callback_data' => "acceptrule"],
            ],
    ]
    ]);
}else{
$confrimrolls = json_encode([
    'keyboard' => [
        [['text' => "✅ قوانین را می پذیرم"]],
    ],
    'resize_keyboard' => true
]);
}
$request_contact = json_encode([
    'keyboard' => [
        [['text' => "☎️ ارسال شماره تلفن", 'request_contact' => true]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
]);
$Feature_status = json_encode([
    'keyboard' => [
        [['text' => "قابلیت مشاهده اطلاعات اکانت"]],
        [['text' => "قابلیت اکانت تست"], ['text' => "قابلیت آموزش"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$channelkeyboard = json_encode([
    'keyboard' => [
        [['text' => "اضافه کردن کانال"],['text' => "حذف کانال"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if($setting['inlinebtnmain'] == "oninline"){
    $backuser = json_encode([
        'inline_keyboard' => [
        [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]]
    ],
]);
}else{
$backuser = json_encode([
        'keyboard' => [
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"برای بازگشت روی دکمه زیر کلیک کنید"
]);
}
$backadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"برای بازگشت روی دکمه زیر کلیک کنید"
]);
//------------------  [ list panel ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);
//------------------  [ list panel inline ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    $list_marzban_panel_edit_product = ['inline_keyboard' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>$row['name_panel'],'callback_data' => 'locationedit_'.$row['code_panel']]];
    }
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"همه پنل ها",'callback_data' => 'locationedit_all']];
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"▶️ بازگشت به منوی قبل",'callback_data' => 'backproductadmin']];
    $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
}
//------------------  [ list channel ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_channels = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM channels");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_channels[] = [$row['link']];
    }
    $list_channels_join = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_channels as $button) {
        $list_channels_join['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_channels_join['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    $list_channels_joins = json_encode($list_channels_join);
}
//------------------  [ list card ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_card = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM card_number");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_card[] = [$row['cardnumber']];
    }
    $list_card_remove = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_card as $button) {
        $list_card_remove['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_card_remove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    $list_card_remove = json_encode($list_card_remove);
}
//------------------  [ help list ]----------------//
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'help'");
    $stmt->execute();
    $result = $stmt->fetchAll();
    $table_exists = count($result) > 0;
    if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpkey = [];
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpkey[] = [$row['name_os']];
        }
        $help_arrke = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];
        foreach ($helpkey as $button) {
            $help_arrke['keyboard'][] = [
                ['text' => $button[0]]
            ];
        }
                $help_arrke['keyboard'][] = [
            ['text' => $textbotlang['users']['backbtn']],
        ];
        $json_list_helpkey = json_encode($help_arrke);
}
//------------------  [ help list ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpcwtgory = ['inline_keyboard' => []];
    $datahelp = [];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if(in_array($result['category'],$datahelp))continue;
        if($result['category'] == null)continue;
        $datahelp[] = $result['category'];
            $helpcwtgory['inline_keyboard'][] = [['text' => $result['category'], 'callback_data' => "helpctgoryـ{$result['category']}"]
            ];
        }
if($setting['linkappstatus'] == "1"){
    $helpcwtgory['inline_keyboard'][] = [
        ['text' => "🔗 لینک دانلود برنامه", 'callback_data' => "linkappdownlod"],
    ];    
    }
$helpcwtgory['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـcategory = json_encode($helpcwtgory);


//------------------  [ help app ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM app");
    $stmt->execute();
    $helpapp = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpapp['inline_keyboard'][] = [['text' => $result['name'], 'url' =>$result['link']]
            ];
        }
$helpapp['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـlink = json_encode($helpapp);
//------------------  [ help app admin ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM app");
    $stmt->execute();
    $helpappremove = ['keyboard' => [],'resize_keyboard' => true];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpappremove['keyboard'][] = [
            ['text' => $result['name']],
        ];
        }
$helpappremove['keyboard'][] = [
    ['text' => $textbotlang['Admin']['backadmin']],
];
$json_list_remove_helpـlink = json_encode($helpappremove);
 //------------------  [ listpanelusers ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if($panelcount > 10){
        $temp_row = [];
         while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;
        if($result['type'] == "Manualsale"){
            $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
            $stmt->bindParam(':codepanel', $result['code_panel']);
            $stmt->execute();
            $configexits = $stmt->rowCount();
            if(intval($configexits) == 0)continue;
        }
        if ($users['step'] == "getusernameinfo") {
            $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"];
        } else {
            $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"];
        }
         if (count($temp_row) == 2) {
            $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
            $temp_row = []; 
        }
    } 
        if (!empty($temp_row)) {
        $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
    }
    }else{
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['type'] == "Manualsale"){
            $stmts = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
            $stmts->bindParam(':codepanel', $result['code_panel']);
            $stmts->execute();
            $configexits = $stmts->rowCount();
            if(intval($configexits) == 0)continue;
        }
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
        if ($users['step'] == "getusernameinfo") {
            $list_marzban_panel_users['inline_keyboard'][] = [
                ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"]
            ];
        }
        else{
            $list_marzban_panel_users['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"]
            ];
        }
    }
    }
$statusnote = false; 
if($setting['statusnamecustom'] == 'onnamecustom')$statusnote = true;
if($setting['statusnoteforf'] == "0" && $users['agent'] == "f")$statusnote = false;
    if($statusnote){
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "buyback"],
];
}else{
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];  
}
$list_marzban_panel_user = json_encode($list_marzban_panel_users);


//------------------  [ listpanelusers omdhe ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users_om = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
            $list_marzban_panel_users_om['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationom_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_users_om['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_panel_userom = json_encode($list_marzban_panel_users_om);

//------------------  [ change location ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = '{$users['agent']}' OR agent = 'all') AND name_panel != '{$users['Processing_value_four']}'");
    $stmt->execute();
    $list_marzban_panel_users_change = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if($panelcount > 10){
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;
    
            $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"];
        if (count($temp_row) == 2) {
            $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
            $temp_row = [];
        }
    }
if (!empty($temp_row)) {
    $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
}
    }else{
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
            $list_marzban_panel_users_change['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"]
            ];
    }
    }
$list_marzban_panel_users_change['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backorder"],
];
$list_marzban_panel_userschange = json_encode($list_marzban_panel_users_change);


//------------------  [ listpanelusers test ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = '{$users['agent']}' OR agent = 'all')");
    $stmt->execute();
    $list_marzban_panel_usertest = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($result['hide_user'] != null and in_array($from_id,json_decode($result['hide_user'],true)))continue;
            $list_marzban_panel_usertest['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);


$textbot = json_encode([
    'keyboard' => [
        [['text' => "تنظیم متن شروع"], ['text' => "دکمه سرویس خریداری شده"]],
        [['text' => "دکمه اکانت تست"], ['text' => "دکمه سوالات متداول"]],
        [['text' => "متن دکمه 📚 آموزش"], ['text' => "متن دکمه ☎️ پشتیبانی"]],
        [['text' => "دکمه افزایش موجودی"],['text' => "متن دکمه زیرمجموعه گیری"]],
        [['text' => "متن دکمه خرید اشتراک"], ['text' => "متن دکمه لیست تعرفه"]],
        [['text' => "متن توضیحات لیست تعرفه"]],
        [['text' => "متن دکمه کیف پول"],['text' => "متن پیش فاکتور"]],
        [['text' => "📝 تنظیم متن توضیحات عضویت اجباری"]],
        [['text' => "📝 تنظیم متن توضیحات سوالات متداول"]],
        [['text' => "⚖️ متن قانون"],['text' => "متن بعد خرید"]],
        [['text' => "متن بعد خرید ibsng"],['text' => "دکمه تمدید"]],
        [['text' => "متن بعد گرفتن اکانت تست"],['text' =>"متن کرون تست"]],
        [['text' => "متن بعد گرفتن اکانت دستی"]],
        [['text' => "متن بعد گرفتن اکانت WGDashboard"]],
        [['text' => "متن انتخاب لوکیشن"],['text' => "متن دکمه کد هدیه"]],
        [['text' => "متن درخواست نمایندگی"],['text' => "متن دکمه  نمایندگی"]],
        [['text' => "متن دکمه گردونه شانس"],['text' => "متن کارت به کارت"]],
        [['text' => "تنظیم متن کارت به کارت خودکار"]],
        [['text' => "متن توضیحات درخواست نمایندگی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $getdataprotocol = select("protocol","*",null,null,"fetchAll");
    $protocol = [];
    foreach($getdataprotocol as $result)
    {
        $protocol[] = [['text'=>$result['NameProtocol']]];
    }
    $protocol[] = [['text'=>$textbotlang['Admin']['backadmin']]];
    $keyboardprotocollist = json_encode(['resize_keyboard'=>true,'keyboard'=> $protocol]);
 }
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin = json_encode($list_Discount);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Inboundkeyboard = [];
    $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->bindParam(':Processing_value', $users['Processing_value']  , PDO::PARAM_STR);
    $stmt->execute();
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $Inboundkeyboard[] = [$row['NameInbound']];
}
    
}
    $list_Inbound = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Inboundkeyboard as $button) {
        $list_Inbound['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_Inbound['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Inbound_list_admin = json_encode($list_Inbound);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'DiscountSell'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discountsell['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
$payment = json_encode([
    'inline_keyboard' => [
        [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"]],
        [['text' => "🎁 ثبت کد تخفیف", 'callback_data' => "aptdc"]],
        [['text' => $textbotlang['users']['backbtn'] ,  'callback_data' => "backuser"]]
    ]
]);
$paymentom = json_encode([
    'inline_keyboard' => [
        [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"]],
        [['text' => $textbotlang['users']['backbtn'] ,  'callback_data' => "backuser"]]
    ]
]);
$change_product = json_encode([
    'keyboard' => [
        [['text' => "قیمت"], ['text' => "حجم"], ['text' => "زمان"]],
        [['text' => "نام محصول"],['text' => "نوع کاربری"]],
        [['text' => "نوع ریست حجم"],['text' => "یادداشت"]],
        [['text' => "موقعیت محصول"],['text' => "دسته بندی"]],
        [['text' => "🎛 تنظیم اینباند"],['text' => "نمایش برای خرید اول"]],
        [['text' => "مخفی کردن پنل"],['text' => "حذف کلی پنل های مخفی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"],['text' => "vmess"],['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionMarzban = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "⚙️ تنظیم پروتکل و اینباند"]],
        [['text' => "🔋 روش تمدید سرویس"],['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "⚙️ قیمت حجم سرویس دلخواه"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "⚙️  اینباند اکانت غیرفعال"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionibsng = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => '🎛 تنظیم نام گروه']],
        [['text' => "🔋 روش تمدید سرویس"],['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "⚙️ قیمت حجم سرویس دلخواه"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$option_mikrotik = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => '🎛 تنظیم نام گروه']],
        [['text' => "🔋 روش تمدید سرویس"],['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "⚙️ قیمت حجم سرویس دلخواه"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$options_ui = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "⚙️ تنظیم پروتکل و اینباند"]],
        [['text' => "🔋 روش تمدید سرویس"],['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "⚙️ قیمت حجم سرویس دلخواه"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "⚙️  اینباند اکانت غیرفعال"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionwg = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "💎 تنظیم شناسه اینباند"]],
        [['text' => "🔋 روش تمدید سرویس"],['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "⚙️ قیمت حجم سرویس دلخواه"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "⚙️  اینباند اکانت غیرفعال"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionmarzneshin = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "🔋 روش تمدید سرویس"]],
        [['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => "⚙️ تنظیمات سرویس"],['text' => "🚨 محدودیت ساخت اکانت"]],
        [['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⚙️ قیمت حجم سرویس دلخواه"]],
        [['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionManualsale = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "💡 روش ساخت نام کاربری"]],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text'=> "📍 تغییر گروه کاربری"]],
        [['text' => "➕ اضافه کردن کانفیگ"],['text' => "❌ حذف کانفیگ "]],
        [['text' => "✏️ ویرایش کانفیگ"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionX_ui_single = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "🔋 روش تمدید سرویس"]],
        [['text' => "💎 تنظیم شناسه اینباند"]],
        [['text' =>"💡 روش ساخت نام کاربری"],['text' => '🔗 دامنه لینک ساب']],
        [['text' => "📍 تغییر گروه کاربری"],['text' => "🚨 محدودیت ساخت اکانت"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⚙️ قیمت حجم سرویس دلخواه"]],
        [['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionalireza_single = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text' => "🔐 ویرایش رمز عبور"],['text' => "👤 ویرایش نام کاربری"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "🔋 روش تمدید سرویس"]],
        [['text' => "💎 تنظیم شناسه اینباند"]],
        [['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => '🔗 دامنه لینک ساب']],
        [['text' => "📍 تغییر گروه کاربری"],['text' => "🚨 محدودیت ساخت اکانت"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⚙️ قیمت حجم سرویس دلخواه"]],
        [['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionhiddfy = json_encode([
    'keyboard' => [
        [['text' => "⚙️ وضعیت قابلیت ها پنل"]],
        [['text' => "✍️ نام پنل"],['text' => "❌ حذف پنل"]],
        [['text'=>"🔗 ویرایش آدرس پنل"],['text' => "🔋 روش تمدید سرویس"]],
        [['text' => "📍 تغییر گروه کاربری"]],
        [['text' =>"💡 روش ساخت نام کاربری"]],
        [['text' => '🔗 دامنه لینک ساب']],
        [['text' => "🚨 محدودیت ساخت اکانت"],['text' => "🔗 uuid admin"]],
        [['text' => "⏳ زمان سرویس تست"], ['text' => "💾 حجم اکانت تست"]],
        [['text' => "🌍 قیمت تغییر لوکیشن"],['text' => "➕ قیمت حجم اضافه"]],
        [['text' => "⏳ قیمت زمان اضافه"],['text' => "⚙️ قیمت حجم سرویس دلخواه"]],
        [['text' => "⏳ قیمت زمان دلخواه"]],
        [['text' => "📍 حداقل حجم دلخواه"],['text' => "📍 حداکثر حجم دلخواه"]],
        [['text' => "📍 حداقل زمان دلخواه"],['text' => "📍 حداکثر زمان دلخواه"]],
        [['text' => "🫣 مخفی کردن پنل برای یک کاربر"]],
        [['text' => "❌  حذف کاربر از لیست مخفی شدگان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if($setting['statussupportpv'] == "onpvsupport"){
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'url' => "https://t.me/{$setting['id_support']}"    ],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],
 
        ]
    ]);
}else{
$supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 ارسال پیام به پشتیبانی", 'callback_data' => "support"],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],
 
        ]
    ]);
}
$adminrule = json_encode([
    'keyboard' => [
        [['text' => "administrator"],['text' => "Seller"],['text' => "support"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$affiliates =  json_encode([
    'keyboard' => [
        [['text' => "🧮 تنظیم درصد زیرمجموعه"]],
        [['text' => "🏞 تنظیم بنر زیرمجموعه گیری"]],
        [['text' => "🎁 پورسانت بعد از خرید"],['text' => "🎁 هدیه استارت"]],
        [['text' => "🎉 پورسانت فقط برای خرید اول"]],
        [['text' => "🌟 مبلغ هدیه استارت"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardexportdata =  json_encode([
    'keyboard' => [
        [['text' => "خروجی کاربران"],['text' => "خروجی سفارشات"]],
        [['text' => "خروجی گرفتن پرداخت ها"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$helpedit =  json_encode([
    'keyboard' => [
        [['text' =>"ویرایش نام"],['text' =>"ویرایش توضیحات"]],
        [['text' => "ویرایش رسانه"],['text' => "ویرایش دسته بندی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Methodextend = json_encode([
    'keyboard' => [
        [['text' => "ریست حجم و زمان"]],
        [['text' => "اضافه شدن زمان و حجم به ماه بعد"]],
        [['text'=> "ریست زمان و اضافه کردن حجم قبلی"]],
        [['text' => "ریست شدن حجم و اضافه شدن زمان"]],
        [['text' => "اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtimereset = json_encode([
    'keyboard' => [
        [['text' => "no_reset"],['text' => "day"],['text' => "week"]],
        [['text' => "month"],['text' => "year"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtypepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "مرزبان" , 'callback_data' => "typepanel#marzban"],
            ['text' => "مرزنشین" , 'callback_data' => "typepanel#marzneshin"]
        ],
        [
            ['text' => 'ثنایی تک پورت', 'callback_data' => 'typepanel#x-ui_single'],
            ['text' => 'علیرضا تک پورت' , 'callback_data' => 'typepanel#alireza_single']
        ],
        [
            ['text' => "فروش دستی" , 'callback_data' => 'typepanel#Manualsale'],
            ['text' => "هیدیفای" , 'callback_data' => 'typepanel#hiddify'],
        ],
        [
            ['text' => "WGDashboard", 'callback_data' => 'typepanel#WGDashboard'],
            ['text' => "s_ui", 'callback_data' => 'typepanel#s_ui']
        ],
        [
            ['text' => "ibsng", 'callback_data' => 'typepanel#ibsng'],
            ['text' => "میکروتیک", 'callback_data' => 'typepanel#mikrotik']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
        ]
    ],
]);

$panelechekc = select("marzban_panel","*","MethodUsername","متن دلخواه نماینده + عدد ترتیبی","count");
if($setting['inlinebtnmain'] == "oninline"){
    $keyboardagent = [
    'inline_keyboard' => [
        [
            ['text' => "🗂 خرید انبوه", 'callback_data' => "kharidanbuh"],
            ['text' => "👤 انتخاب نام دلخواه", 'callback_data' => "selectname"]
        ],
        [
            ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
        ]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['inline_keyboard'][0][1]);
}
}else{
$keyboardagent = [
    'keyboard' => [
        [['text' => "🗂 خرید انبوه"],['text' => "👤 انتخاب نام دلخواه"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['keyboard'][0][1]);
}
}
$keyboardagent = json_encode($keyboardagent);
$Swapinokey = json_encode([
    'keyboard' => [
        [['text' => "تنظیم api"]],
        [['text' => "🗂 نام درگاه ارزی ریالی"]],
        [['text' => "💰 کش بک ارزی ریالی"],['text' => "📚 تنظیم آموزش ارزی ریالی اول"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه رمز ارز آفلاین"]],
        [['text' => "⬇️ حداقل مبلغ رمزارز آفلاین"],['text' => "⬆️ حداکثر مبلغ رمزارز آفلاین"]],
        [['text' => "📚 تنظیم آموزش  ارزی افلاین"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = json_encode([
    'keyboard' => [
        [['text' => "🔧 ساخت کانفیگ دستی"],['text' => "🖥 مدیریت نود ها"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathx_ui = json_encode([
    'keyboard' => [
        [['text' => "🔧 ساخت کانفیگ دستی"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$configedit = json_encode([
    'keyboard' => [
        [['text' => "مخشصات کانفیگ"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$iranpaykeyboard = json_encode([
    'keyboard' => [
        [['text' => "api  درگاه ارزی ریالی"]],
        [['text' => "🗂 نام درگاه ارزی ریالی سوم"]],
        [['text' => "⬇️ حداقل مبلغ ارزی ریالی سوم"],['text' => "⬆️ حداکثر مبلغ ارزی ریالی سوم"]],
        [['text' => "💰 کش بک ارزی ریالی سوم"]],
        [['text' => "📚 تنظیم آموزش ارزی ریالی سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$supportcenter = json_encode([
    'keyboard' => [
        [['text' => "👤 تنظیم آیدی پشتیبانی"]],
        [['text' => "🔼 اضافه کردن دپارتمان"],['text' => "🔽 حذف کردن دپارتمان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
//------------------  [ list departeman ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'departman'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$departeman = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departeman[] = [$row['name_departman']];
    }
    $departemans = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($departeman as $button) {
        $departemans['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $departemans['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    $departemanslist = json_encode($departemans);
}
// list departeman
    $list_departman = ['inline_keyboard' => []];
 $stmt = $pdo->prepare("SELECT * FROM departman");
 $stmt->execute();
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_departman['inline_keyboard'][] = [['text' => $result['name_departman'], 'callback_data' => "departman_{$result['id']}"]
            ];
    }
$list_departman['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_departman = json_encode($list_departman);
$active_panell =  json_encode([
    'keyboard' => [
        [['text' => "📣 گزارشات ربات"]],
    ],
    'resize_keyboard' => true
]);
$lottery =  json_encode([
    'keyboard' => [
        [['text' => "1️⃣ تنظیم جایزه نفر اول"],['text' => "2️⃣ تنظیم جایزه نفر دوم"]],
        [['text' => "3️⃣ تنظیم جایزه نفر سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$wheelkeyboard =  json_encode([
    'keyboard' => [
        [['text' => "🎲 مبلغ برنده شدن کاربر"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$keyboardlinkapp = json_encode([
    'keyboard' => [
        [['text' => "🔗 اضافه کردن برنامه"],['text' => "❌ حذف برنامه"]],
        [['text' => "✏️ ویرایش برنامه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardProduct($location,$query,$pricediscount,$datakeyboard,$statuscustom = false,$backuser = "backuser", $valuetow = null,$customvolume = "customsellvolume"){
    global $pdo,$textbotlang,$from_id;
    $product = ['inline_keyboard' => []];
    $statusshowprice = select("shopSetting","*","Namevalue","statusshowprice","select")['value'];
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    if($valuetow != null){
            $valuetow = "-$valuetow";
    }else{
            $valuetow = "";
        }
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hide_panel = json_decode($result['hide_panel'],true);
        if(in_array($location,$hide_panel))continue;
        $stmts2 = $pdo->prepare("SELECT * FROM invoice WHERE Status != 'Unpaid' AND id_user = '$from_id'");
        $stmts2->execute();
        $countorder = $stmts2->rowCount();
        if($result['one_buy_status'] == "1" && $countorder != 0 )continue;
        if(intval($pricediscount) != 0){
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] -$resultper;
        }
        $namekeyboard = $result['name_product']." - ".number_format($result['price_product']) ."تومان";
        if($statusshowprice == "onshowprice"){
            $result['name_product'] = $namekeyboard;
        }
        $product['inline_keyboard'][] = [
                ['text' =>  $result['name_product'], 'callback_data' => "{$datakeyboard}{$result['code_product']}{$valuetow}"]
            ];
    }
    if ($statuscustom)$product['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume]];
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser],
    ];
    return json_encode($product);
}
function KeyboardCategory($location,$agent,$backuser = "backuser"){
    global $pdo,$textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = ['inline_keyboard' => [],];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmts = $pdo->prepare("SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND category = :category AND agent = :agent");
        $stmts->bindParam(':location', $location, PDO::PARAM_STR);
        $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
        $stmts->bindParam(':agent', $agent);
        $stmts->execute();
        if($stmts->rowCount() == 0)continue;
        $list_category['inline_keyboard'][] = [['text' =>$row['remark'],'callback_data' => "categorynames_".$row['id']]];
    }
    $list_category['inline_keyboard'][] = [
        ['text' => "▶️ بازگشت به منوی قبل","callback_data" => $backuser],
    ];
    return json_encode($list_category);
}

function keyboardTimeCategory($name_panel,$agent,$callback_data = "producttime_",$callback_data_back = "backuser",$statuscustomvolume = false,$statusbtnextend = false){
    global $pdo,$textbotlang;
    $stmt = $pdo->prepare("SELECT (Service_time) FROM product WHERE (Location = '$name_panel' OR Location = '/all') AND  agent = '$agent'");
    $stmt->execute();
    $montheproduct = array_flip(array_flip($stmt->fetchAll(PDO::FETCH_COLUMN)));
    $monthkeyboard = ['inline_keyboard' => []];
    if (in_array("1",$montheproduct)){
        $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1day'], 'callback_data' => "{$callback_data}1"]
                ];
            }
    if (in_array("7",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['7day'], 'callback_data' => "{$callback_data}7"]
                ];
            }
    if (in_array("31",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}31"]
                ];
            }
    if (in_array("30",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}30"]
                ];
            }
    if (in_array("61",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}61"]
                ];
            }
    if (in_array("60",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}60"]
                ];
            }
    if (in_array("91",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}91"]
                ];
            }
    if (in_array("90",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}90"]
                ];
            }
    if (in_array("121",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}121"]
                ];
            }
    if (in_array("120",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}120"]
                ];
            }
    if (in_array("181",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}181"]
                ];
            }
    if (in_array("180",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}180"]
                ];
            }
    if (in_array("365",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['365'], 'callback_data' => "{$callback_data}365"]
                ];
            }
    if (in_array("0",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['unlimited'], 'callback_data' => "{$callback_data}0"]
                ];
            }
    if($statusbtnextend)$monthkeyboard['inline_keyboard'][] = [['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]];
    if ($statuscustomvolume == true)$monthkeyboard['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => "customsellvolume"]];
    $monthkeyboard['inline_keyboard'][] = [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $callback_data_back]
            ];
    return json_encode($monthkeyboard);
}
$Startelegram = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه استار"]],
        [['text' => "💰 کش بک استار"],['text' => "📚 تنظیم آموزش استار"]],
        [['text' => "⬇️ حداقل مبلغ استار"],['text' => "⬆️ حداکثر مبلغ استار"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardchangelimit = json_encode([
    'keyboard' => [
        [['text' => "🆓 محدودیت رایگان"],['text' => "↙️ محدودیت کلی"]],
        [['text' => "🔄 ریست محدودیت کل کاربران"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategoryadmin(){
    global $pdo,$textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [['text' =>$row['remark']]];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    return json_encode($list_category);
}
$nowpayment_setting_keyboard = json_encode([
    'keyboard' => [
        [['text' => "API NOWPAYMENT"],['text' => "🗂 نام درگاه nowpayment"]],
        [['text' => "💰 کش بک nowpayment"],['text' => "📚 تنظیم آموزش nowpayment"]],
        [['text' => "⬇️ حداقل مبلغ nowpayment"],['text' => "⬆️ حداکثر مبلغ nowpayment"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Exception_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => "➕ استثناء کردن کاربر"],['text' => "❌ حذف کاربر از لیست"]],
        [['text' => "👁 نمایش لیست افراد"]],
        [['text' => "▶️ بازگشت به منوی تظنیمات کارت"]]
    ],
    'resize_keyboard' => true
]);
function keyboard_config($config_split,$id_invoice,$back_active = true){
    global $textbotlang;
    $keyboard_config = ['inline_keyboard' => []];
    $keyboard_config['inline_keyboard'][] = [
        ['text' => "⚙️ کانفیگ", 'callback_data' => "none"],
        ['text' => "✏️نام کانفیگ", 'callback_data' => "none"],
        ];
    for($i = 0; $i<count($config_split);$i++){
        $config = $config_split[$i];
        $split_config = explode("://",$config);
        $type_prtocol = $split_config[0];
        $split_config = $split_config[1];
        if(isBase64($split_config)){
            $split_config = base64_decode($split_config);
        }
        if($type_prtocol == "vmess"){
            $split_config = json_decode($split_config,true)['ps'];
        }elseif($type_prtocol == "ss"){
            $split_config = $split_config;
            $split_config = explode("#",$split_config)[1];
        }else{
        $split_config = explode("#",$split_config)[1];
        }
        $keyboard_config['inline_keyboard'][] = [
        ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i"],
        ['text' => urldecode($split_config), 'callback_data' => "none"],
        ];
        
    }
    $keyboard_config['inline_keyboard'][] = [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "configget_$id_invoice"."_1520"]];
    if($back_active){
    $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"]];
    }
    return json_encode($keyboard_config);
}
$keyboard_buy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🛍خرید اشتراک", 'callback_data' => 'buy'],
            ],
        ]
    ]);
$keyboard_stat = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "⏱️ آمار کل", 'callback_data' => 'stat_all_bot'],
                ],[
                    ['text' => "⏱️ یک ساعت اخیر", 'callback_data' => 'hoursago_stat'],
                ],
                [
                    ['text' => "⛅️ امروز", 'callback_data' => 'today_stat'],
                    ['text' => "☀️ دیروز", 'callback_data' => 'yesterday_stat'],
                ],
                [
                    ['text' => "☀️ ماه فعلی ", 'callback_data' => 'month_current_stat'],
                    ['text' => "⛅️ ماه قبل", 'callback_data' => 'month_old_stat'],
                ],
                [
                    ['text' => "🗓 مشاهده آمار در تاریخ مشخص", 'callback_data' => 'view_stat_time'],
                ]
            ]
        ]);