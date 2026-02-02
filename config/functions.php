<?php
// config/functions.php

// 1. INCLUDE DATABASE & AUTOLOAD
require_once __DIR__ . '/database.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ==========================================
// FUNGSI KIRIM EMAIL
// ==========================================
function sendEmailNotification($to, $subject, $body) {
    global $conn; 

    $settings = [];
    $sql = "SELECT setting_key, setting_value FROM settings";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
        return false;
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false;
    }

    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = $settings['smtp_pass']; 
        
        $secureType = isset($settings['smtp_secure']) ? strtolower($settings['smtp_secure']) : 'tls';
        if ($secureType == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = isset($settings['smtp_port']) ? intval($settings['smtp_port']) : 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $fromName = !empty($settings['company_name']) ? $settings['company_name'] : 'Helpdesk System';
        $mail->setFrom($settings['smtp_user'], $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        $errorMsg = date('Y-m-d H:i:s') . " - Gagal kirim ke $to. Error: " . $mail->ErrorInfo . "\n";
        file_put_contents(__DIR__ . '/email_error.log', $errorMsg, FILE_APPEND);
        return false;
    }
}

// ==========================================
// 1. GENERATE NOMOR QUOTATION (CONTINUOUS)
// ==========================================
function generateQuotationNo($conn) {
    // Format Prefix: QLF + YYYYMMDD
    $prefixToday = "QLF" . date('Ymd'); 
    
    // Cari nomor terakhir di database (Tanpa filter tanggal, agar sequence jalan terus)
    // Kita filter 'QLF' saja untuk memastikan mengambil jenis dokumen yang benar
    $sql = "SELECT quotation_no FROM quotations WHERE quotation_no LIKE 'QLF%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    $newSeq = 1;
    if ($res && $res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['quotation_no'];
        // Ambil 4 digit terakhir dari nomor sebelumnya
        $lastSeq = intval(substr($lastNo, -4));
        $newSeq = $lastSeq + 1;
    }
    
    // Gabung Prefix Hari Ini + Sequence yang terus berlanjut
    return $prefixToday . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// 2. GENERATE NOMOR INVOICE (CONTINUOUS)
// ==========================================
function generateInvoiceNo($conn) {
    // Format Prefix: INVLF + YYYYMMDD
    $prefixToday = "INVLF" . date('Ymd'); 
    
    // Cari nomor terakhir (Sequence lanjut terus tidak peduli ganti hari/bulan)
    $sql = "SELECT invoice_no FROM invoices WHERE invoice_no LIKE 'INVLF%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    $newSeq = 1;
    if ($res && $res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['invoice_no'];
        // Ambil 4 digit terakhir
        $lastSeq = intval(substr($lastNo, -4));
        $newSeq = $lastSeq + 1;
    }
    
    return $prefixToday . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// 3. GENERATE NOMOR DO (CONTINUOUS)
// ==========================================
function generateDONumber($conn) {
    // Format Prefix: DL + YYYYMMDD
    $prefixToday = "DL" . date('Ymd'); 
    
    // Cari nomor terakhir
    $sql = "SELECT do_number FROM delivery_orders WHERE do_number LIKE 'DL%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    $newSeq = 1;
    if ($res && $res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['do_number'];
        $lastSeq = intval(substr($lastNo, -4));
        $newSeq = $lastSeq + 1;
    }
    
    return $prefixToday . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// FUNGSI LAINNYA (HELPER)
// ==========================================
function executeCurl($url, $payload) {
    $ch = curl_init($url);
    $json_data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true);
}

function sendToDiscord($title, $message, $fields = [], $thread_id = null) {
    global $conn;
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='discord_webhook'");
    if($res->num_rows == 0) return false;
    
    $webhookurl = $res->fetch_object()->setting_value;
    if(empty($webhookurl)) return false;

    $cleanFields = [];
    foreach ($fields as $field) {
        $cleanVal = substr(stripslashes($field['value']), 0, 1000);
        $cleanFields[] = ["name" => $field['name'], "value" => $cleanVal, "inline" => $field['inline'] ?? false];
    }

    $payload = [
        "embeds" => [[
            "title" => $title,
            "description" => $message,
            "color" => 3368652,
            "fields" => $cleanFields,
            "footer" => ["text" => "Helpdesk System"],
            "timestamp" => date("c")
        ]]
    ];
    
    $url = $webhookurl . "?wait=true" . ($thread_id ? "&thread_id=$thread_id" : "");
    return executeCurl($url, $payload);
}

function sendInternalDiscord($title, $message, $fields = [], $thread_id = null) {
    global $conn;
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='discord_webhook_internal'");
    if($res->num_rows == 0) return false;
    
    $webhookurl = $res->fetch_object()->setting_value;
    if(empty($webhookurl)) return false;

    $cleanFields = [];
    foreach ($fields as $field) {
        $cleanVal = substr(stripslashes($field['value']), 0, 1000);
        $cleanFields[] = ["name" => $field['name'], "value" => $cleanVal, "inline" => $field['inline'] ?? false];
    }

    $payload = [
        "embeds" => [[
            "title" => $title,
            "description" => $message,
            "color" => 16750848,
            "fields" => $cleanFields,
            "footer" => ["text" => "Internal System"],
            "timestamp" => date("c")
        ]]
    ];
    
    $url = $webhookurl . "?wait=true" . ($thread_id ? "&thread_id=$thread_id" : "");
    return executeCurl($url, $payload);
}
?>