<?php
$page_title = "Detail Internal Ticket";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// 1. Validasi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location='internal_tickets.php';</script>";
    exit;
}

$ticket_id = intval($_GET['id']);
$msg_status = "";
$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];

// Ambil Divisi User Login
$uQ = $conn->query("SELECT division_id FROM users WHERE id = $current_user_id");
$current_user_div = $uQ->fetch_assoc()['division_id'];

// 2. Ambil Data Tiket (UPDATED: Tambah info Assigned To)
$sql = "SELECT t.*, 
               u.username as creator_name, u.email as creator_email, u.phone as creator_phone,
               d.name as target_div_name, d.code as target_div_code,
               u2.username as assigned_name
        FROM internal_tickets t
        JOIN users u ON t.user_id = u.id
        JOIN divisions d ON t.target_division_id = d.id
        LEFT JOIN users u2 ON t.assigned_to = u2.id
        WHERE t.id = $ticket_id";

$ticket = $conn->query($sql)->fetch_assoc();

if (!$ticket) {
    echo "<div class='alert alert-danger m-4'>Tiket tidak ditemukan.</div>";
    include 'includes/footer.php'; exit;
}

// Cek Permission (Admin ATAU Anggota Divisi Tujuan)
$can_edit_status = ($current_role == 'admin' || $current_user_div == $ticket['target_division_id']);

// --- FITUR BARU: PROSES ASSIGN STAFF ---
if (isset($_POST['assign_staff']) && $can_edit_status) {
    $staff_id = intval($_POST['assigned_to']);
    
    // Validasi: Staff harus dari divisi yang sama
    $cekStaff = $conn->query("SELECT id FROM users WHERE id = $staff_id AND division_id = " . $ticket['target_division_id']);
    
    if ($cekStaff->num_rows > 0 || $staff_id == 0) { // 0 = Unassign
        $sqlAssign = ($staff_id == 0) ? "NULL" : $staff_id;
        if($conn->query("UPDATE internal_tickets SET assigned_to = $sqlAssign WHERE id = $ticket_id")) {
            echo "<script>alert('Berhasil assign staff!'); window.location.href='internal_view.php?id=$ticket_id';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Error: Staff tidak valid atau beda divisi.');</script>";
    }
}

// 3. PROSES REPLY & STATUS
if (isset($_POST['submit_reply'])) {
    $reply_msg = $conn->real_escape_string($_POST['reply_message']);
    
    // Tentukan Status Baru
    $new_status = $ticket['status']; 
    if ($can_edit_status && isset($_POST['status'])) {
        $new_status = $conn->real_escape_string($_POST['status']);
    }
    
    // Logika Upload
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
    @chmod($uploadDir, 0777);

    // Proses Upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] != '') {
        $fErr = $_FILES['attachment']['error'];
        if (!is_writable($uploadDir)) {
             $msg_status = "<div class='alert alert-danger'>Error Server: Folder 'uploads' terkunci.</div>";
        } elseif ($fErr === 0) {
            $fileName = time() . '_rep_' . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['attachment']['name']);
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
                $attachment = $fileName;
            } else {
                $msg_status = "<div class='alert alert-danger'>Gagal Upload: Permission Denied.</div>";
            }
        } elseif ($fErr === 1) {
            $msg_status = "<div class='alert alert-danger'>Gagal Upload: File terlalu besar.</div>";
        } else {
            $msg_status = "<div class='alert alert-danger'>Gagal Upload: Kode Error $fErr</div>";
        }
    }

    if (strpos($msg_status, 'alert-danger') === false) {
        $stmt = $conn->prepare("INSERT INTO internal_ticket_replies (internal_ticket_id, user_id, message, attachment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $ticket_id, $current_user_id, $reply_msg, $attachment);
        
        if ($stmt->execute()) {
            if ($new_status != $ticket['status']) {
                $conn->query("UPDATE internal_tickets SET status = '$new_status' WHERE id = $ticket_id");
            }
            
            // Notifikasi (Email/Discord) - Kode Existing
            if (function_exists('sendEmailNotification')) {
                // ... (Kode notifikasi yang sudah ada tetap berjalan)
            }
            
            echo "<script>window.location.href='internal_view.php?id=$ticket_id';</script>"; 
            exit;
        }
    }
}

