<?php
$uPOST = sanitizeInput($_POST);
$rootDirectory = dirname(__DIR__).'/';
$configDirectory = $rootDirectory.'config.php';
$migrateDirectory = $rootDirectory.'mig.php';
if(!file_exists($configDirectory)) {
    $ERROR[] = "فایل config.php یافت نشد.";
    $ERROR[] = "ابتدا ربات را نصب کنید.";
}
if(!file_exists($migrateDirectory)) {
    $ERROR[] = "فایل mig.php یافت نشد.";
    $ERROR[] = "فایل های پروژه را مجددا دانلود و بارگذاری کنید (<a href='https://github.com/ExploitNet/mirza_pro'>‎🌐 Github</a>)";
}
if(phpversion() < 8.2){
    $ERROR[] = "نسخه PHP شما باید حداقل 8.2 باشد.";
    $ERROR[] = "نسخه فعلی: ".phpversion();
    $ERROR[] = "لطفا نسخه PHP خود را به 8.2 یا بالاتر ارتقا دهید.";
}
if(!empty($_SERVER['SCRIPT_URI'])) {
    $URI = str_replace($_SERVER['REQUEST_SCHEME'].'://','',$_SERVER['SCRIPT_URI']);
    if(basename($URI) == 'index.php') {
        $URI = (dirname($URI));
    }
    $webAddress = (dirname($URI)).'/';
}
else {
    $webAddress = $_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME']));
}
$success = false;
$tgBot = [];
$botFirstMessage = '';
$migrationOutput = '';
$document = [];
if(isset($uPOST['submit']) && $uPOST['submit']) {
    $ERROR = [];
    $SUCCESS[] = "✅ مهاجرت دیتابیس با موفقیت انجام شد!";
    $rawConfigData = file_get_contents($configDirectory);
    $tgAdminId = $uPOST['admin_id'];
    $tgBotToken = $uPOST['tg_bot_token'];
    $dbInfo['host'] = 'localhost';
    $dbInfo['name'] = $uPOST['database_name'];
    $dbInfo['username'] = $uPOST['database_username'];
    $dbInfo['password'] = $uPOST['database_password'];
 
    $botAddressWebhook = $uPOST['bot_address_webhook'] ?? '';
    if (!empty($botAddressWebhook)) {
        $document['address'] = dirname($botAddressWebhook);
    } else {
        $document['address'] = $webAddress;
    }
 
    $panel_type = $uPOST['panel_type'] ?? 'pasargad';
    if($_SERVER['REQUEST_SCHEME'] != 'https') {
        $ERROR[] = 'برای فعال سازی ربات تلگرام نیازمند فعال بودن SSL (https) هستید';
        $ERROR[] = '<i>اگر از فعال بودن SSL مطمئن هستید آدرس صفحه را چک کنید، حتما با https صفحه را باز کنید.</i>';
        $ERROR[] = '<a href="https://'.$_SERVER['HTTP_HOST'].'/'.$_SERVER['SCRIPT_NAME'].'">https://'.$_SERVER['HTTP_HOST'].'/'.$_SERVER['SCRIPT_NAME'].'</a>';
    }
    $isValidToken = isValidTelegramToken($tgBotToken);
    if(!$isValidToken) {
        $ERROR[] = "توکن ربات صحیح نمی باشد.";
    }
    if (!isValidTelegramId($tgAdminId)) {
        $ERROR[] = "آیدی عددی ادمین نامعتبر است.";
    }
    if($isValidToken) {
        $tgBot['details'] = getContents("https://api.telegram.org/bot".$tgBotToken."/getMe");
        if($tgBot['details']['ok'] == false) {
            $ERROR[] = "توکن ربات را بررسی کنید. <i>عدم توانایی دریافت جزئیات ربات.</i>";
        }
        else {
            $tgBot['recognitionion'] = getContents("https://api.telegram.org/bot".$tgBotToken."/getChat?chat_id=".$tgAdminId);
            if($tgBot['recognitionion']['ok'] == false) {
                $ERROR[] = "<b>عدم شناسایی مدیر ربات:</b>";
                $ERROR[] = "ابتدا ربات را فعال/استارت کنید با اکانت که میخواهید مدیر اصلی ربات باشد.";
                $ERROR[] = "<a href='https://t.me/".($tgBot['details']['result']['username'] ?? '')."'>@".($tgBot['details']['result']['username'] ?? '')."</a>";
            }
        }
    }
    try {
        $dsn = "mysql:host=" . $dbInfo['host'] . ";dbname=" . $dbInfo['name'] . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $dbInfo['username'], $dbInfo['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $SUCCESS[] = "✅ اتصال به دیتابیس موفقیت آمیز بود!";
    }
    catch (\PDOException $e) {
        $ERROR[] = "❌ عدم اتصال به دیتابیس: ";
        $ERROR[] = "اطلاعات ورودی را بررسی کنید.";
        $ERROR[] = "<code>".$e->getMessage()."</code>";
    }
    if(empty($ERROR)) {
        $replacements = [
            '{database_name}' => $dbInfo['name'],
            '{username_db}' => $dbInfo['username'],
            '{password_db}' => $dbInfo['password'],
            '{API_KEY}' => $tgBotToken,
            '{admin_number}' => $tgAdminId,
            '{domain_name}' => $document['address'],
            '{username_bot}' => $tgBot['details']['result']['username'] ?? ''
        ];
        $newConfigData = str_replace(array_keys($replacements),array_values($replacements),$rawConfigData,$count);
     
        if ($panel_type === 'marzban') {
            $newConfigData = preg_replace('/\s*\n\s*\$new_marzban\s*=\s*true;\s*\n\s*/', "\n", $newConfigData);
        }
     
        if(file_put_contents($configDirectory,$newConfigData) === false || $count == 0) {
            $ERROR[] = '✏️❌ خطا در زمان بازنویسی اطلاعات فایل کانفیگ ربات';
            $ERROR[] = "فایل های پروژه را مجددا دانلود و بارگذاری کنید (<a href='https://github.com/ExploitNet/mirza_pro'>‎🌐 Github</a>)";
        }
        else {
            ob_start();
            include($migrateDirectory);
            $migrationOutput = ob_get_clean();
         
            if (!empty($document['address']) && !empty($tgBotToken)) {
                getContents("https://api.telegram.org/bot".$tgBotToken."/setwebhook?url=https://".$document['address'].'/index.php');
            }
         
            $botFirstMessage = "\n[🔄] تغییرات جا به جایی از نسخه رایگان به پرو روی دیتابیس با موفقیت انجام شد";
            if (!empty($tgBotToken) && !empty($tgAdminId)) {
                getContents('https://api.telegram.org/bot'.$tgBotToken.'/sendMessage?chat_id='.$tgAdminId.'&text='.urlencode(' '.$SUCCESS[0].$botFirstMessage).'&reply_markup={"inline_keyboard":[[{"text":"⚙️ شروع ربات ","callback_data":"start"}]]}');
            }
         
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 مهاجرت ربات میرزا</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .migration-output {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Vazir', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.8;
            color: #e5e7eb;
            direction: rtl;
            text-align: right;
            display: flex;
            flex-direction: column;
        }
        .migration-output h3 {
            color: #e5e7eb;
            text-align: right;
            direction: rtl;
            margin-bottom: 15px;
            border-bottom: 1px solid #334155;
            padding-bottom: 10px;
            font-family: Vazir, sans-serif;
        }
        .migration-output .success {
            color: #4ade80;
            direction: rtl;
            text-align: right;
        }
        .migration-output .error {
            color: #f87171;
            direction: rtl;
            text-align: right;
        }
        .migration-output .info {
            color: #60a5fa;
            direction: rtl;
            text-align: right;
        }
        .migration-line {
            display: block;
            margin-bottom: 8px;
            font-family: 'Courier New', monospace;
            direction: rtl;
            text-align: right;
        }
        .button-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
            align-items: center;
        }
        .submit-success {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
            text-align: center;
        }
        .submit-success:hover {
            background: #059669;
        }
        .delete-button {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
            text-align: center;
            border: none;
            cursor: pointer;
            font-family: Vazir, sans-serif;
            font-size: 16px;
        }
        .delete-button:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>مهاجرت از نسخه رایگان به پرو میرزا</h1>
     
        <?php if (!empty($ERROR)): ?>
            <div class="alert alert-danger">
                <?php echo implode("<br>",$ERROR); ?>
            </div>
        <?php endif; ?>
     
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo implode("<br>",$SUCCESS); ?>
            </div>
         
            <?php if (!empty($migrationOutput)): ?>
                <div class="migration-output">
                    <h3>خروجی مهاجرت:</h3>
                    <div class="migration-content">
                        <?php
                        $lines = explode("<br>", $migrationOutput);
                        foreach ($lines as $line) {
                            if (trim($line) !== '') {
                                $cleanLine = htmlspecialchars($line);
                                if (strpos($line, '✅') !== false || strpos($line, 'SUCCESS') !== false || strpos($line, 'success') !== false) {
                                    echo '<div class="migration-line success">' . $cleanLine . '</div>';
                                } elseif (strpos($line, '❌') !== false || strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false) {
                                    echo '<div class="migration-line error">' . $cleanLine . '</div>';
                                } elseif (strpos($line, 'ℹ️') !== false || strpos($line, 'INFO') !== false || strpos($line, 'info') !== false) {
                                    echo '<div class="migration-line info">' . $cleanLine . '</div>';
                                } else {
                                    echo '<div class="migration-line">' . $cleanLine . '</div>';
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
         
            <div class="button-container">
                <?php if (!empty($tgBot['details']['result']['username'])): ?>
                    <a class="submit-success" href="https://t.me/<?php echo $tgBot['details']['result']['username']; ?>">🤖 رفتن به ربات <?php echo "‎@".$tgBot['details']['result']['username']; ?> »</a>
                <?php endif; ?>
             
                <a class="delete-button" href="delete_migrate.php">🗑️ حذف پوشه‌های نصب و مهاجرت</a>
            </div>
         
            <div style="text-align: center; margin-top: 20px; font-size: 16px; color: #6b7280;">
                <p>برای امنیت بیشتر، پس از اتمام کار روی دکمه بالا کلیک کنید تا پوشه‌های Installer و Migrate حذف شوند.</p>
            </div>
        <?php endif; ?>
         
        <form id="migrate-form" <?php if($success) { echo 'style="display:none;"'; } ?> method="post">
            <div class="form-group">
                <label for="admin_id">آیدی عددی ادمین:</label>
                <input type="text" id="admin_id" name="admin_id"
                       placeholder="ADMIN TELEGRAM #Id" value="<?php echo $uPOST['admin_id'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="tg_bot_token">توکن ربات تلگرام :</label>
                <input type="text" id="tg_bot_token" name="tg_bot_token"
                       placeholder="BOT TOKEN" value="<?php echo $uPOST['tg_bot_token'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="database_username">نام کاربری دیتابیس :</label>
                <input type="text" id="database_username" name="database_username"
                       placeholder="DATABASE USERNAME" value="<?php echo $uPOST['database_username'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="database_password">رمز عبور دیتابیس :</label>
                <input type="password" id="database_password" name="database_password"
                       placeholder="DATABASE PASSWORD" value="<?php echo $uPOST['database_password'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="database_name">نام دیتابیس :</label>
                <input type="text" id="database_name" name="database_name"
                       placeholder="DATABASE NAME" value="<?php echo $uPOST['database_name'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="panel_type">نوع پنل:</label>
                <select id="panel_type" name="panel_type" required>
                    <option value="pasargad" <?php echo ($uPOST['panel_type'] ?? 'pasargad') === 'pasargad' ? 'selected' : ''; ?>>پنل پاسارگارد</option>
                    <option value="marzban" <?php echo ($uPOST['panel_type'] ?? '') === 'marzban' ? 'selected' : ''; ?>>پنل مرزبان</option>
                </select>
            </div>
            <div class="form-group">
                <details>
                    <summary for="secret_key"><i>آدرس سورس ربات</i></summary>
                    <label for="bot_address_webhook ">آدرس صفحه سورس ربات</label>
                    <input type="text" id="bot_address_webhook" name="bot_address_webhook" placeholder="Web URL for Set Webhook" value="<?php echo $webAddress.'/index.php'; ?>" required>
                </details>
            </div>
         
            <div class="form-group">
                <div class="alert alert-warning">
                    <strong>⚠️ هشدار مهم:</strong>
                    <ul style="text-align: right; margin-right: 20px;">
                        <li>این عملیات ساختار دیتابیس را به روزرسانی می‌کند</li>
                        <li>از اطلاعات مهم خود backup بگیرید</li>
                        <li>عملیات مهاجرت ممکن است کمی زمان بر باشد</li>
                        <li>تنظیمات ربات نیز به‌روزرسانی خواهند شد</li>
                        <li>پس از اتمام، پوشه‌های Installer و Migrate حذف خواهند شد</li>
                    </ul>
                </div>
            </div>
         
            <button type="submit" name="submit" value="submit">اجرای مهاجرت دیتابیس</button>
        </form>
     
        <footer>
            <p>MirzaPRO Migration , Made by ♥️ | <a href="https://t.me/ImSoheilOfficial">ImSoheil</a> | <a href="https://t.me/+NABuHliyQrIzZGI0">Telegram Group (UnOfficial)</a> | &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>
</body>
</html>
<?php
function getContents($url) {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
 
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['ok' => false];
    }
    return json_decode($response, true);
}
function isValidTelegramToken($token) {
    return preg_match('/^\d{6,12}:[A-Za-z0-9_-]{35}$/', $token);
}
function isValidTelegramId($id) {
    return preg_match('/^\d{6,12}$/', $id);
}
function sanitizeInput(&$INPUT, array $options = []) {
    $defaultOptions = [
        'allow_html' => false,
        'allowed_tags' => '',
        'remove_spaces' => false,
        'connection' => null,
        'max_length' => 0,
        'encoding' => 'UTF-8'
    ];
 
    $options = array_merge($defaultOptions, $options);
 
    if (is_array($INPUT)) {
        return array_map(function($item) use ($options) {
            return sanitizeInput($item, $options);
        }, $INPUT);
    }
 
    if ($INPUT === null || $INPUT === false) {
        return '';
    }
 
    $INPUT = (string)$INPUT;
 
    $INPUT = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $INPUT);
 
    if ($options['max_length'] > 0) {
        $INPUT = mb_substr($INPUT, 0, $options['max_length'], $options['encoding']);
    }
 
    if (!$options['allow_html']) {
        $INPUT = strip_tags($INPUT);
    } elseif (!empty($options['allowed_tags'])) {
        $INPUT = strip_tags($INPUT, $options['allowed_tags']);
    }
 
    if ($options['remove_spaces']) {
        $INPUT = preg_replace('/\s+/', ' ', trim($INPUT));
    }
 
    $INPUT = htmlspecialchars($INPUT, ENT_QUOTES | ENT_HTML5, $options['encoding']);
 
    if ($options['connection'] instanceof mysqli) {
        $INPUT = $options['connection']->real_escape_string($INPUT);
    }
 
    return $INPUT;
}
?>
