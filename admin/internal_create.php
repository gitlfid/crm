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
    $subjectRaw = $_POST['subject'];
    $descRaw    = $_POST['description']; 
    
    $subjectDB  = $conn->real_escape_string($subjectRaw);
    $descDB     = $conn->real_escape_string($descRaw);
    $type_ticket = $conn->real_escape_string($_POST['type']);
    
    $ticketCode = generateInternalTicketID($target_div, $conn);
    
    // --- 2. UPLOAD FILE (DIPERBAIKI) ---
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/';
    
    // Pastikan folder ada dan writable
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Cek apakah user mencoba upload file
    if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] != '') {
        $fileError = $_FILES['attachment']['error'];
        $fileSize = $_FILES['attachment']['size'];
        
        // Cek Error Upload
        if ($fileError === 0) {
            // Validasi Ukuran (Contoh Max 5MB di Script, tapi tetap dibatasi PHP.ini)
            if ($fileSize <= 5 * 1024 * 1024) { 
                $fileName = time() . '_int_' . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['attachment']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    $attachment = $fileName;
                } else {
                    echo "<script>alert('Gagal memindahkan file ke folder uploads. Cek permission folder!');</script>";
                }
            } else {
                echo "<script>alert('File terlalu besar! Maksimal 5MB.');</script>";
            }
        } elseif ($fileError === 1) {
            echo "<script>alert('File melebihi batas upload_max_filesize di server (PHP.ini).');</script>";
        } else {
            echo "<script>alert('Terjadi error saat upload file. Kode Error: $fileError');</script>";
        }
    }
    
    // --- 3. INSERT DATABASE (DATA AMAN DULU) ---
    $stmt = $conn->prepare("INSERT INTO internal_tickets (ticket_code, user_id, target_division_id, subject, description, attachment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisss", $ticketCode, $user_id, $target_div, $subjectDB, $descDB, $attachment);
    
    if ($stmt->execute()) {
        
        // --- 4. KIRIM NOTIFIKASI (Background Process Style) ---
        // Ambil Nama Divisi Tujuan
        $targetDivName = "Unknown";
        foreach($divisions as $d) { if($d['id'] == $target_div) $targetDivName = $d['name']; }

        // A. Notif Discord
        if (function_exists('sendInternalDiscord')) {
            $discordDesc = (strlen($descRaw) > 1000) ? substr($descRaw, 0, 1000) . "..." : $descRaw;
            $discordFields = [
                ["name" => "From", "value" => $user_data['username'], "inline" => true],
                ["name" => "To Division", "value" => $targetDivName, "inline" => true],
                ["name" => "Type", "value" => $type_ticket, "inline" => true],
                ["name" => "Description", "value" => $discordDesc]
            ];
            
            // Tambahkan Info Attachment di Discord jika ada
            if ($attachment) {
                $discordFields[] = ["name" => "Attachment", "value" => "Ada file terlampir", "inline" => true];
            }

            try {
                $titleDiscord = "$ticketCode: $subjectRaw";
                $response = sendInternalDiscord($titleDiscord, "A new internal ticket has been submitted.", $discordFields, null, $titleDiscord);
                
                if (isset($response['id'])) {
                    $thread_id = $response['id'];
                    $conn->query("UPDATE internal_tickets SET discord_thread_id = '$thread_id' WHERE ticket_code = '$ticketCode'");
                }
            } catch (Exception $e) { /* Ignore Error Discord */ }
        }
        
        // B. Notif Email Pembuat
        if (function_exists('sendEmailNotification')) {
            try {
                $body = "Halo " . $user_data['username'] . ",<br>Tiket Internal Anda ke divisi <strong>$targetDivName</strong> berhasil dibuat.<br>ID: $ticketCode<br>Subject: $subjectRaw";
                sendEmailNotification($user_data['email'], "Internal Ticket Created: $ticketCode", $body);
            } catch (Exception $e) { /* Ignore Error Email */ }
        }

        // C. Notif Email Divisi Tujuan
        if (function_exists('sendEmailNotification')) {
            $stmtDivUsers = $conn->prepare("SELECT username, email FROM users WHERE division_id = ?");
            $stmtDivUsers->bind_param("i", $target_div);
            $stmtDivUsers->execute();
            $resDivUsers = $stmtDivUsers->get_result();

            while ($targetUser = $resDivUsers->fetch_assoc()) {
                if ($targetUser['email'] === $user_data['email']) continue;

                $subjectTarget = "[New Ticket] Masuk dari " . $user_data['username'];
                $bodyTarget  = "<h3>Halo " . $targetUser['username'] . ",</h3>";
                $bodyTarget .= "<p>Ada tiket internal baru ke Divisi Anda (<strong>$targetDivName</strong>).</p>";
                $bodyTarget .= "<ul><li>Ticket ID: $ticketCode</li><li>Subject: " . htmlspecialchars($subjectRaw) . "</li></ul>";
                
                if ($attachment) {
                    $bodyTarget .= "<p><em>*Tiket ini memiliki lampiran.</em></p>";
                }

                $bodyTarget .= "<p>Mohon cek sistem Helpdesk.</p>";

                try {
                    sendEmailNotification($targetUser['email'], $subjectTarget, $bodyTarget);
                } catch (Exception $e) { /* Ignore Error Email */ }
            }
        }
        
        echo "<script>alert('Tiket Internal Berhasil Dibuat!'); window.location='internal_tickets.php';</script>";
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
                        <div class="form-text text-muted small">Max file size depends on server config (Usually 2MB).</div>
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