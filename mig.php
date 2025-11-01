<?php
require_once 'function.php';
require_once 'config.php';
require_once 'botapi.php';
global $connect;
function logError($message, $table = 'general') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$table] $message\n";
    file_put_contents('mig_error_log', $logMessage, FILE_APPEND);
    echo "خطا در $table: $message<br>";
}
function columnExists($table, $column) {
    global $connect;
    $result = $connect->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}
function cannotHaveDefault($type) {
    $type = strtolower($type);
    return strpos($type, 'text') !== false ||
           strpos($type, 'blob') !== false ||
           strpos($type, 'json') !== false ||
           strpos($type, 'geometry') !== false;
}
function safeAddColumn($table, $column, $type, $default = null, $after = null, $null = true) {
    global $connect;
    if (!columnExists($table, $column)) {
        $nullStr = $null ? 'NULL' : 'NOT NULL';
        $defaultStr = '';
     
        if ($default !== null && !cannotHaveDefault($type)) {
            if (is_numeric($default)) {
                $defaultStr = "DEFAULT $default";
            } else {
                $defaultStr = "DEFAULT '$default'";
            }
        }
     
        $afterStr = $after ? "AFTER `$after`" : '';
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $type $nullStr $defaultStr $afterStr";
     
        if ($connect->query($sql)) {
            echo "فیلد $column به جدول $table اضافه شد ✅<br>";
        
            $isNumeric = (strpos($type, 'int') !== false) ||
                        (strpos($type, 'decimal') !== false) ||
                        (strpos($type, 'float') !== false) ||
                        (strpos($type, 'double') !== false) ||
                        (strpos($type, 'bool') !== false);
        
            if ($isNumeric && $default !== null) {
                $connect->query("UPDATE IGNORE `$table` SET `$column` = $default WHERE `$column` IS NULL OR `$column` = ''");
            }
        } else {
            logError("خطا در اضافه کردن $column به $table: " . $connect->error, $table);
        }
    } else {
        echo "فیلد $column در جدول $table از قبل وجود دارد ✅<br>";
    }
}
function safeDropColumn($table, $column) {
    global $connect;
    if (columnExists($table, $column)) {
        $sql = "ALTER TABLE `$table` DROP COLUMN `$column`";
        if ($connect->query($sql)) {
            echo "فیلد $column از جدول $table حذف شد ✅<br>";
        } else {
            logError("خطا در حذف $column از $table: " . $connect->error, $table);
        }
    } else {
        echo "فیلد $column در جدول $table وجود ندارد ✅<br>";
    }
}
function updateDefaultValue($table, $column, $value) {
    global $connect;
    if (!columnExists($table, $column)) {
        return;
    }
    $columnInfo = $connect->query("SHOW COLUMNS FROM `$table` WHERE Field = '$column'");
    if ($columnInfo && $col = $columnInfo->fetch_assoc()) {
        $type = $col['Type'];
     
        if (cannotHaveDefault($type)) {
            return;
        }
     
        $isNumeric = (strpos($type, 'int') !== false) ||
                    (strpos($type, 'decimal') !== false) ||
                    (strpos($type, 'float') !== false) ||
                    (strpos($type, 'double') !== false) ||
                    (strpos($type, 'bool') !== false);
    
        $check = $connect->query("SELECT COUNT(*) as cnt FROM `$table` WHERE `$column` IS NULL OR `$column` = ''");
    
        if ($check && $check->fetch_assoc()['cnt'] > 0) {
            $setValue = '';
            if ($value === null) {
                $setValue = 'NULL';
            } elseif (is_numeric($value)) {
                $setValue = $value;
            } else {
                $setValue = "'" . $connect->real_escape_string($value) . "'";
            }
        
            $connect->query("UPDATE IGNORE `$table` SET `$column` = $setValue WHERE `$column` IS NULL OR `$column` = ''");
            echo "مقدار پیش‌فرض '$value' برای فیلد $column به‌روزرسانی شد ✅<br>";
        }
    }
}
function migrateTable($tableName, $targetStructure, $dropIfExists = false) {
    global $connect;
    $result = $connect->query("SHOW TABLES LIKE '$tableName'");
    $tableExists = ($result->num_rows > 0);
 
    if ($dropIfExists && $tableExists) {
        $connect->query("DROP TABLE `$tableName`");
        echo "جدول $tableName حذف شد ✅<br>";
        $tableExists = false;
    }
 
    if (!$tableExists) {
        $columns = [];
        foreach ($targetStructure as $col => $def) {
            $columns[] = "`$col` $def";
        }
        $createSql = "CREATE TABLE `$tableName` (" . implode(', ', $columns) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($connect->query($createSql)) {
            echo "جدول $tableName ایجاد شد ✅<br>";
        } else {
            logError("خطا در ایجاد جدول $tableName: " . $connect->error, $tableName);
            return;
        }
    } else {
        $existingColumns = [];
        $showColumns = $connect->query("SHOW COLUMNS FROM `$tableName`");
        while ($row = $showColumns->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
     
        foreach ($existingColumns as $col) {
            if (!array_key_exists($col, $targetStructure)) {
                safeDropColumn($tableName, $col);
            }
        }
     
        foreach ($targetStructure as $col => $def) {
            if (!in_array($col, $existingColumns)) {
                $defClean = trim($def);
                $isNotNull = strpos($defClean, 'NOT NULL') !== false;
                $null = !$isNotNull;
                $default = null;
            
                if (strpos($defClean, 'DEFAULT') !== false && !cannotHaveDefault($defClean)) {
                    if (preg_match("/DEFAULT\s+('([^']*)'|(\d+)|NULL)/i", $defClean, $matches)) {
                        if (isset($matches[3])) {
                            $default = (int)$matches[3];
                        } elseif (isset($matches[2])) {
                            $default = $matches[2];
                        } else {
                            $default = null;
                        }
                    }
                }
            
                $typeParts = explode(' ', $defClean);
                $type = '';
                $i = 0;
                while ($i < count($typeParts) && !in_array(strtoupper($typeParts[$i]), ['NULL', 'NOT', 'DEFAULT'])) {
                    $type .= $typeParts[$i] . ' ';
                    $i++;
                }
                $type = trim($type);
            
                safeAddColumn($tableName, $col, $type, $default, null, $null);
            }
        }
     
        foreach ($targetStructure as $col => $def) {
            if (strpos($def, 'DEFAULT') !== false && columnExists($tableName, $col) && !cannotHaveDefault($def)) {
                if (preg_match("/DEFAULT\s+('([^']*)'|(\d+)|NULL)/i", $def, $matches)) {
                    $defaultVal = null;
                    if (isset($matches[3])) {
                        $defaultVal = (int)$matches[3];
                    } elseif (isset($matches[2])) {
                        $defaultVal = $matches[2];
                    }
                    updateDefaultValue($tableName, $col, $defaultVal);
                }
            }
        }
    }
}
try {
    $connect->query("DROP TABLE IF EXISTS `setting`");
    echo "جدول setting قدیمی حذف شد ✅<br>";
} catch (Exception $e) {
    logError($e->getMessage(), 'setting_old');
}
try {
    $connect->query("UPDATE marzban_panel SET status = 'active' WHERE status = 'activepanel'");
    echo "مقادیر activepanel در جدول marzban_panel به active تغییر یافت ✅<br>";
} catch (Exception $e) {
    logError($e->getMessage(), 'marzban_panel');
}
try {
    $tableName = 'user';
    $targetStructure = [
        'id' => 'VARCHAR(500) PRIMARY KEY',
        'limit_usertest' => 'INT(100) NOT NULL DEFAULT 0',
        'roll_Status' => 'BOOL NOT NULL DEFAULT 0',
        'username' => 'VARCHAR(500) NOT NULL DEFAULT \'none\'',
        'Processing_value' => 'TEXT NOT NULL DEFAULT \'none\'',
        'Processing_value_one' => 'TEXT NOT NULL',
        'Processing_value_tow' => 'TEXT NOT NULL',
        'Processing_value_four' => 'TEXT NOT NULL DEFAULT \'\'',
        'step' => 'VARCHAR(500) NOT NULL',
        'description_blocking' => 'TEXT NULL',
        'number' => 'VARCHAR(300) NOT NULL DEFAULT \'none\'',
        'Balance' => 'INT(255) NOT NULL DEFAULT 0',
        'User_Status' => 'VARCHAR(500) NOT NULL',
        'pagenumber' => 'INT(10) NOT NULL DEFAULT 0',
        'message_count' => 'VARCHAR(100) NOT NULL DEFAULT \'0\'',
        'last_message_time' => 'VARCHAR(100) NOT NULL DEFAULT \'0\'',
        'agent' => 'VARCHAR(100) NOT NULL DEFAULT \'f\'',
        'affiliatescount' => 'VARCHAR(100) NOT NULL DEFAULT \'0\'',
        'affiliates' => 'VARCHAR(100) NOT NULL DEFAULT \'0\'',
        'namecustom' => 'VARCHAR(300) NOT NULL DEFAULT \'none\'',
        'number_username' => 'VARCHAR(300) NOT NULL DEFAULT \'100\'',
        'register' => 'VARCHAR(100) NOT NULL DEFAULT \'none\'',
        'verify' => 'VARCHAR(100) NOT NULL DEFAULT \'1\'',
        'cardpayment' => 'VARCHAR(100) NOT NULL DEFAULT \'1\'',
        'codeInvitation' => 'VARCHAR(100) NULL',
        'pricediscount' => 'VARCHAR(100) NULL DEFAULT \'0\'',
        'hide_mini_app_instruction' => 'VARCHAR(20) NULL DEFAULT \'0\'',
        'maxbuyagent' => 'VARCHAR(100) NULL DEFAULT \'0\'',
        'joinchannel' => 'VARCHAR(100) NULL DEFAULT \'0\'',
        'checkstatus' => 'VARCHAR(50) NULL DEFAULT \'0\'',
        'bottype' => 'TEXT NULL DEFAULT \'0\'',
        'score' => 'INT(255) NULL DEFAULT 0',
        'limitchangeloc' => 'VARCHAR(50) NULL DEFAULT \'0\'',
        'status_cron' => 'VARCHAR(20) NULL DEFAULT \'1\'',
        'expire' => 'VARCHAR(100) NULL',
        'token' => 'VARCHAR(100) NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $result = $connect->query("SHOW TABLES LIKE '$tableName'");
    if ($result->num_rows > 0) {
        $numericColumns = [];
        $showColumns = $connect->query("SHOW COLUMNS FROM `user`");
        while ($row = $showColumns->fetch_assoc()) {
            $type = $row['Type'];
            if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false ||
                strpos($type, 'float') !== false || strpos($type, 'double') !== false ||
                strpos($type, 'bool') !== false) {
                $numericColumns[] = $row['Field'];
            }
        }
    
        foreach ($numericColumns as $col) {
            $connect->query("UPDATE IGNORE `user` SET `$col` = 0 WHERE `$col` = '' OR `$col` IS NULL");
        }
        echo "داده‌های عددی جدول user پاکسازی شد ✅<br>";
    
        $textNumericColumns = ['Balance', 'score', 'pagenumber', 'limit_usertest'];
        foreach ($textNumericColumns as $col) {
            if (columnExists('user', $col)) {
                $connect->query("UPDATE IGNORE `user` SET `$col` = 0 WHERE `$col` = '' OR `$col` IS NULL OR `$col` NOT REGEXP '^[0-9]+$'");
            }
        }
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'user');
}
try {
    $tableName = 'help';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'name_os' => 'VARCHAR(500) NOT NULL',
        'Media_os' => 'VARCHAR(5000) NOT NULL',
        'type_Media_os' => 'VARCHAR(500) NOT NULL',
        'category' => 'TEXT NOT NULL',
        'Description_os' => 'TEXT NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'help');
}
try {
    $tableName = 'setting';
    $DATAAWARD = json_encode(array('one' => "0", "tow" => "0", "theree" => "0"));
    $limitlist = json_encode(array('free' => 100, 'all' => 100));
    $status_cron = json_encode(array(
        'day' => true, 'volume' => true, 'remove' => false, 'remove_volume' => false,
        'test' => false, 'on_hold' => false, 'uptime_node' => false, 'uptime_panel' => false
    ));
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    $targetStructure = [
        'Bot_Status' => "VARCHAR(200) NULL DEFAULT 'botstatuson'",
        'roll_Status' => "VARCHAR(200) NULL DEFAULT 'rolleon'",
        'get_number' => "VARCHAR(200) NULL DEFAULT 'onAuthenticationphone'",
        'iran_number' => "VARCHAR(200) NULL DEFAULT 'offAuthenticationiran'",
        'NotUser' => "VARCHAR(200) NULL DEFAULT 'offnotuser'",
        'Channel_Report' => "VARCHAR(600) NULL DEFAULT '0'",
        'limit_usertest_all' => "VARCHAR(600) NULL DEFAULT '1'",
        'affiliatesstatus' => "VARCHAR(600) NULL DEFAULT 'offaffiliates'",
        'affiliatespercentage' => "VARCHAR(600) NULL DEFAULT '0'",
        'removedayc' => "VARCHAR(600) NULL DEFAULT '1'",
        'showcard' => "VARCHAR(200) NULL DEFAULT '1'",
        'numbercount' => "VARCHAR(600) NULL DEFAULT '0'",
        'statusnewuser' => "VARCHAR(600) NULL DEFAULT 'onnewuser'",
        'statusagentrequest' => "VARCHAR(600) NULL DEFAULT 'onrequestagent'",
        'statuscategory' => "VARCHAR(200) NULL DEFAULT 'offcategory'",
        'statusterffh' => "VARCHAR(200) NULL",
        'volumewarn' => "VARCHAR(200) NULL DEFAULT '2'",
        'inlinebtnmain' => "VARCHAR(200) NULL DEFAULT 'offinline'",
        'verifystart' => "VARCHAR(200) NULL DEFAULT 'offverify'",
        'id_support' => "VARCHAR(200) NULL DEFAULT '0'",
        'statusnamecustom' => "VARCHAR(100) NULL DEFAULT 'offnamecustom'",
        'statuscategorygenral' => "VARCHAR(100) NULL DEFAULT 'offcategorys'",
        'statussupportpv' => "VARCHAR(100) NULL DEFAULT 'offpvsupport'",
        'agentreqprice' => "VARCHAR(100) NULL DEFAULT '0'",
        'bulkbuy' => "VARCHAR(100) NULL DEFAULT 'onbulk'",
        'on_hold_day' => "VARCHAR(100) NULL DEFAULT '4'",
        'cronvolumere' => "VARCHAR(100) NULL DEFAULT '5'",
        'verifybucodeuser' => "VARCHAR(100) NULL DEFAULT 'offverify'",
        'scorestatus' => "VARCHAR(100) NULL DEFAULT '0'",
        'Lottery_prize' => "TEXT NULL",
        'wheelـluck' => "VARCHAR(45) NULL DEFAULT '0'",
        'wheelـluck_price' => "VARCHAR(45) NULL DEFAULT '0'",
        'btn_status_extned' => "VARCHAR(45) NULL DEFAULT '0'",
        'daywarn' => "VARCHAR(45) NULL DEFAULT '2'",
        'categoryhelp' => "VARCHAR(45) NULL DEFAULT '0'",
        'linkappstatus' => "VARCHAR(45) NULL DEFAULT '0'",
        'iplogin' => "VARCHAR(45) NULL DEFAULT '0'",
        'wheelagent' => "VARCHAR(45) NULL DEFAULT '1'",
        'Lotteryagent' => "VARCHAR(45) NULL DEFAULT '1'",
        'languageen' => "VARCHAR(45) NULL DEFAULT '0'",
        'languageru' => "VARCHAR(45) NULL DEFAULT '0'",
        'statusfirstwheel' => "VARCHAR(45) NULL DEFAULT '0'",
        'statuslimitchangeloc' => "VARCHAR(45) NULL DEFAULT '0'",
        'Debtsettlement' => "VARCHAR(45) NULL DEFAULT '1'",
        'Dice' => "VARCHAR(45) NULL DEFAULT '0'",
        'keyboardmain' => "TEXT NOT NULL",
        'statusnoteforf' => "VARCHAR(45) NOT NULL DEFAULT '1'",
        'statuscopycart' => "VARCHAR(45) NOT NULL DEFAULT '0'",
        'timeauto_not_verify' => "VARCHAR(20) NOT NULL DEFAULT '4'",
        'status_keyboard_config' => "VARCHAR(20) NULL DEFAULT '1'",
        'cron_status' => "TEXT NOT NULL",
        'limitnumber' => "VARCHAR(200) NULL"
    ];
    migrateTable($tableName, $targetStructure, true);
    $insertSql = "INSERT INTO `$tableName` (Bot_Status,roll_Status,get_number,limit_usertest_all,iran_number,NotUser,affiliatesstatus,affiliatespercentage,removedayc,showcard,statuscategory,numbercount,statusnewuser,statusagentrequest,volumewarn,inlinebtnmain,verifystart,statussupportpv,statusnamecustom,statuscategorygenral,agentreqprice,cronvolumere,bulkbuy,on_hold_day,verifybucodeuser,scorestatus,Lottery_prize,wheelـluck,wheelـluck_price,iplogin,daywarn,categoryhelp,linkappstatus,languageen,languageru,wheelagent,Lotteryagent,statusfirstwheel,statuslimitchangeloc,limitnumber,Debtsettlement,Dice,keyboardmain,statusnoteforf,statuscopycart,timeauto_not_verify,status_keyboard_config,cron_status) VALUES ('botstatuson','rolleon','onAuthenticationphone','1','offAuthenticationiran','offnotuser','offaffiliates','0','1','1','offcategory','0','onnewuser','onrequestagent','2','offinline','offverify','offpvsupport','offnamecustom','offcategorys','0','5','onbulk','4','offverify','0','$DATAAWARD','0','0','0','2','0','0','0','0','1','1','0','0','$limitlist','1','0','$keyboardmain','1','0','4','1','$status_cron')";
    $connect->query($insertSql);
    echo "داده‌های پیش‌فرض به جدول setting اضافه شد ✅<br>";
} catch (Exception $e) {
    logError($e->getMessage(), 'setting');
}
try {
    $tableName = 'admin';
    $targetStructure = [
        'id_admin' => 'VARCHAR(500) PRIMARY KEY NOT NULL',
        'username' => 'VARCHAR(1000) NOT NULL',
        'password' => 'VARCHAR(1000) NOT NULL',
        'rule' => 'VARCHAR(500) NOT NULL DEFAULT \'administrator\''
    ];
    migrateTable($tableName, $targetStructure);
    $count = $connect->query("SELECT COUNT(*) as cnt FROM `$tableName`")->fetch_assoc()['cnt'];
    if ($count == 0) {
        $randomString = bin2hex(random_bytes(5));
        $insertSql = "INSERT INTO `$tableName` (id_admin, rule, username, password) VALUES ('$adminnumber', 'administrator', 'admin', '$randomString')";
        $connect->query($insertSql);
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'admin');
}
try {
    $tableName = 'channels';
    $targetStructure = [
        'remark' => 'VARCHAR(200) NOT NULL',
        'linkjoin' => 'VARCHAR(200) NOT NULL',
        'link' => 'VARCHAR(200) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'channels');
}
try {
    $tableName = 'marzban_panel';
    $VALUE = json_encode(array('f' => '0', 'n' => '0', 'n2' => '0'));
    $valueprice = json_encode(array('f' => "4000", 'n' => "4000", 'n2' => "4000"));
    $valuemain = json_encode(array('f' => "1", 'n' => "1", 'n2' => "1"));
    $valuemax = json_encode(array('f' => "1000", 'n' => "1000", 'n2' => "1000"));
    $valuemax_time = json_encode(array('f' => "365", 'n' => "365", 'n2' => "365"));
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'code_panel' => 'VARCHAR(200) NULL',
        'name_panel' => 'VARCHAR(2000) NULL',
        'status' => 'VARCHAR(500) NULL DEFAULT \'active\'',
        'url_panel' => 'VARCHAR(2000) NULL',
        'username_panel' => 'VARCHAR(200) NULL',
        'password_panel' => 'VARCHAR(200) NULL',
        'agent' => 'VARCHAR(200) NULL DEFAULT \'all\'',
        'sublink' => 'VARCHAR(500) NULL DEFAULT \'onsublink\'',
        'config' => 'VARCHAR(500) NULL DEFAULT \'offconfig\'',
        'MethodUsername' => 'VARCHAR(700) NULL DEFAULT \'آیدی عددی + حروف و عدد رندوم\'',
        'TestAccount' => 'VARCHAR(100) NULL DEFAULT \'ONTestAccount\'',
        'limit_panel' => 'VARCHAR(100) NULL DEFAULT \'unlimted\'',
        'namecustom' => 'VARCHAR(100) NULL DEFAULT \'vpn\'',
        'Methodextend' => 'VARCHAR(100) NULL DEFAULT \'ریست حجم و زمان\'',
        'conecton' => 'VARCHAR(100) NULL DEFAULT \'offconecton\'',
        'linksubx' => 'VARCHAR(1000) NULL',
        'inboundid' => 'VARCHAR(100) NULL DEFAULT \'1\'',
        'type' => 'VARCHAR(100) NULL DEFAULT \'marzban\'',
        'inboundstatus' => 'VARCHAR(100) NULL DEFAULT \'offinbounddisable\'',
        'hosts' => 'JSON NULL',
        'inbound_deactive' => 'VARCHAR(100) NULL DEFAULT \'0\'',
        'time_usertest' => 'VARCHAR(100) NULL DEFAULT \'1\'',
        'val_usertest' => 'VARCHAR(100) NULL DEFAULT \'100\'',
        'secret_code' => 'VARCHAR(200) NULL',
        'priceChangeloc' => 'VARCHAR(200) NULL DEFAULT \'0\'',
        'priceextravolume' => "VARCHAR(500) NULL",
        'pricecustomvolume' => "VARCHAR(500) NULL",
        'pricecustomtime' => "VARCHAR(500) NULL",
        'priceextratime' => "VARCHAR(500) NULL",
        'mainvolume' => "VARCHAR(500) NULL",
        'maxvolume' => "VARCHAR(500) NULL",
        'maintime' => "VARCHAR(500) NULL",
        'maxtime' => "VARCHAR(500) NULL",
        'status_extend' => 'VARCHAR(100) NULL DEFAULT \'on_extend\'',
        'datelogin' => 'TEXT NULL',
        'proxies' => 'TEXT NULL',
        'inbounds' => 'TEXT NULL',
        'subvip' => 'VARCHAR(60) NULL DEFAULT \'offsubvip\'',
        'changeloc' => 'VARCHAR(60) NULL DEFAULT \'offchangeloc\'',
        'on_hold_test' => 'VARCHAR(60) NOT NULL DEFAULT \'1\'',
        'customvolume' => "TEXT NULL",
        'hide_user' => 'TEXT NULL'
    ];
    migrateTable($tableName, $targetStructure);
 
    $connect->query("UPDATE marzban_panel SET priceextravolume = '$valueprice' WHERE priceextravolume IS NULL OR priceextravolume = ''");
    $connect->query("UPDATE marzban_panel SET pricecustomvolume = '$valueprice' WHERE pricecustomvolume IS NULL OR pricecustomvolume = ''");
    $connect->query("UPDATE marzban_panel SET pricecustomtime = '$valueprice' WHERE pricecustomtime IS NULL OR pricecustomtime = ''");
    $connect->query("UPDATE marzban_panel SET priceextratime = '$valueprice' WHERE priceextratime IS NULL OR priceextratime = ''");
    $connect->query("UPDATE marzban_panel SET mainvolume = '$valuemain' WHERE mainvolume IS NULL OR mainvolume = ''");
    $connect->query("UPDATE marzban_panel SET maxvolume = '$valuemax' WHERE maxvolume IS NULL OR maxvolume = ''");
    $connect->query("UPDATE marzban_panel SET maintime = '$valuemain' WHERE maintime IS NULL OR maintime = ''");
    $connect->query("UPDATE marzban_panel SET maxtime = '$valuemax_time' WHERE maxtime IS NULL OR maxtime = ''");
    $connect->query("UPDATE marzban_panel SET customvolume = '$VALUE' WHERE customvolume IS NULL OR customvolume = ''");
 
    $max_stmt = $connect->query("SELECT MAX(CAST(SUBSTRING(code_panel, 3) AS UNSIGNED)) as max_num FROM marzban_panel WHERE code_panel LIKE '7e%'");
    $max_row = $max_stmt->fetch_assoc();
    $next_num = $max_row['max_num'] ? (int)$max_row['max_num'] + 1 : 15;
    $stmt = $connect->query("SELECT id FROM marzban_panel WHERE code_panel IS NULL OR code_panel = ''");
    while ($row = $stmt->fetch_assoc()) {
        $code = '7e' . $next_num;
        $connect->query("UPDATE marzban_panel SET code_panel = '$code' WHERE id = " . $row['id']);
        $next_num++;
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'marzban_panel');
}
try {
    $tableName = 'product';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'code_product' => 'VARCHAR(200) NULL',
        'name_product' => 'VARCHAR(2000) NULL',
        'price_product' => 'VARCHAR(2000) NULL',
        'Volume_constraint' => 'VARCHAR(2000) NULL',
        'Location' => 'VARCHAR(200) NULL',
        'Service_time' => 'VARCHAR(200) NULL',
        'agent' => 'VARCHAR(100) NULL DEFAULT \'f\'',
        'note' => 'TEXT NULL DEFAULT \'\'',
        'data_limit_reset' => 'VARCHAR(200) NULL DEFAULT \'no_reset\'',
        'one_buy_status' => 'VARCHAR(20) NOT NULL DEFAULT \'0\'',
        'inbounds' => 'TEXT NULL',
        'proxies' => 'TEXT NULL',
        'category' => 'VARCHAR(400) NULL',
        'hide_panel' => 'TEXT NOT NULL DEFAULT \'{}\''
    ];
    migrateTable($tableName, $targetStructure);
 
} catch (Exception $e) {
    logError($e->getMessage(), 'product');
}
try {
    $tableName = 'invoice';
    $targetStructure = [
        'id_invoice' => 'VARCHAR(200) PRIMARY KEY',
        'id_user' => 'VARCHAR(200) NULL',
        'username' => 'VARCHAR(300) NULL',
        'Service_location' => 'VARCHAR(300) NULL',
        'time_sell' => 'VARCHAR(200) NULL',
        'name_product' => 'VARCHAR(200) NULL',
        'price_product' => 'VARCHAR(200) NULL',
        'Volume' => 'VARCHAR(200) NULL',
        'Service_time' => 'VARCHAR(200) NULL',
        'uuid' => 'TEXT NULL',
        'note' => 'VARCHAR(500) NULL',
        'user_info' => 'TEXT NULL',
        'bottype' => 'VARCHAR(200) NULL',
        'refral' => 'VARCHAR(100) NULL',
        'time_cron' => 'VARCHAR(100) NULL',
        'notifctions' => 'TEXT NOT NULL',
        'Status' => 'VARCHAR(200) NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $data = json_encode(array('volume' => false, 'time' => false));
    $connect->query("UPDATE invoice SET notifctions = '$data' WHERE notifctions IS NULL OR notifctions = ''");
} catch (Exception $e) {
    logError($e->getMessage(), 'invoice');
}
try {
    $tableName = 'Payment_report';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'id_user' => 'VARCHAR(200) NULL',
        'id_order' => 'VARCHAR(2000) NULL',
        'time' => 'VARCHAR(200) NULL',
        'at_updated' => 'VARCHAR(200) NULL',
        'price' => 'VARCHAR(200) NULL',
        'dec_not_confirmed' => 'TEXT NULL',
        'Payment_Method' => 'VARCHAR(400) NULL',
        'payment_Status' => 'VARCHAR(100) NULL',
        'bottype' => 'VARCHAR(300) NULL',
        'message_id' => 'INT NULL',
        'id_invoice' => 'VARCHAR(1000) NULL DEFAULT \'none\''
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'Payment_report');
}
try {
    $tableName = 'Discount';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'code' => 'VARCHAR(2000) NULL',
        'price' => 'VARCHAR(200) NULL',
        'limituse' => 'VARCHAR(200) NULL',
        'limitused' => 'VARCHAR(200) NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'Discount');
}
try {
    $tableName = 'Giftcodeconsumed';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'code' => 'VARCHAR(2000) NULL',
        'id_user' => 'VARCHAR(200) NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'Giftcodeconsumed');
}
try {
    $tableName = 'textbot';
    $text_roll = "♨️ قوانین استفاده از خدمات ما\n1- به اطلاعیه هایی که داخل کانال گذاشته می شود حتما توجه کنید.\n2- در صورتی که اطلاعیه ای در مورد قطعی در کانال گذاشته نشده به اکانت پشتیبانی پیام دهید\n3- سرویس ها را از طریق پیامک ارسال نکنید برای ارسال پیامک می توانید از طریق ایمیل ارسال کنید.";
    $text_dec_fq = " 💡 سوالات متداول ⁉️\n1️⃣ فیلترشکن شما آیپی ثابته؟ میتونم برای صرافی های ارز دیجیتال استفاده کنم？\n✅ به دلیل وضعیت نت و محدودیت های کشور سرویس ما مناسب ترید نیست و فقط لوکیشن‌ ثابته.\n2️⃣ اگه قبل از منقضی شدن اکانت، تمدیدش کنم روزهای باقی مانده می سوزد？\n✅ خیر، روزهای باقیمونده اکانت موقع تمدید حساب میشن و اگه مثلا 5 روز قبل از منقضی شدن اکانت 1 ماهه خودتون اون رو تمدید کنید 5 روز باقیمونده + 30 روز تمدید میشه.\n3️⃣ اگه به یک اکانت بیشتر از حد مجاز متصل شیم چه اتفاقی میافته？\n✅ در این صورت حجم سرویس شما زود تمام خواهد شد.\n4️⃣ فیلترشکن شما از چه نوعیه？\n✅ فیلترشکن های ما v2ray است و پروتکل‌های مختلفی رو ساپورت میکنیم تا حتی تو دورانی که اینترنت اختلال داره بدون مشکل و افت سرعت بتونید از سرویستون استفاده کنید.\n5️⃣ فیلترشکن از کدوم کشور است？\n✅ سرور فیلترشکن ما از کشور آلمان است\n6️⃣ چطور باید از این فیلترشکن استفاده کنم？\n✅ برای آموزش استفاده از برنامه، روی دکمه «📚 آموزش» بزنید.\n7️⃣ فیلترشکن وصل نمیشه، چیکار کنم？\n✅ به همراه یک عکس از پیغام خطایی که میگیرید به پشتیبانی مراجعه کنید.\n8️⃣ فیلترشکن شما تضمینی هست که همیشه مواقع متصل بشه？\n✅ به دلیل قابل پیش‌بینی نبودن وضعیت نت کشور، امکان دادن تضمین نیست فقط می‌تونیم تضمین کنیم که تمام تلاشمون رو برای ارائه سرویس هر چه بهتر انجام بدیم.\n9️⃣ امکان بازگشت وجه دارید？\n✅ امکان بازگشت وجه در صورت حل نشدن مشکل از سمت ما وجود دارد.\n💡 در صورتی که جواب سوالتون رو نگرفتید میتونید به «پشتیبانی» مراجعه کنید.";
    $text_channel = "⚠️ کاربر گرامی؛ شما عضو چنل ما نیستید\nاز طریق دکمه زیر وارد کانال شده و عضو شوید\nپس از عضویت دکمه بررسی عضویت را کلیک کنید";
    $text_invoice = "📇 پیش فاکتور شما:\n👤 نام کاربری: {username}\n🔐 نام سرویس: {name_product}\n📆 مدت اعتبار: {Service_time} روز\n💶 قیمت: {price} تومان\n👥 حجم اکانت: {Volume} گیگ\n🗒 یادداشت محصول : {note}\n💵 موجودی کیف پول شما : {userBalance}\n \n💰 سفارش شما آماده پرداخت است";
    $textafterpay = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس : {username}\n🌿 نام سرویس: {name_service}\n‏🇺🇳 لوکیشن: {location}\n⏳ مدت زمان: {day} روز\n🗜 حجم سرویس: {volume} گیگابایت\n\nلینک اتصال:\n{config}\n{links}\n🧑‍🦯 شما میتوانید شیوه اتصال را با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $text_wgdashboard = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس : {username}\n🌿 نام سرویس: {name_service}\n‏🇺🇳 لوکیشن: {location}\n⏳ مدت زمان: {day} روز\n🗜 حجم سرویس: {volume} گیگابایت\n\n🧑‍🦯 شما میتوانید شیوه اتصال را با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textafterpayibsng = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس : {username}\n🔑 رمز عبور سرویس : <code>{password}</code>\n🌿 نام سرویس: {name_service}\n‏🇺🇳 لوکیشن: {location}\n⏳ مدت زمان: {day} روز\n🗜 حجم سرویس: {volume} گیگابایت\n\n🧑‍🦯 شما میتوانید شیوه اتصال را با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textmanual = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس : {username}\n🌿 نام سرویس: {name_service}\n‏🇺🇳 لوکیشن: {location}\n\n اطلاعات سرویس :\n{config}\n🧑‍🦯 شما میتوانید شیوه اتصال را با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textaftertext = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس : {username}\n🌿 نام سرویس: {name_service}\n‏🇺🇳 لوکیشن: {location}\n⏳ مدت زمان: {day} ساعت\n🗜 حجم سرویس: {volume} مگابایت\n\nلینک اتصال:\n{config}\n🧑‍🦯 شما میتوانید شیوه اتصال را با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    $textconfigtest = "با سلام خدمت شما کاربر گرامی \nسرویس تست شما با نام کاربری {username} به پایان رسیده است\nامیدواریم تجربه‌ی خوبی از آسودگی و سرعت سرویستون داشته باشین. در صورتی که از سرویس‌ تست خودتون راضی بودین، میتونید سرویس اختصاصی خودتون رو تهیه کنید و از داشتن اینترنت آزاد با نهایت کیفیت لذت ببرید😉🔥\n🛍 برای تهیه سرویس با کیفیت می توانید از دکمه زیر استفاده نمایید";
    $textcart = "برای افزایش موجودی، مبلغ <code>{price}</code> تومان را به شماره‌ی حساب زیر واریز کنید 👇🏻\n \n ==================== \n <code>{card_number}</code>\n {name_card}\n ====================\n\n❌ این تراکنش به مدت یک ساعت اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد. \n‼مبلغ باید همان مبلغی که در بالا ذکر شده واریز نمایید.\n‼️امکان برداشت وجه از کیف پول نیست.\n‼️مسئولیت واریز اشتباهی با شماست.\n🔝بعد از پرداخت دکمه پرداخت کردم را زده سپس تصویر رسید را ارسال نمایید\n💵بعد از تایید پرداختتون توسط ادمین کیف پول شما شارژ خواهد شد و در صورتی که سفارشی داشته باشین انجام خواهد شد";
    $textcartauto = "برای تایید فوری لطفا دقیقاً مبلغ زیر واریز شود. در غیر این صورت تایید پرداخت شما ممکن است با تاخیر مواجه شود.⚠️\n برای افزایش موجودی، مبلغ <code>{price}</code> ریال را به شماره‌ی حساب زیر واریز کنید 👇🏻\n\n ==================== \n <code>{card_number}</code>\n {name_card}\n ====================\n \n\n💰دقیقا مبلغی را که در بالا ذکر شده واریز نمایید تا بصورت آنی تایید شود.\n‼️امکان برداشت وجه از کیف پول نیست.\n🔝لزومی به ارسال رسید نیست، اما در صورتی که بعد از گذشت مدتی واریز شما تایید نشد، عکس رسید خود را ارسال کنید.";
    $targetStructure = [
        'id_text' => 'VARCHAR(600) PRIMARY KEY NOT NULL',
        'text' => 'TEXT NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $insertQueries = [
        ['text_start', 'سلام خوش آمدید'],
        ['text_usertest', '🔑 اکانت تست'],
        ['text_Purchased_services', '🛍 سرویس های من'],
        ['text_support', '☎️ پشتیبانی'],
        ['text_help', '📚 آموزش'],
        ['text_bot_off', '❌ ربات خاموش است، لطفا دقایقی دیگر مراجعه کنید'],
        ['text_roll', $text_roll],
        ['text_fq', '❓ سوالات متداول'],
        ['text_dec_fq', $text_dec_fq],
        ['text_sell', '🔐 خرید اشتراک'],
        ['text_Add_Balance', '💰 افزایش موجودی'],
        ['text_channel', $text_channel],
        ['text_Discount', '🎁 کد هدیه'],
        ['text_Tariff_list', '💵 تعرفه اشتراک ها'],
        ['text_dec_Tariff_list', 'تنظیم نشده است'],
        ['text_Account_op', '🎛 حساب کاربری'],
        ['text_affiliates', '👥 زیر مجموعه گیری'],
        ['text_pishinvoice', $text_invoice],
        ['accountwallet', '🏦 کیف پول + شارژ'],
        ['carttocart', '💳 کارت به کارت'],
        ['textnowpayment', '💵 پرداخت ارزی 1'],
        ['textnowpaymenttron', '💵 واریز رمزارز ترون'],
        ['textsnowpayment', '💸 پرداخت با ارز دیجیتال'],
        ['iranpay1', '💸 درگاه پرداخت ریالی'],
        ['iranpay2', '💸 درگاه پرداخت ریالی دوم'],
        ['iranpay3', '💸 درگاه پرداخت ریالی سوم'],
        ['aqayepardakht', '🔵 درگاه آقای پرداخت'],
        ['mowpayment', '💸 پرداخت با ارز دیجیتال'],
        ['zarinpal', '🟡 زرین پال'],
        ['textafterpay', $textafterpay],
        ['textafterpayibsng', $textafterpayibsng],
        ['textaftertext', $textaftertext],
        ['textmanual', $textmanual],
        ['textselectlocation', '📌 موقعیت سرویس را انتخاب نمایید.'],
        ['crontest', $textconfigtest],
        ['textpaymentnotverify', 'درگاه ریالی'],
        ['textrequestagent', '👨‍💻 درخواست نمایندگی'],
        ['textpanelagent', '👨‍💻 پنل نمایندگی'],
        ['text_wheel_luck', '🎲 گردونه شانس'],
        ['text_cart', $textcart],
        ['text_cart_auto', $textcartauto],
        ['text_star_telegram', "💫 Star Telegram"],
        ['text_request_agent_dec', '📌 توضیحات خود را برای ثبت درخواست نمایندگی ارسال نمایید.'],
        ['text_extend', '♻️ تمدید سرویس'],
        ['text_wgdashboard', $text_wgdashboard]
    ];
    foreach ($insertQueries as $query) {
        $connect->query("INSERT IGNORE INTO textbot (id_text, text) VALUES ('" . $connect->real_escape_string($query[0]) . "', '" . $connect->real_escape_string($query[1]) . "')");
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'textbot');
}
try {
    $tableName = 'PaySetting';
    $main = 20000;
    $max = 1000000;
    $targetStructure = [
        'NamePay' => 'VARCHAR(500) PRIMARY KEY NOT NULL',
        'ValuePay' => 'TEXT NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $settings = [
        ['Cartstatus', 'oncard'],
        ['CartDirect', '@cart'],
        ['cardnumber', '603700000000'],
        ['namecard', 'تنظیم نشده'],
        ['Cartstatuspv', 'offcardpv'],
        ['apinowpayment', '0'],
        ['nowpaymentstatus', 'offnowpayment'],
        ['digistatus', 'offdigi'],
        ['statusSwapWallet', 'offnSolutions'],
        ['statusaqayepardakht', 'offaqayepardakht'],
        ['merchant_id_aqayepardakht', '0'],
        ['minbalance', (string)$main],
        ['maxbalance', (string)$max],
        ['marchent_tronseller', '0'],
        ['walletaddress', '0'],
        ['statuscardautoconfirm', 'offautoconfirm'],
        ['urlpaymenttron', 'https://tronseller.storeddownloader.fun/api/GetOrderToken'],
        ['statustarnado', 'offternado'],
        ['apiternado', '0'],
        ['chashbackcart', '0'],
        ['chashbackstar', '0'],
        ['chashbackperfect', '0'],
        ['chashbackaqaypardokht', '0'],
        ['chashbackiranpay1', '0'],
        ['chashbackiranpay2', '0'],
        ['chashbackplisio', '0'],
        ['chashbackzarinpal', '0'],
        ['checkpaycartfirst', 'offpayverify'],
        ['zarinpalstatus', 'offzarinpal'],
        ['merchant_zarinpal', '0'],
        ['minbalancecart', (string)$main],
        ['maxbalancecart', (string)$max],
        ['minbalancestar', (string)$main],
        ['maxbalancestar', (string)$max],
        ['minbalanceplisio', (string)$main],
        ['maxbalanceplisio', (string)$max],
        ['minbalancedigitaltron', (string)$main],
        ['maxbalancedigitaltron', (string)$max],
        ['minbalanceiranpay1', (string)$main],
        ['maxbalanceiranpay1', (string)$max],
        ['minbalanceiranpay2', (string)$main],
        ['maxbalanceiranpay2', (string)$max],
        ['minbalanceaqayepardakht', (string)$main],
        ['maxbalanceaqayepardakht', (string)$max],
        ['minbalancepaynotverify', (string)$main],
        ['maxbalancepaynotverify', (string)$max],
        ['minbalanceperfect', (string)$main],
        ['maxbalanceperfect', (string)$max],
        ['minbalancezarinpal', (string)$main],
        ['maxbalancezarinpal', (string)$max],
        ['minbalanceiranpay', (string)$main],
        ['maxbalanceiranpay', (string)$max],
        ['minbalancenowpayment', (string)$main],
        ['maxbalancenowpayment', (string)$max],
        ['statusiranpay3', 'oniranpay3'],
        ['apiiranpay', '0'],
        ['chashbackiranpay3', '0'],
        ['helpcart', '2'],
        ['helpaqayepardakht', '2'],
        ['helpstar', '2'],
        ['helpplisio', '2'],
        ['helpiranpay1', '2'],
        ['helpiranpay2', '2'],
        ['helpiranpay3', '2'],
        ['helpperfectmony', '2'],
        ['helpzarinpal', '2'],
        ['helpnowpayment', '2'],
        ['helpofflinearze', '2'],
        ['autoconfirmcart', 'offauto'],
        ['cashbacknowpayment', '0'],
        ['statusstar', '0'],
        ['statusnowpayment', '0'],
        ['Exception_auto_cart', '{}'],
        ['marchent_floypay', '0'],
    ];
    foreach ($settings as $setting) {
        $connect->query("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES ('" . $connect->real_escape_string($setting[0]) . "', '" . $connect->real_escape_string($setting[1]) . "')");
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'PaySetting');
}
try {
    $tableName = 'DiscountSell';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'codeDiscount' => 'VARCHAR(1000) NOT NULL',
        'price' => 'VARCHAR(200) NOT NULL',
        'limitDiscount' => 'VARCHAR(500) NOT NULL',
        'agent' => 'VARCHAR(500) NOT NULL DEFAULT \'f\'',
        'usefirst' => 'VARCHAR(100) NOT NULL DEFAULT 0',
        'useuser' => 'VARCHAR(100) NOT NULL DEFAULT 0',
        'code_product' => 'VARCHAR(100) NOT NULL',
        'code_panel' => 'VARCHAR(100) NOT NULL',
        'time' => 'VARCHAR(100) NOT NULL',
        'type' => 'VARCHAR(100) NOT NULL',
        'usedDiscount' => 'VARCHAR(500) NOT NULL DEFAULT 0'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'DiscountSell');
}
try {
    $tableName = 'affiliates';
    $targetStructure = [
        'description' => 'TEXT NULL DEFAULT \'none\'',
        'status_commission' => 'VARCHAR(200) NULL DEFAULT \'oncommission\'',
        'Discount' => 'VARCHAR(200) NULL DEFAULT \'onDiscountaffiliates\'',
        'price_Discount' => 'VARCHAR(200) NULL DEFAULT \'0\'',
        'porsant_one_buy' => 'VARCHAR(100) NULL DEFAULT \'off_buy_porsant\'',
        'id_media' => 'VARCHAR(300) NULL DEFAULT \'none\''
    ];
    migrateTable($tableName, $targetStructure);
    $count = $connect->query("SELECT COUNT(*) as cnt FROM `$tableName`")->fetch_assoc()['cnt'];
    if ($count == 0) {
        $connect->query("INSERT INTO affiliates (description,id_media,status_commission,Discount,porsant_one_buy) VALUES ('none','none','oncommission','onDiscountaffiliates','off_buy_porsant')");
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'affiliates');
}
try {
    $tableName = 'shopSetting';
    $agent_cashback = json_encode(array('n' => 0, 'n2' => 0));
    $targetStructure = [
        'Namevalue' => 'VARCHAR(500) PRIMARY KEY NOT NULL',
        'value' => 'TEXT NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $inserts = [
        ['customvolmef','4000'],
        ['customvolmen','4000'],
        ['customvolmen2','4000'],
        ['statusextra','offextra'],
        ['customtimepricef','4000'],
        ['customtimepricen','4000'],
        ['customtimepricen2','4000'],
        ['statusdirectpabuy','ondirectbuy'],
        ['minbalancebuybulk','0'],
        ['statustimeextra','ontimeextraa'],
        ['statusdisorder','offdisorder'],
        ['statuschangeservice','onstatus'],
        ['statusshowprice','offshowprice'],
        ['configshow','onconfig'],
        ['backserviecstatus','on'],
        ['chashbackextend','0'],
        ['chashbackextend_agent', $agent_cashback]
    ];
    foreach ($inserts as $ins) {
        $connect->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('" . $connect->real_escape_string($ins[0]) . "', '" . $connect->real_escape_string($ins[1]) . "')");
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'shopSetting');
}
try {
    $tableName = 'cancel_service';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'id_user' => 'VARCHAR(500) NOT NULL',
        'username' => 'VARCHAR(1000) NOT NULL',
        'description' => 'TEXT NOT NULL',
        'status' => 'VARCHAR(1000) NOT NULL DEFAULT \'pending\''
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'cancel_service');
}
try {
    $tableName = 'service_other';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'id_user' => 'VARCHAR(500) NOT NULL',
        'username' => 'VARCHAR(1000) NOT NULL',
        'value' => 'VARCHAR(1000) NOT NULL',
        'time' => 'VARCHAR(200) NOT NULL',
        'price' => 'VARCHAR(200) NOT NULL DEFAULT \'0\'',
        'type' => 'VARCHAR(1000) NOT NULL',
        'status' => 'VARCHAR(200) NOT NULL DEFAULT \'pending\'',
        'output' => 'TEXT NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'service_other');
}
try {
    $tableName = 'card_number';
    $targetStructure = [
        'cardnumber' => 'VARCHAR(500) PRIMARY KEY',
        'namecard' => 'VARCHAR(1000) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $connect->query("ALTER TABLE card_number CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Exception $e) {
    logError($e->getMessage(), 'card_number');
}
try {
    $tableName = 'Requestagent';
    $targetStructure = [
        'id' => 'VARCHAR(500) PRIMARY KEY',
        'username' => 'VARCHAR(500) NOT NULL',
        'time' => 'VARCHAR(500) NOT NULL',
        'Description' => 'VARCHAR(500) NOT NULL',
        'status' => 'VARCHAR(500) NOT NULL DEFAULT \'pending\'',
        'type' => 'VARCHAR(500) NOT NULL DEFAULT \'normal\''
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'Requestagent');
}
try {
    $tableName = 'topicid';
    $targetStructure = [
        'report' => 'VARCHAR(500) PRIMARY KEY NOT NULL',
        'idreport' => 'TEXT NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $inserts = [
        ['buyreport', '0'],
        ['otherservice', '0'],
        ['paymentreport', '0'],
        ['otherreport', '0'],
        ['reporttest', '0'],
        ['errorreport', '0'],
        ['porsantreport', '0'],
        ['reportnight', '0'],
        ['reportcron', '0'],
        ['backupfile', '0']
    ];
    foreach ($inserts as $ins) {
        $connect->query("INSERT IGNORE INTO topicid (report, idreport) VALUES ('" . $connect->real_escape_string($ins[0]) . "', '" . $connect->real_escape_string($ins[1]) . "')");
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'topicid');
}
try {
    $tableName = 'manualsell';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'codepanel' => 'VARCHAR(100) NOT NULL',
        'codeproduct' => 'VARCHAR(100) NOT NULL',
        'namerecord' => 'VARCHAR(200) NOT NULL',
        'username' => 'VARCHAR(500) NULL',
        'contentrecord' => 'TEXT NOT NULL',
        'status' => 'VARCHAR(200) NOT NULL DEFAULT \'active\''
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'manualsell');
}
try {
    $tableName = 'departman';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'idsupport' => 'VARCHAR(200) NOT NULL',
        'name_departman' => 'VARCHAR(600) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
    $count = $connect->query("SELECT COUNT(*) as cnt FROM `$tableName`")->fetch_assoc()['cnt'];
    if ($count == 0) {
        $connect->query("INSERT INTO departman (idsupport,name_departman) VALUES ('$adminnumber','☎️ بخش عمومی')");
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'departman');
}
try {
    $tableName = 'support_message';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'Tracking' => 'VARCHAR(100) NOT NULL',
        'idsupport' => 'VARCHAR(100) NOT NULL',
        'iduser' => 'VARCHAR(100) NOT NULL',
        'name_departman' => 'VARCHAR(600) NOT NULL',
        'text' => 'TEXT NOT NULL',
        'result' => 'TEXT NULL DEFAULT \'0\'',
        'time' => 'VARCHAR(200) NOT NULL',
        'status' => "ENUM('Answered','Pending','Unseen','Customerresponse','close') NOT NULL DEFAULT 'Pending'"
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'support_message');
}
try {
    $tableName = 'wheel_list';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'id_user' => 'VARCHAR(200) NOT NULL',
        'time' => 'VARCHAR(200) NOT NULL',
        'first_name' => 'VARCHAR(200) NOT NULL',
        'wheel_code' => 'VARCHAR(200) NOT NULL',
        'price' => 'VARCHAR(200) NOT NULL DEFAULT \'0\''
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'wheel_list');
}
try {
    $tableName = 'botsaz';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'id_user' => 'VARCHAR(200) NOT NULL',
        'bot_token' => 'VARCHAR(200) NOT NULL',
        'admin_ids' => 'TEXT NOT NULL',
        'username' => 'VARCHAR(200) NOT NULL',
        'setting' => 'TEXT NULL',
        'hide_panel' => 'JSON NOT NULL DEFAULT \'{}\'',
        'time' => 'VARCHAR(200) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'botsaz');
}
try {
    $tableName = 'app';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'name' => 'VARCHAR(200) NOT NULL',
        'link' => 'VARCHAR(200) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'app');
}
try {
    $tableName = 'logs_api';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'header' => 'JSON NULL',
        'data' => 'JSON NULL',
        'ip' => 'VARCHAR(200) NOT NULL',
        'time' => 'VARCHAR(200) NOT NULL',
        'actions' => 'VARCHAR(200) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'logs_api');
}
try {
    $tableName = 'category';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'remark' => 'VARCHAR(500) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'category');
}
try {
    $tableName = 'reagent_report';
    $targetStructure = [
        'id' => 'INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'user_id' => 'BIGINT UNIQUE NOT NULL',
        'get_gift' => 'BOOL NOT NULL DEFAULT 0',
        'time' => 'VARCHAR(50) NOT NULL',
        'reagent' => 'VARCHAR(30) NOT NULL'
    ];
    migrateTable($tableName, $targetStructure);
} catch (Exception $e) {
    logError($e->getMessage(), 'reagent_report');
}
$balancemain = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
if (!isset($balancemain['f'])) {
    $value = json_encode(array(
        "f" => "1000000",
        "n" => "1000000",
        "n2" => "1000000",
    ));
    $valuemain = json_encode(array(
        "f" => "20000",
        "n" => "20000",
        "n2" => "20000",
    ));
    update("PaySetting", "ValuePay", $value, "NamePay", "maxbalance");
    update("PaySetting", "ValuePay", $valuemain, "NamePay", "minbalance");
}
$connect->query("ALTER TABLE `invoice` CHANGE `Volume` `Volume` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `price_product` `price_product` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `name_product` `name_product` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `username` `username` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `Service_location` `Service_location` VARCHAR(200)");
$connect->query("ALTER TABLE `invoice` CHANGE `time_sell` `time_sell` VARCHAR(200)");
$connect->query("ALTER TABLE marzban_panel MODIFY name_panel VARCHAR(255) COLLATE utf8mb4_bin");
$connect->query("ALTER TABLE product MODIFY name_product VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");
$connect->query("ALTER TABLE help MODIFY name_os VARCHAR(500) COLLATE utf8mb4_bin");
telegram('setwebhook', [
    'url' => "https://$domainhosts/index.php"
]);
echo "<br>مهاجرت دیتابیس با موفقیت تکمیل شد! تمام تغییرات اعمال شد.";
?>
