<?php
// 1. AKTIFKAN DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. INCLUDE CONFIG DENGAN PATH ABSOLUT
$configPath = __DIR__ . '/config/functions.php';

if (file_exists($configPath)) {
    include $configPath;
} else {
    die("<strong>Error Critical:</strong> File config/functions.php tidak ditemukan. Pastikan struktur folder benar.");
}

// 3. CEK KONEKSI DATABASE
if (!isset($conn) || $conn->connect_error) {
    die("<strong>Error Database:</strong> Koneksi database gagal. Cek config/database.php.");
}

// 4. PROSES FORM
if (isset($_POST['submit_ticket'])) {
    
    // Sanitasi Input Sederhana
    $type = $conn->real_escape_string($_POST['type']);
    $email = $conn->real_escape_string($_POST['email']);
    $company = $conn->real_escape_string($_POST['company']);
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $description = $conn->real_escape_string($_POST['description']);
    
    // A. Generate ID Unik
    if (function_exists('generateTicketID')) {
        $ticketCode = generateTicketID($type, $conn);
    } else {
        die("Fungsi generateTicketID tidak ditemukan di functions.php");
    }
    
    // B. Handle File Upload
    $attachment = null;
    $uploadDir = __DIR__ . '/uploads/'; 
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowedSize = 2 * 1024 * 1024; // 2MB
        $fileSize = $_FILES['attachment']['size'];
        $fileTmp = $_FILES['attachment']['tmp_name'];
        $originalName = $_FILES['attachment']['name'];
        
        if ($fileSize <= $allowedSize) {
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $originalName);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $attachment = $fileName;
            } else {
                echo "<script>alert('Gagal mengupload file. Cek permission folder.');</script>";
            }
        } else {
            echo "<script>alert('File terlalu besar! Maksimal 2MB.');</script>";
        }
    }
    
    // C. Insert Database
    // Default status ticket saat dibuat adalah 'open' (sesuai struktur DB default)
    $query = "INSERT INTO tickets (ticket_code, type, email, company, name, phone, subject, description, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("sssssssss", $ticketCode, $type, $email, $company, $name, $phone, $subject, $description, $attachment);
        
        if ($stmt->execute()) {
            
            // D. Kirim Discord Notification (Fitur Lama Tetap Ada)
            // ------------------------------------------------------------------
            $discordDesc = (strlen($description) > 1000) ? substr($description, 0, 1000) . "..." : $description;

            $discordFields = [
                ["name" => "Ticket ID", "value" => $ticketCode, "inline" => true],
                ["name" => "From", "value" => "$name ($company)", "inline" => true],
                ["name" => "Subject", "value" => $subject],
                ["name" => "Description", "value" => $discordDesc]
            ];
            
            if (function_exists('sendToDiscord')) {
                $threadName = $ticketCode . " - " . $subject;
                $response = sendToDiscord("New Ticket Created!", "A new ticket has been submitted.", $discordFields, null, $threadName);
                
                if (isset($response['id'])) {
                    $thread_id = $response['id'];
                    $conn->query("UPDATE tickets SET discord_thread_id = '$thread_id' WHERE ticket_code = '$ticketCode'");
                }
            }
            // ------------------------------------------------------------------
            
            // E. Hitung Nomor Antrian (Ticket Open saat ini)
            // Logika: Hitung jumlah tiket dengan status 'open'. Tiket yang baru dibuat ini termasuk di dalamnya.
            $queueQuery = "SELECT COUNT(*) as total FROM tickets WHERE status = 'open'";
            $queueRes = $conn->query($queueQuery);
            $queueData = $queueRes->fetch_assoc();
            $queueNumber = $queueData['total']; // Ini adalah nomor antrian saat tiket dibuat

            // F. Kirim Email ke User (Template Baru)
            if (function_exists('sendEmailNotification')) {
                $emailSubject = "Konfirmasi Pembuatan Ticket: $ticketCode – Linksfield Networks Indonesia";
                
                // Menggunakan nl2br agar format baris baru di email terjaga
                $emailBodyRaw = "Yth. Bapak/Ibu Pelanggan,

Terima kasih telah menghubungi layanan dukungan Linksfield Networks Indonesia.

Dengan ini kami informasikan bahwa ticket layanan Anda telah berhasil dibuat dengan detail sebagai berikut:

ID Ticket: $ticketCode
Nomor Antrian: $queueNumber

Tim kami akan segera menindaklanjuti permintaan Anda sesuai dengan urutan antrian dan tingkat prioritas yang berlaku.
Anda dapat memantau status serta perkembangan penanganan ticket melalui website resmi kami.

https://system.linksfield.id (Klik pada Lacak Status Ticket)

Apabila Anda memerlukan informasi tambahan atau memiliki pertanyaan lebih lanjut, silakan membalas email ini atau menghubungi kanal layanan kami yang tersedia.

Terima kasih atas kepercayaan Anda kepada Linksfield Networks Indonesia.

Hormat kami,
Tim Support
Linksfield Networks Indonesia";

                // Konversi newlines ke <br> untuk HTML email
                $emailBodyHtml = nl2br($emailBodyRaw);

                sendEmailNotification($email, $emailSubject, $emailBodyHtml);
            }
            
            // REDIRECT SUCCESS
            echo "<script>
                alert('Ticket Berhasil Dibuat! ID Anda: $ticketCode. Nomor Antrian: $queueNumber'); 
                window.location.href = 'track_ticket.php?track_id=$ticketCode';
            </script>";
            exit(); 
            
        } else {
            echo "Error Database Execute: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error Database Prepare: " . $conn->error;
    }
} else {
    header("Location: create_ticket.php");
    exit();
}
?>