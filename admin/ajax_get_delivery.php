<?php
// Pastikan file koneksi database dan auth disertakan
require_once '../config/database.php';

// Cek apakah ada request ID dari delivery_list.php
if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Query untuk mengambil detail pengiriman
    $stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // 1. Tentukan Status Logistik
        $status_color = "bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400";
        $status_label = "IN TRANSIT";
        $status_icon  = "ph-spinner-gap animate-spin-slow";

        if (!empty($row['delivered_date'])) {
            $status_color = "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400";
            $status_label = "DELIVERED";
            $status_icon  = "ph-check-circle";
        }

        // 2. Format Tanggal
        $sent_date = date('d M Y, H:i', strtotime($row['delivery_date']));
        $delivered_at = !empty($row['delivered_date']) ? date('d M Y, H:i', strtotime($row['delivered_date'])) : '<span class="italic text-slate-400">Waiting Delivery</span>';

        // 3. Render HTML UI Tailwind (Response)
        echo '
        <div class="space-y-6 animate-fade-in-up">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 p-5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center text-2xl shrink-0">
                        <i class="ph-fill ph-package"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Tracking Number / AWB</p>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400 font-mono tracking-tight">' . (!empty($row['tracking_number']) ? htmlspecialchars($row['tracking_number']) : 'NO-RESI-AVAILABLE') . '</span>
                            <span class="px-2 py-0.5 rounded bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider">' . htmlspecialchars($row['courier_name']) . '</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:items-end">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Current Status</p>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-widest ' . $status_color . '">
                        <i class="ph-bold ' . $status_icon . '"></i> ' . $status_label . '
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <div class="relative p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1e293b] shadow-soft overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-rose-500"></div>
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100 dark:border-slate-700">
                        <i class="ph-fill ph-arrow-circle-up text-xl text-rose-500"></i>
                        <h4 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Sender Details</h4>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Company / PIC</p>
                            <p class="font-bold text-slate-800 dark:text-white text-sm">' . htmlspecialchars($row['sender_company']) . '</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400">' . htmlspecialchars($row['sender_name']) . '</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Phone Contact</p>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-300">' . (!empty($row['sender_phone']) ? htmlspecialchars($row['sender_phone']) : '-') . '</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Address</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">' . (!empty($row['sender_address']) ? nl2br(htmlspecialchars($row['sender_address'])) : '-') . '</p>
                        </div>
                    </div>
                </div>

                <div class="relative p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1e293b] shadow-soft overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100 dark:border-slate-700">
                        <i class="ph-fill ph-map-pin text-xl text-emerald-500"></i>
                        <h4 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Receiver Details</h4>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Company / PIC</p>
                            <p class="font-bold text-slate-800 dark:text-white text-sm">' . htmlspecialchars($row['receiver_company']) . '</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400">' . htmlspecialchars($row['receiver_name']) . '</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Phone Contact</p>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-300">' . (!empty($row['receiver_phone']) ? htmlspecialchars($row['receiver_phone']) : '-') . '</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Address</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">' . (!empty($row['receiver_address']) ? nl2br(htmlspecialchars($row['receiver_address'])) : '-') . '</p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1e293b] shadow-soft">
                <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100 dark:border-slate-700">
                    <i class="ph-fill ph-cube text-xl text-indigo-500"></i>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Item Information</h4>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-widest text-slate-400 border-b border-slate-100 dark:border-slate-700">
                                <th class="pb-2 font-bold">Project Name</th>
                                <th class="pb-2 font-bold">Item Detail</th>
                                <th class="pb-2 font-bold">Data Pkg</th>
                                <th class="pb-2 font-bold text-center">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr>
                                <td class="py-3 font-semibold text-slate-800 dark:text-slate-200">' . (!empty($row['project_name']) ? htmlspecialchars($row['project_name']) : '-') . '</td>
                                <td class="py-3 text-slate-600 dark:text-slate-400">' . htmlspecialchars($row['item_name']) . '</td>
                                <td class="py-3 text-slate-600 dark:text-slate-400">' . (!empty($row['data_package']) ? htmlspecialchars($row['data_package']) : '-') . '</td>
                                <td class="py-3 text-center font-black text-indigo-600 dark:text-indigo-400">' . $row['qty'] . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-inner">
                <div class="mb-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Additional Notes</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300 italic bg-white dark:bg-[#1A222C] p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        "' . (!empty($row['notes']) ? nl2br(htmlspecialchars($row['notes'])) : 'Tidak ada catatan tambahan.') . '"
                    </p>
                </div>
                <div class="flex flex-wrap gap-x-8 gap-y-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Created / Sent At</p>
                        <p class="text-xs font-bold text-slate-600 dark:text-slate-400 mt-0.5">' . $sent_date . '</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Delivered At</p>
                        <p class="text-xs font-bold text-slate-600 dark:text-slate-400 mt-0.5">' . $delivered_at . '</p>
                    </div>
                </div>
            </div>

        </div>
        ';
    } else {
        echo '
        <div class="flex flex-col items-center justify-center py-10">
            <i class="ph-fill ph-warning-circle text-5xl text-rose-500 mb-3"></i>
            <p class="text-lg font-bold text-slate-800 dark:text-white">Data Tidak Ditemukan</p>
            <p class="text-sm text-slate-500 mt-1">Data pengiriman yang Anda cari mungkin sudah dihapus.</p>
        </div>';
    }
} else {
    echo '
    <div class="p-4 bg-rose-50 text-rose-600 border border-rose-200 rounded-xl font-medium">
        Invalid Request.
    </div>';
}
?>