<?php
$page_title = "Buat Tiket Internal";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Ambil Data User yang Login
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_data = $conn->query($user_sql)->fetch_assoc();

// Ambil Daftar Divisi
$divisions = [];
$div_sql = "SELECT * FROM divisions";
$div_res = $conn->query($div_sql);
while($row = $div_res->fetch_assoc()) {
    $divisions[] = $row;
}

// PROSES SUBMIT
if (isset($_POST['submit_internal'])) {
    $target_div = intval($_POST['target_division']);
    
    // --- 1. SIAPKAN DATA ---
    // Data Mentah (Untuk Email & Discord agar format Enter tetap terjaga)
    $subjectRaw = $_POST['subject'];
    $descRaw    = $_POST['description']; 
    
    // Data Aman (Untuk Database)
    $subjectDB  = $conn->real_escape_string($subjectRaw);
    $descDB     = $conn->real_escape_string($descRaw);
    $type_ticket = $conn->real_escape_string($_POST['type']);
    
    // Generate ID
    $ticketCode = generateInternalTicketID($target_div, $conn);
    
    // --- 2. UPLOAD FILE (DIPERBAIKI) ---
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/';
    
    // Cek apakah folder uploads ada, jika tidak buat folder
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            echo "<script>alert('Gagal membuat folder uploads. Cek permission server.');</script>";
        }
    }

    // Cek apakah user mengupload file
    if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] != '') {
        $fileError = $_FILES['attachment']['error'];
        $fileSize = $_FILES['attachment']['size'];
        
        // 0 = Tidak ada error
        if ($fileError === 0) {
            // Bersihkan nama file agar aman
            $cleanName = preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['attachment']['name']);
            $fileName = time() . '_int_' . $cleanName;
            $targetPath = $uploadDir . $fileName;
            
            // Pindahkan file dari temp ke folder uploads
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachment = $fileName;
            } else {
                echo "<script>alert('Gagal upload: Folder tidak bisa ditulisi (Permission Denied).');</script>";
            }
        } 
        // 1 = File melebihi upload_max_filesize di php.ini
        elseif ($fileError === 1) {
            echo "<script>alert('Gagal upload: File terlalu besar (Melebihi batas server/php.ini).');</script>";
        } 
        // Error lainnya
        else {
            echo "<script>alert('Gagal upload: Terjadi kesalahan sistem (Code: $fileError).');</script>";
        }
    }
    
    // --- 3. INSERT DATABASE ---
    // Simpan ke database (termasuk nama file attachment jika ada)
    $stmt = $conn->prepare("INSERT INTO internal_tickets (ticket_code, user_id, target_division_id, subject, description, attachment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisss", $ticketCode, $user_id, $target_div, $subjectDB, $descDB, $attachment);
    
    if ($stmt->execute()) {
        
        // --- 4. KIRIM NOTIFIKASI (KODE ASLI DIPERTAHANKAN) ---
        
        // Ambil Nama Divisi Tujuan
        $targetDivName = "Unknown";
        foreach($divisions as $d) { if($d['id'] == $target_div) $targetDivName = $d['name']; }

        // --- A. NOTIF DISCORD INTERNAL ---
        if (function_exists('sendInternalDiscord')) {
            $discordDesc = (strlen($descRaw) > 1000) ? substr($descRaw, 0, 1000) . "..." : $descRaw;

            $discordFields = [
                ["name" => "From", "value" => $user_data['username'], "inline" => true],
                ["name" => "To Division", "value" => $targetDivName, "inline" => true],
                ["name" => "Type", "value" => $type_ticket, "inline" => true],
                ["name" => "Description", "value" => $discordDesc]
            ];
            
            // Tambahkan info attachment di Discord jika ada
            if ($attachment) {
                $discordFields[] = ["name" => "Attachment", "value" => "File Terlampir", "inline" => true];
            }
            
            $titleDiscord = "$ticketCode: $subjectRaw";
            
            $response = sendInternalDiscord($titleDiscord, "A new internal ticket has been submitted.", $discordFields, null, $titleDiscord);
            
            if (isset($response['id'])) {
                $thread_id = $response['id'];
                $conn->query("UPDATE internal_tickets SET discord_thread_id = '$thread_id' WHERE ticket_code = '$ticketCode'");
            }
        }
        
        // --- B. KIRIM EMAIL KE PEMBUAT (KONFIRMASI) ---
        if (function_exists('sendEmailNotification')) {
            $body = "Halo " . $user_data['username'] . ",<br>Tiket Internal Anda ke divisi <strong>$targetDivName</strong> berhasil dibuat.<br>ID: $ticketCode<br>Subject: $subjectRaw";
            sendEmailNotification($user_data['email'], "Internal Ticket Created: $ticketCode", $body);
        }

        // --- C. KIRIM EMAIL KE SELURUH USER DIVISI TUJUAN ---
        // (Looping ini yang mungkin menyebabkan delay jika user divisi banyak, tapi tetap dipertahankan sesuai permintaan)
        if (function_exists('sendEmailNotification')) {
            $stmtDivUsers = $conn->prepare("SELECT username, email FROM users WHERE division_id = ?");
            $stmtDivUsers->bind_param("i", $target_div);
            $stmtDivUsers->execute();
            $resDivUsers = $stmtDivUsers->get_result();

            while ($targetUser = $resDivUsers->fetch_assoc()) {
                if ($targetUser['email'] === $user_data['email']) continue;

                $subjectTarget = "[New Ticket] Masuk dari " . $user_data['username'];
                $bodyTarget  = "<h3>Halo " . $targetUser['username'] . ",</h3>";
                $bodyTarget .= "<p>Ada tiket internal baru yang ditujukan ke Divisi Anda (<strong>$targetDivName</strong>).</p>";
                $bodyTarget .= "<hr>";
                $bodyTarget .= "<ul>
                                    <li><strong>Ticket ID:</strong> $ticketCode</li>
                                    <li><strong>Pengirim:</strong> " . $user_data['username'] . "</li>
                                    <li><strong>Jenis:</strong> $type_ticket</li>
                                    <li><strong>Subject:</strong> " . htmlspecialchars($subjectRaw) . "</li>
                                </ul>";
                
                $bodyTarget .= "<p><strong>Deskripsi:</strong><br>" . nl2br(htmlspecialchars($descRaw)) . "</p>";
                
                if ($attachment) {
                    $bodyTarget .= "<p><em>*Tiket ini memiliki lampiran file.</em></p>";
                }
                
                $bodyTarget .= "<hr>";
                $bodyTarget .= "<p>Mohon segera dicek pada sistem Helpdesk Internal.</p>";

                sendEmailNotification($targetUser['email'], $subjectTarget, $bodyTarget);
            }
        }
        
        echo "<script>alert('Tiket Internal Berhasil Dibuat & Notifikasi dikirim!'); window.location='internal_tickets.php';</script>";
    } else {
        echo "<script>alert('Gagal membuat tiket: " . $conn->error . "');</script>";
    }
}
?>

