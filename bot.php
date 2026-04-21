<?php

// ================== تنظیمات ==================
$BOT_TOKEN = 'توکن_ربات_خود_را_اینجا_قرار_دهید';
$ADMIN_ID = آیدی_عددی_شما; // مثلاً 123456789

$FTP_CONFIG = [
    'host' => '3255155726.cloudydl.com',
    'user' => 'pz23819',
    'pass' => 'y8NUYWBx',
    'path' => '/public_html/files/'
];

$DOMAIN = '3255155726.cloudydl.com/files/';
// ==============================================

// دریافت آپدیت از تلگرام
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message'])) {
    exit;
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$text = $message['text'] ?? '';
$document = $message['document'] ?? null;

// تابع ارسال پیام
function sendMessage($chat_id, $text) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    curlRequest($url, $data);
}

// تابع درخواست cURL
function curlRequest($url, $data = [], $post = true) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// دستور /start
if ($text == '/start') {
    sendMessage($chat_id, "سلام! فایلی که می‌خواهید آپلود شود را ارسال کنید.");
}

// مدیریت فایل ارسالی
elseif ($document) {
    sendMessage($chat_id, "📤 در حال آپلود فایل...");
    
    $file_id = $document['file_id'];
    $file_name = $document['file_name'] ?? 'file_' . time();
    
    // دریافت اطلاعات فایل
    $file_info_url = "https://api.telegram.org/bot$BOT_TOKEN/getFile";
    $file_info = curlRequest($file_info_url, ['file_id' => $file_id]);
    
    if (!$file_info['ok']) {
        sendMessage($chat_id, "❌ خطا در دریافت فایل");
        exit;
    }
    
    $file_path = $file_info['result']['file_path'];
    
    // دانلود فایل از تلگرام
    $file_url = "https://api.telegram.org/file/bot$BOT_TOKEN/$file_path";
    $file_content = file_get_contents($file_url);
    
    if (!$file_content) {
        sendMessage($chat_id, "❌ خطا در دانلود فایل");
        exit;
    }
    
    // ذخیره موقت فایل
    $temp_file = tempnam(sys_get_temp_dir(), 'upload_');
    file_put_contents($temp_file, $file_content);
    
    // آپلود به FTP
    $ftp_conn = ftp_connect($FTP_CONFIG['host']);
    
    if (!$ftp_conn) {
        sendMessage($chat_id, "❌ خطا در اتصال به سرور FTP");
        exit;
    }
    
    $login = ftp_login($ftp_conn, $FTP_CONFIG['user'], $FTP_CONFIG['pass']);
    
    if (!$login) {
        sendMessage($chat_id, "❌ خطا در ورود به FTP");
        ftp_close($ftp_conn);
        exit;
    }
    
    // تغییر نام فایل برای جلوگیری از تداخل
    $new_file_name = time() . '_' . basename($file_name);
    $remote_path = $FTP_CONFIG['path'] . $new_file_name;
    
    if (ftp_put($ftp_conn, $remote_path, $temp_file, FTP_BINARY)) {
        $file_link = $DOMAIN . rawurlencode($new_file_name);
        
        sendMessage($chat_id, "✅ فایل آپلود شد!\n\n📎 نام: $new_file_name\n\n🔗 لینک دانلود:\n$file_link");
    } else {
        sendMessage($chat_id, "❌ خطا در آپلود فایل");
    }
    
    // پاکسازی
    ftp_close($ftp_conn);
    unlink($temp_file);
}

else {
    sendMessage($chat_id, "لطفا یک فایل ارسال کنید.");
}
