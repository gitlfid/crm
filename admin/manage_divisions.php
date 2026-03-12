<?php
$page_title = "Manage Divisions";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Proteksi Halaman (Hanya Admin)
if($_SESSION['role'] != 'admin') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit;
}

// --- LOGIKA TAMBAH DIVISI ---
if(isset($_POST['add_div'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $code = strtoupper($conn->real_escape_string($_POST['code']));
    
    if($conn->query("INSERT INTO divisions (name, code) VALUES ('$name', '$code')")) {
        echo "<script>window.location='manage_divisions.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan divisi: " . $conn->error . "');</script>";
    }
}

// --- AMBIL DATA DIVISI ---
$divs = $conn->query("SELECT * FROM divisions ORDER BY id DESC");
$total_divs = $divs->num_rows;
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modern-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .modern-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .modern-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .modern-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1600px] mx-auto space-y-6 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-2">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-500/30">
                    <i class="ph-fill ph-buildings"></i>
                </div>
                Manage Divisions
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Kelola daftar divisi / departemen perusahaan untuk strukturisasi pengelompokan pengguna.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.location.href='manage_divisions.php'" class="group inline-flex items-center justify-center w-12 h-12 bg-white dark:bg-[#24303F] border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-2xl shadow-sm transition-all active:scale-95" title="Refresh Data">
                <i class="ph-bold ph-arrows-clockwise text-xl group-hover:rotate-180 transition-transform duration-500"></i>
            </button>
            <button onclick="openModal('addDivModal')" class="group inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-3 px-6 rounded-2xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:scale-95 whitespace-nowrap overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                <i class="ph-bold ph-plus text-xl relative z-10"></i> 
                <span class="relative z-10">Add Division</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-5 mb-6">
        <div class="bg-white dark:bg-[#24303F] rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-5 transition-transform hover:-translate-y-1 group">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0 group-hover:scale-110 transition-transform"><i class="ph-fill ph-briefcase"></i></div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Divisi Terdaftar</p>
                <h4 class="text-4xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($total_divs) ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-2 transition-colors duration-300">
        <div class="relative group">
            <i class="ph-bold ph-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
            <input type="text" id="searchInput" class="w-full pl-12 pr-4 py-3.5 bg-transparent border-none text-sm font-medium focus:ring-0 outline-none dark:text-white placeholder-slate-400" placeholder="Pencarian cepat nama divisi atau kode divisi..." onkeyup="liveSearch()">
        </div>
    </div>

    <div class="bg-white dark:bg-[#24303F] rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-colors duration-300 flex flex-col min-h-[450px] relative">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Tampilkan</span>
                <select id="pageSize" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-500/50 outline-none cursor-pointer shadow-sm">
                    <option value="10">10 Baris</option>
                    <option value="25">25 Baris</option>
                    <option value="50">50 Baris</option>
                </select>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Data</span>
            </div>
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400" id="paginationInfo">
                Menampilkan 0 dari 0 data
            </div>
        </div>

        <div class="overflow-x-auto modern-scrollbar flex-grow pb-24">
            <table class="w-full text-left border-collapse table-fixed min-w-[700px]">
                <thead class="bg-white dark:bg-[#24303F] border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[15%] text-center">System ID</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[50%]">Division Name & Info</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest w-[35%] text-center">Division Code</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if($divs && $divs->num_rows > 0): ?>
                        <?php while($row = $divs->fetch_assoc()): ?>
                        <tr class="data-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <td class="px-6 py-5 align-middle text-center search-target">
                                <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 font-mono font-bold text-[11px] shadow-sm border border-slate-200 dark:border-slate-600">
                                    <i class="ph-bold ph-hash"></i>
                                    <?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle search-target">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 flex items-center justify-center shadow-inner border border-indigo-100 dark:border-indigo-500/20 shrink-0">
                                        <i class="ph-bold ph-briefcase text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0 pr-2">
                                        <div class="font-black text-slate-800 dark:text-slate-200 text-sm group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors break-words whitespace-normal mb-0.5">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1">
                                            <i class="ph-fill ph-users"></i> Anggota Divisi Terdaftar
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-5 align-middle text-center search-target">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-black text-xs tracking-widest border border-indigo-200 dark:border-indigo-500/30 shadow-sm uppercase">
                                    <i class="ph-bold ph-tag"></i> <?= htmlspecialchars($row['code']) ?>
                                </span>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyRow">
                            <td colspan="3" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mb-4 border border-slate-100 dark:border-slate-800 shadow-inner">
                                        <i class="ph-fill ph-buildings text-5xl text-slate-300 dark:text-slate-600"></i>
                                    </div>
                                    <h4 class="font-black text-slate-700 dark:text-slate-200 text-lg mb-1">Tidak Ada Divisi</h4>
                                    <p class="text-sm font-medium">Belum ada departemen atau divisi yang ditambahkan ke sistem.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between w-full mt-auto shrink-0 z-20" id="paginationControls">
            <div class="flex-1 flex justify-start">
                <button id="btnPrev" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ph-bold ph-arrow-left"></i> Previous
                </button>
            </div>
            
            <div id="pageNumbers" class="flex-1 flex items-center justify-center gap-1.5 hidden sm:flex">
                </div>
            <div class="text-xs font-bold text-slate-500 sm:hidden" id="pageInfoMobile">
                Page 1
            </div>
            
            <div class="flex-1 flex justify-end">
                <button id="btnNext" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    Next <i class="ph-bold ph-arrow-right"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<div id="addDivModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('addDivModal')"></div>
    
    <div class="relative bg-white dark:bg-[#24303F] rounded-3xl shadow-2xl w-full max-w-lg transform scale-95 opacity-0 transition-all duration-300 modal-box flex flex-col overflow-hidden">
        <form method="POST">
            <div class="px-6 py-5 border-b border-indigo-500/20 bg-indigo-600 text-white flex justify-between items-center">
                <h3 class="text-base font-black flex items-center gap-2 tracking-wide"><i class="ph-bold ph-buildings text-xl"></i> Tambah Divisi Baru</h3>
                <button type="button" onclick="closeModal('addDivModal')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/40 transition-colors">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Nama Divisi <span class="text-rose-500">*</span></label>
                    <div class="relative group">
                        <i class="ph-bold ph-briefcase absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                        <input type="text" name="name" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner" placeholder="Ex: Finance Department" required>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-2">Kode Divisi (Short) <span class="text-rose-500">*</span></label>
                    <div class="relative group">
                        <i class="ph-bold ph-tag absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg group-focus-within:text-indigo-500 transition-colors"></i>
                        <input type="text" name="code" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 dark:text-white outline-none transition-all placeholder-slate-400 shadow-inner uppercase" placeholder="Ex: FIN" maxlength="5" required>
                    </div>
                    <p class="text-[10px] font-medium text-slate-400 mt-2 italic flex items-center gap-1"><i class="ph-fill ph-info"></i> Maksimal 5 Karakter. Akan diubah menjadi huruf besar otomatis.</p>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal('addDivModal')" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-[#24303F] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition-colors shadow-sm">Batal</button>
                <button type="submit" name="add_div" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-500/30 flex items-center justify-center gap-2 active:scale-95">
                    <i class="ph-bold ph-plus text-lg"></i> Simpan Divisi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- LIVE SEARCH LOGIC ---
    function liveSearch() {
        let input = document.getElementById("searchInput").value.toLowerCase();
        let rows = document.querySelectorAll(".data-row");

        if(input.trim() !== '') {
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if (text.includes(input)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
            document.getElementById("paginationControls").classList.add('hidden');
        } else {
            document.getElementById("paginationControls").classList.remove('hidden');
            if(typeof renderTable === 'function') renderTable();
        }
    }

    // --- PAGINATION LOGIC (Vanilla JS) ---
    document.addEventListener('DOMContentLoaded', () => {
        const rows = Array.from(document.querySelectorAll('#tableBody tr.data-row'));
        const totalRows = rows.length;
        
        if(totalRows === 0) return;

        const pageSizeSelect = document.getElementById('pageSize');
        const paginationInfo = document.getElementById('paginationInfo');
        const pageInfoMobile = document.getElementById('pageInfoMobile');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const pageNumbersContainer = document.getElementById('pageNumbers');

        let currentPage = 1;
        let rowsPerPage = parseInt(pageSizeSelect.value);

        window.renderTable = function() {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                if (index >= start && index < end) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            const currentEnd = end > totalRows ? totalRows : end;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            
            paginationInfo.innerHTML = `Menampilkan <span class="text-indigo-600 dark:text-indigo-400 font-black">${start + 1} - ${currentEnd}</span> dari <span class="font-black text-slate-800 dark:text-white">${totalRows}</span>`;
            pageInfoMobile.innerText = `Page ${currentPage} of ${totalPages}`;

            updatePaginationButtons(totalPages);
        }

        function updatePaginationButtons(totalPages) {
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === totalPages || totalPages === 0;

            pageNumbersContainer.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const pageBtn = document.createElement('button');
                    pageBtn.innerText = i;
                    if (i === currentPage) {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-black text-white bg-indigo-600 shadow-sm shadow-indigo-500/30 flex items-center justify-center transition-all";
                    } else {
                        pageBtn.className = "w-8 h-8 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700 transition-all flex items-center justify-center";
                        pageBtn.onclick = () => { currentPage = i; window.renderTable(); };
                    }
                    pageNumbersContainer.appendChild(pageBtn);
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    const dots = document.createElement('span');
                    dots.innerText = '...';
                    dots.className = "w-8 h-8 flex items-center justify-center text-slate-400 text-xs font-black tracking-widest";
                    pageNumbersContainer.appendChild(dots);
                }
            }
        }

        pageSizeSelect.addEventListener('change', (e) => {
            rowsPerPage = parseInt(e.target.value);
            currentPage = 1;
            window.renderTable();
        });

        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; window.renderTable(); }
        });

        btnNext.addEventListener('click', () => {
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            if (currentPage < totalPages) { currentPage++; window.renderTable(); }
        });

        window.renderTable();
    });

    // --- CUSTOM MODAL HANDLERS ---
    function openModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const box = modal.querySelector('.modal-box');
        modal.classList.add('opacity-0');
        box.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
</script>

<?php include 'includes/footer.php'; ?>