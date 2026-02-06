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
// FUNGSI TIKET (DIKEMBALIKAN AGAR TIDAK ERROR)
// ==========================================
function generateTicketID($type, $conn) {
    $prefixMap = ['support' => 'LFID-SUP', 'payment' => 'LFID-PAY', 'info' => 'LFID-INFO'];
    $prefix = isset($prefixMap[$type]) ? $prefixMap[$type] : 'LFID-GEN';
    $date = date('Ymd'); 
    $query = "SELECT ticket_code FROM tickets WHERE ticket_code LIKE '$prefix-$date-%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $parts = explode('-', $row['ticket_code']);
        $lastNum = (int)end($parts); 
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNum = '001';
    }
    return "$prefix-$date-$newNum";
}

function generateInternalTicketID($target_division_id, $conn) {
    $res = $conn->query("SELECT code FROM divisions WHERE id = $target_division_id");
    $divCode = ($res->num_rows > 0) ? $res->fetch_object()->code : 'GEN';
    
    $prefix = "LF-INT-$divCode";
    $date = date('Ymd'); 
    
    $query = "SELECT ticket_code FROM internal_tickets WHERE ticket_code LIKE '$prefix-$date-%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $parts = explode('-', $row['ticket_code']);
        $lastNum = (int)end($parts); 
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNum = '001';
    }
    return "$prefix-$date-$newNum";
}

// ==========================================
// FUNGSI DISCORD & CURL
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

// ==========================================
// 1. GENERATE NOMOR QUOTATION (CONTINUOUS - UPDATED)
// ==========================================
function generateQuotationNo($conn) {
    // Format Prefix: QLF + YYYYMM (Tanpa Tanggal/Hari)
    $prefixToday = "QLF" . date('Ym'); 
    
    // Cari nomor terakhir yang depannya QLF
    // Tanpa filter tanggal spesifik agar sequence jalan terus selamanya
    $sql = "SELECT quotation_no FROM quotations WHERE quotation_no LIKE 'QLF%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    $newSeq = 1;
    if ($res && $res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['quotation_no'];
        // Ambil 4 digit terakhir
        $lastSeq = intval(substr($lastNo, -4));
        $newSeq = $lastSeq + 1;
    }
    
    return $prefixToday . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// 2. GENERATE NOMOR INVOICE (CONTINUOUS)
// ==========================================
function generateInvoiceNo($conn) {
    $prefixToday = "INVLF" . date('Ym'); 
    $sql = "SELECT invoice_no FROM invoices WHERE invoice_no LIKE 'INVLF%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    $newSeq = 1;
    if ($res && $res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['invoice_no'];
        $lastSeq = intval(substr($lastNo, -4));
        $newSeq = $lastSeq + 1;
    }
    return $prefixToday . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// 3. GENERATE NOMOR DO (CONTINUOUS)
// ==========================================
function generateDONumber($conn) {
    $prefixToday = "DO" . date('Ym'); 
    $sql = "SELECT do_number FROM delivery_orders WHERE do_number LIKE 'DO%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    $newSeq = 1;
    if ($res && $res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['do_number'];
        $lastSeq = intval(substr($lastNo, -4));
        $newSeq = $lastSeq + 1;
    }
    return $prefixToday . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}
?>