<div class="page-heading">
    <h3>Buat Ticket Internal</h3>
</div>

<div class="page-content">
    <div class="card">
        <div class="card-header">Form Ticket Antar Divisi</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nama Pembuat</label>
                        <input type="text" class="form-control bg-light" value="<?= $user_data['username'] ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control bg-light" value="<?= $user_data['email'] ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" class="form-control bg-light" value="<?= $user_data['phone'] ?? '-' ?>" readonly>
                    </div>

                    <div class="col-md-12"><hr></div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tujuan Divisi</label>
                        <select name="target_division" class="form-select" required>
                            <option value="">-- Pilih Divisi Tujuan --</option>
                            <?php foreach($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= $div['name'] ?> (<?= $div['code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Semua staff di divisi ini akan menerima notifikasi email.</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Jenis Ticket</label>
                        <select name="type" class="form-select">
                            <option value="Request">Request</option>
                            <option value="Incident">Incident</option>
                            <option value="Question">Question</option>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" required placeholder="Judul permasalahan...">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="5" required placeholder="Jelaskan detail kebutuhan..."></textarea>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Attachment (Optional)</label>
                        <input type="file" name="attachment" class="form-control">
                        <div class="form-text text-muted small">Max file size mengikuti konfigurasi server (biasanya 2MB).</div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" name="submit_internal" class="btn btn-primary px-5">Kirim Ticket Internal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>