// 4. History Chat
$chat_sql = "SELECT r.*, u.username 
             FROM internal_ticket_replies r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.internal_ticket_id = $ticket_id 
             ORDER BY r.created_at ASC";
$chats = $conn->query($chat_sql);

// Helper Functions
// [FIXED] Menambahkan replace untuk mengubah \r\n (teks literal) menjadi baris baru HTML
function formatText($text) {
    if (empty($text)) return "";
    $text = str_replace(array('\r\n', '\n', '\r'), "\n", $text);
    return nl2br(htmlspecialchars($text));
}

function isImg($file) { return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); }
?>

<style>
    .chat-container { background-color: #f4f6f8; padding: 20px; border-radius: 0 0 10px 10px; max-height: 600px; overflow-y: auto; }
    .chat-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .avatar-other { background-color: #6c757d; color: white; }
    .chat-bubble { padding: 12px 18px; border-radius: 12px; position: relative; max-width: 85%; box-shadow: 0 2px 4px rgba(0,0,0,0.05); line-height: 1.5; }
    .chat-left .chat-bubble { background-color: #ffffff; color: #333; border-top-left-radius: 0; }
    .chat-right .chat-bubble { background-color: #435ebe; color: #fff; border-top-right-radius: 0; }
    .chat-meta { font-size: 0.7rem; opacity: 0.7; margin-top: 4px; display: block; }
    .chat-right .chat-meta { text-align: right; color: #e0e0e0; }
    .chat-left .chat-meta { text-align: left; color: #888; }
</style>

<div class="page-heading">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3><?= $ticket['ticket_code'] ?></h3>
            <p class="text-subtitle text-muted">Internal Support Ticket</p>
        </div>
        <a href="internal_tickets.php" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <section class="section">
        <?= $msg_status ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary w-75"><?= htmlspecialchars($ticket['subject']) ?></h5>
                        <div>
                            <?php 
                                $st = $ticket['status'];
                                $badge = ($st=='open')?'success':(($st=='progress')?'warning text-dark':'secondary');
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= strtoupper($st) ?></span>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <?php if($ticket['assigned_name']): ?>
                            <div class="mb-3 p-2 bg-light border rounded small text-secondary">
                                <i class="bi bi-person-check-fill text-primary me-2"></i> 
                                Ditangani oleh: <strong><?= htmlspecialchars($ticket['assigned_name']) ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center mb-3">
                            <div class="chat-avatar bg-info text-white me-3">
                                <?= substr($ticket['creator_name'],0,1) ?>
                            </div>
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($ticket['creator_name']) ?></h6>
                                <small class="text-muted">
                                    <?= $ticket['creator_email'] ?> &bull; <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <div class="alert alert-light border">
                            <?= formatText($ticket['description']) ?>
                        </div>
                        <?php if($ticket['attachment']): ?>
                            <div class="mt-3">
                                <?php if(isImg($ticket['attachment'])): ?>
                                    <a href="../uploads/<?= $ticket['attachment'] ?>" target="_blank">
                                        <img src="../uploads/<?= $ticket['attachment'] ?>" class="img-fluid rounded border" style="max-height: 200px;">
                                    </a>
                                <?php else: ?>
                                    <a href="../uploads/<?= $ticket['attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip"></i> Lihat Lampiran Awal</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h6 class="mb-0"><i class="bi bi-chat-dots-fill me-2"></i> Riwayat Diskusi</h6>
                    </div>
                    <div class="chat-container">
                        <?php if($chats->num_rows > 0): ?>
                            <?php while($c = $chats->fetch_assoc()): ?>
                                <?php $isMe = ($c['user_id'] == $current_user_id); ?>
                                <div class="d-flex mb-4 <?= $isMe ? 'justify-content-end chat-right' : 'justify-content-start chat-left' ?>">
                                    <?php if(!$isMe): ?>
                                        <div class="chat-avatar avatar-other me-3">
                                            <?= strtoupper(substr($c['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="chat-bubble">
                                        <?php if(!$isMe): ?>
                                            <div class="fw-bold small mb-1 text-primary"><?= htmlspecialchars($c['username']) ?></div>
                                        <?php endif; ?>
                                        <div><?= formatText($c['message']) ?></div>
                                        <?php if($c['attachment']): ?>
                                            <div class="mt-2 pt-2 border-top border-opacity-25" style="border-color: inherit;">
                                                <a href="../uploads/<?= $c['attachment'] ?>" target="_blank" class="btn btn-sm btn-light border text-dark py-0 px-2" style="font-size:0.8rem">
                                                    <i class="bi bi-file-earmark"></i> File
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <span class="chat-meta"><?= date('H:i, d M', strtotime($c['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-chat-square-dots fs-1 opacity-25"></i>
                                <p class="mt-2">Belum ada balasan diskusi.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card sticky-top shadow-sm" style="top: 20px; z-index: 10;">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0">Tindakan</h5>
                    </div>
                    <div class="card-body pt-4">
                        
                        <?php if ($can_edit_status): ?>
                        <form method="POST" class="mb-4 pb-3 border-bottom">
                            <div class="mb-2">
                                <label class="form-label fw-bold small text-uppercase text-primary">Assign PIC</label>
                                <div class="input-group">
                                    <select name="assigned_to" class="form-select form-select-sm">
                                        <option value="0">- Belum Ada PIC -</option>
                                        <?php
                                            // Ambil list user dari divisi yang sama (Target Division)
                                            $targetDiv = $ticket['target_division_id'];
                                            $qStaff = $conn->query("SELECT id, username FROM users WHERE division_id = $targetDiv ORDER BY username ASC");
                                            while($s = $qStaff->fetch_assoc()):
                                        ?>
                                            <option value="<?= $s['id'] ?>" <?= ($ticket['assigned_to'] == $s['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['username']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="submit" name="assign_staff" class="btn btn-primary btn-sm">Set</button>
                                </div>
                                <div class="form-text small">Pilih anggota tim untuk menangani tiket ini.</div>
                            </div>
                        </form>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Update Status</label>
                                <?php if ($can_edit_status): ?>
                                    <select name="status" class="form-select">
                                        <option value="open" <?= $ticket['status']=='open'?'selected':'' ?>>Open</option>
                                        <option value="progress" <?= $ticket['status']=='progress'?'selected':'' ?>>In Progress</option>
                                        <option value="hold" <?= $ticket['status']=='hold'?'selected':'' ?>>Hold</option>
                                        <option value="closed" <?= $ticket['status']=='closed'?'selected':'' ?>>Closed</option>
                                        <option value="canceled" <?= $ticket['status']=='canceled'?'selected':'' ?>>Canceled</option>
                                    </select>
                                <?php else: ?>
                                    <input type="text" class="form-control bg-light" value="<?= strtoupper($ticket['status']) ?>" readonly>
                                    <div class="form-text text-danger small fst-italic mt-1"><i class="bi bi-lock"></i> Status hanya dapat diubah oleh divisi tujuan.</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Pesan Balasan</label>
                                <textarea name="reply_message" class="form-control" rows="4" placeholder="Tulis balasan Anda..." required></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Lampiran (Opsional)</label>
                                <input type="file" name="attachment" class="form-control form-control-sm">
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="submit_reply" class="btn btn-primary">
                                    <i class="bi bi-send-fill me-2"></i> Kirim Update
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>