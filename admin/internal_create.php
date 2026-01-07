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
    
    // --- 2. UPLOAD FILE ---
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $fileName = time() . '_int_' . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['attachment']['name']);
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
            $attachment = $fileName;
        }
    }
    
    // --- 3. INSERT DATABASE (PRIORITAS UTAMA) ---
    // Simpan dulu agar user merasa "selesai" lebih cepat jika nanti ada delay email
    $stmt = $conn->prepare("INSERT INTO internal_tickets (ticket_code, user_id, target_division_id, subject, description, attachment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisss", $ticketCode, $user_id, $target_div, $subjectDB, $descDB, $attachment);
    
    if ($stmt->execute()) {
        
        // --- 4. KIRIM NOTIFIKASI (SETELAH INSERT SUKSES) ---
        // Jika bagian ini lemot, setidaknya data sudah aman di DB.
        
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
            
            // Coba kirim Discord (dengan error suppression agar tidak fatal error jika timeout)
            try {
                $titleDiscord = "$ticketCode: $subjectRaw";
                $response = sendInternalDiscord($titleDiscord, "A new internal ticket has been submitted.", $discordFields, null, $titleDiscord);
                
                if (isset($response['id'])) {
                    $thread_id = $response['id'];
                    $conn->query("UPDATE internal_tickets SET discord_thread_id = '$thread_id' WHERE ticket_code = '$ticketCode'");
                }
            } catch (Exception $e) {
                // Ignore error discord agar flow tetap jalan
            }
        }
        
        // B. Notif Email Pembuat
        if (function_exists('sendEmailNotification')) {
            try {
                $body = "Halo " . $user_data['username'] . ",<br>Tiket Internal Anda ke divisi <strong>$targetDivName</strong> berhasil dibuat.<br>ID: $ticketCode<br>Subject: $subjectRaw";
                sendEmailNotification($user_data['email'], "Internal Ticket Created: $ticketCode", $body);
            } catch (Exception $e) { /* Ignore */ }
        }

        // C. Notif Email Divisi Tujuan (Looping Email bisa bikin lama)
        if (function_exists('sendEmailNotification')) {
            $stmtDivUsers = $conn->prepare("SELECT username, email FROM users WHERE division_id = ?");
            $stmtDivUsers->bind_param("i", $target_div);
            $stmtDivUsers->execute();
            $resDivUsers = $stmtDivUsers->get_result();

            // Batasi jumlah email jika terlalu banyak user (misal max 5 user pertama) untuk mencegah timeout
            $emailCount = 0; 
            while ($targetUser = $resDivUsers->fetch_assoc()) {
                if ($targetUser['email'] === $user_data['email']) continue;
                // if ($emailCount >= 5) break; // Opsional: Uncomment jika divisi anggotanya ratusan

                $subjectTarget = "[New Ticket] Masuk dari " . $user_data['username'];
                $bodyTarget  = "<h3>Halo " . $targetUser['username'] . ",</h3>";
                $bodyTarget .= "<p>Ada tiket internal baru ke Divisi Anda (<strong>$targetDivName</strong>).</p>";
                $bodyTarget .= "<ul><li>Ticket ID: $ticketCode</li><li>Subject: " . htmlspecialchars($subjectRaw) . "</li></ul>";
                $bodyTarget .= "<p>Mohon cek sistem Helpdesk.</p>";

                try {
                    sendEmailNotification($targetUser['email'], $subjectTarget, $bodyTarget);
                } catch (Exception $e) { /* Ignore */ }
                
                $emailCount++;
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
                        <div class="form-text text-muted small">Max 2MB.</div>
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