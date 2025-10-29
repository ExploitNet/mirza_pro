<?php
/**
 * فایل حذف پوشه‌های Installer و Migrate
 * پس از اتمام موفقیت‌آمیز مهاجرت اجرا می‌شود
 */

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

// مسیر ریشه پروژه (یک سطح بالاتر از پوشه migrate)
$rootDirectory = dirname(__DIR__);

// مسیر پوشه‌ها و فایل‌هایی که باید حذف شوند
$installerDir = $rootDirectory . '/installer';
$migrateDir = __DIR__; // پوشه فعلی (migrate)
$migFile = $rootDirectory . '/mig.php'; // فایل mig.php در پوشه اصلی

$deleted = [];
$errors = [];

// حذف پوشه installer اگر وجود دارد
if (file_exists($installerDir) && is_dir($installerDir)) {
    if (deleteDirectory($installerDir)) {
        $deleted[] = "پوشه Installer";
    } else {
        $errors[] = "خطا در حذف پوشه Installer";
    }
}

// حذف فایل mig.php در پوشه اصلی
if (file_exists($migFile)) {
    if (unlink($migFile)) {
        $deleted[] = "فایل mig.php";
    } else {
        $errors[] = "خطا در حذف فایل mig.php";
    }
}

// حذف تمام فایل‌های داخل پوشه migrate به جز فایل جاری
$migrateFiles = scandir($migrateDir);
foreach ($migrateFiles as $file) {
    if ($file != '.' && $file != '..' && $file != basename(__FILE__)) {
        $filePath = $migrateDir . '/' . $file;
        if (is_dir($filePath)) {
            if (deleteDirectory($filePath)) {
                $deleted[] = "پوشه $file در migrate";
            } else {
                $errors[] = "خطا در حذف پوشه $file";
            }
        } else {
            if (unlink($filePath)) {
                $deleted[] = "فایل $file";
            } else {
                $errors[] = "خطا در حذف فایل $file";
            }
        }
    }
}

// تلاش برای حذف پوشه migrate (اگر خالی شده باشد)
$remainingFiles = array_diff(scandir($migrateDir), ['.', '..', basename(__FILE__)]);
if (empty($remainingFiles)) {
    // بستن session و هر منبع دیگری که ممکن است قفل شده باشد
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // تغییر مسیر به پوشه اصلی قبل از حذف
    chdir($rootDirectory);
    
    if (@rmdir($migrateDir)) {
        $deleted[] = "پوشه Migrate";
    } else {
        $errors[] = "خطا در حذف پوشه Migrate - ممکن است نیاز به حذف دستی داشته باشد";
    }
} else {
    $errors[] = "پوشه Migrate به طور کامل خالی نشده است. فایل‌های باقیمانده: " . implode(', ', $remainingFiles);
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف فایل‌های مهاجرت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Vazir, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 2.2em;
        }
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        ul {
            margin-right: 20px;
            margin-top: 10px;
        }
        li {
            margin-bottom: 8px;
        }
        .submit-success {
            display: block;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            transition: transform 0.3s ease;
        }
        .submit-success:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
        footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        footer a {
            color: #667eea;
            text-decoration: none;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        #countdown {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 حذف فایل‌های مهاجرت</h1>
        
        <?php if (!empty($deleted)): ?>
            <div class="alert alert-success">
                <h3>✅ عملیات حذف با موفقیت انجام شد!</h3>
                <p>موارد زیر حذف شدند:</p>
                <ul>
                    <?php foreach ($deleted as $item): ?>
                        <li><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h3>⚠️ خطا در حذف برخی موارد:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <h3>💡 راهنمایی:</h3>
            <p>اگر برخی فایل‌ها حذف نشدند، می‌توانید به صورت دستی اقدام کنید:</p>
            <ul>
                <li>پوشه <code>installer</code> در مسیر اصلی پروژه</li>
                <li>فایل <code>mig.php</code> در مسیر اصلی پروژه</li>
                <li>پوشه <code>migrate</code> در مسیر اصلی پروژه</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a class="submit-success" href="../">🏠 بازگشت به صفحه اصلی</a>
        </div>
        
        <footer>
            <p>MirzaPRO Migration Cleanup | &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>
</body>
</html>