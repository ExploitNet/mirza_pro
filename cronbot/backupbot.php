<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

function manualDbBackup($host, $user, $pass, $dbname, $filename) {
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if ($mysqli->connect_error) {
        throw new Exception("اتصال به دیتابیس شکست خورد: " . $mysqli->connect_error);
    }
    $mysqli->set_charset("utf8mb4");

    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sql = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "START TRANSACTION;\n";
    $sql .= "SET time_zone = \"+00:00\";\n\n";

    foreach ($tables as $table) {
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $create = $mysqli->query("SHOW CREATE TABLE `$table`");
        $createRow = $create->fetch_row();
        $sql .= $createRow[1] . ";\n\n";

        $data = $mysqli->query("SELECT * FROM `$table`");
        $numFields = $data->field_count;

        for ($i = 0; $i < $data->num_rows; $i++) {
            $row = $data->fetch_row();
            $sql .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $numFields; $j++) {
                $row[$j] = isset($row[$j]) ? addslashes($row[$j]) : 'NULL';
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                if (!isset($row[$j]) || $row[$j] == 'NULL') {
                    $sql .= "NULL";
                } else {
                    $sql .= "'" . $row[$j] . "'";
                }
                if ($j < ($numFields - 1)) {
                    $sql .= ',';
                }
            }
            $sql .= ");\n";
        }
        $sql .= "\n\n";
    }

    $sql .= "COMMIT;\n";

    if (file_put_contents($filename, $sql) === false) {
        throw new Exception("خطا در ذخیره فایل بکاپ: $filename");
    }

    $mysqli->close();
    return true;
}

function createAndSendZip($filePath, $caption, $chatId, $threadId) {
    if (!file_exists($filePath)) return false;

    $zipPath = $filePath . '.zip';

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($filePath, basename($filePath));
            $zip->close();
            $sendFile = $zipPath;
        } else {
            $sendFile = $filePath;
        }
    } else {
        $sendFile = $filePath;
    }

    try {
        telegram('sendDocument', [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'document' => new CURLFile(realpath($sendFile)),
            'caption' => $caption,
        ]);

        if (file_exists($sendFile)) unlink($sendFile);
        if (file_exists($filePath)) unlink($filePath);
    } catch (Exception $e) {
        error_log("خطا در ارسال فایل: " . $e->getMessage());
        return false;
    }

    return true;
}


$reportbackup = select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
$destination = getcwd();
$setting = select("setting", "*");
$sourcefir = dirname($destination);
$botlist = select("botsaz", "*", null, null, "fetchAll");


if ($botlist) {
    foreach ($botlist as $bot) {
        $folderName = $bot['id_user'] . $bot['username'];
        $botBasePath = $sourcefir . '/vpnbot/' . $folderName;
        $zipFilePath = $destination . '/file_' . $folderName . '.zip';

        if (!is_dir($botBasePath)) continue;

        $filesToZip = [];
        foreach (['data', 'product.json', 'product_name.json'] as $item) {
            $fullPath = $botBasePath . '/' . $item;
            if (file_exists($fullPath)) {
                $filesToZip[] = $fullPath;
            }
        }

        if (empty($filesToZip)) continue;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($filesToZip as $path) {
                    if (is_dir($path)) {
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        foreach ($iterator as $file) {
                            $relative = str_replace($botBasePath . DIRECTORY_SEPARATOR, '', $file);
                            if ($file->isDir()) {
                                $zip->addEmptyDir($relative);
                            } else {
                                $zip->addFile($file, $relative);
                            }
                        }
                    } else {
                        $relative = basename($path);
                        $zip->addFile($path, $relative);
                    }
                }
                $zip->close();

                telegram('sendDocument', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $reportbackup,
                    'document' => new CURLFile(realpath($zipFilePath)),
                    'caption' => "@{$bot['username']} | {$bot['id_user']}",
                ]);

                if (file_exists($zipFilePath)) unlink($zipFilePath);
            }
        } else {
            telegram('sendMessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportbackup,
                'text' => "⚠️ نمی‌توان بکاپ ربات @{$bot['username']} را فشرده‌سازی کرد (ZipArchive غیرفعال است).",
            ]);
        }
    }
}


$backup_file_name = 'backup_' . date("Y-m-d") . '.sql';
$success = false;

$db_host = 'localhost';


if (function_exists('exec') && !ini_get('safe_mode')) {
    $command = "mysqldump -h " . escapeshellarg($db_host) .
               " -u " . escapeshellarg($usernamedb) .
               " -p" . escapeshellarg($passworddb) .
               " --no-tablespaces " . escapeshellarg($dbname) .
               " > " . escapeshellarg($backup_file_name);

    $output = [];
    $return_var = 0;
    @exec($command, $output, $return_var);
    if ($return_var === 0 && file_exists($backup_file_name)) {
        $success = true;
    }
}

if (!$success) {
    try {
        manualDbBackup($db_host, $usernamedb, $passworddb, $dbname, $backup_file_name);
        $success = true;
    } catch (Exception $e) {
        error_log("خطا در بکاپ دستی: " . $e->getMessage());
        telegram('sendMessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'text' => "❌❌❌❌❌❌\nخطا در بکاپ دیتابیس:\n" . $e->getMessage(),
        ]);
        $success = false;
    }
}

if ($success) {
    createAndSendZip(
        $backup_file_name,
        "📌 خروجی دیتابیس ربات اصلی",
        $setting['Channel_Report'],
        $reportbackup
    );
}
