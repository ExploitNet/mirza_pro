<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../jdf.php';

$setting = select("setting", "*");

if (!$setting || !isset($setting['scorestatus'])) {
    error_log("Setting data is missing or incomplete.");
    exit;
}

$midnight_time = date("H:i");

if (intval($setting['scorestatus']) == 1) {
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];

    if ($midnight_time == "00:00") {
        $temp = [];
        $Lottery_prize = json_decode($setting['Lottery_prize'], true);

        if (!is_array($Lottery_prize)) {
            error_log("Lottery_prize is not a valid JSON array.");
            exit;
        }

        foreach ($Lottery_prize as $lottery) {
            $temp[] = $lottery;
        }
        $Lottery_prize = $temp;

        if ($setting['Lotteryagent'] == "1") {
            $stmt = $pdo->prepare("SELECT * FROM user WHERE User_Status = 'Active' AND score != '0' ORDER BY score DESC LIMIT 3");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM user WHERE User_Status = 'Active' AND score != '0' AND agent = 'f' ORDER BY score DESC LIMIT 3");
        }
        $stmt->execute();

        $count = 0;
        $textlotterygroup = "📌 ادمین عزیز کاربران زیر برنده قرعه کشی و حسابشان شارژ گردید.\n";

        $textJson = json_decode(file_get_contents('../text.json'), true);
        if (!is_array($textJson)) {
            error_log("text.json is not a valid JSON file.");
            exit;
        }

        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userLang = isset($result['language']) && !empty($result['language']) ? $result['language'] : 'fa';
            if (!isset($textJson[$userLang])) {
                $userLang = 'fa'; // fallback to Persian
            }
            $textbotlang = $textJson[$userLang];

            if (!isset($Lottery_prize[$count])) {
                error_log("No prize defined for rank " . ($count + 1));
                break;
            }

            $prizeAmount = intval($Lottery_prize[$count]);
            $balance_last = intval($result['Balance']) + $prizeAmount;
            update("user", "Balance", $balance_last, "id", $result['id']);

            $balanceFormatted = number_format($prizeAmount);
            $rank = $count + 1;

            $textlottery = "🎁 نتیجه قرعه کشی \n\n😎 کاربر عزیز تبریک! شما نفر $rank برنده $balanceFormatted تومان موجودی شدید و حساب شما شارژ گردید.";
            sendmessage($result['id'], $textlottery, null, 'html');

            $textlotterygroup .= "\nنام کاربری : @{$result['username']}\nآیدی عددی : {$result['id']}\nمبلغ : $balanceFormatted\nنفر : $rank\n--------------";

            $count++;
        }

        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textlotterygroup,
            'parse_mode' => "HTML"
        ]);

        update("user", "score", "0", null, null);
    }
}
