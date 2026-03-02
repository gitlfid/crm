<?php
$page_title = "Input Delivery";
include 'includes/header.php';
include 'includes/sidebar.php';
// require_once '../config/database.php'; // Aktifkan jika butuh query dropdown database
?>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1400px] mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 animate-slide-up">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Input Delivery Baru</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Lengkapi formulir di bawah ini untuk mencatat pengiriman logistik baru.</p>
        </div>
        <a href="delivery_list.php" class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all">
            <i class="ph-bold ph-arrow-left text-lg"></i> Kembali ke List
        </a>
    </div>

    <form action="process_delivery.php" method="POST" class="space-y-6 animate-slide-up delay-100">
        <input type="hidden" name="action" value="create_logistic">

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <h3 class="font-bold text-indigo-600 dark:text-indigo-400 text-sm uppercase tracking-widest flex items-center gap-2">
                    <i class="ph-fill ph-package text-lg"></i> Item & Project Information
                </h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="lg:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Date <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <i class="ph-fill ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="date" name="delivery_date" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all font-medium" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="lg:col-span-1 relative">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Project <span class="text-rose-500">*</span></label>
                        <button type="button" onclick="toggleInput('project')" class="text-[10px] font-bold text-indigo-500 hover:text-indigo-700 flex items-center gap-1 transition-colors">
                            <i class="ph-bold ph-plus"></i> New
                        </button>
                    </div>
                    
                    <div id="project_select_wrap" class="relative block">
                        <i class="ph-fill ph-folder absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <select name="project_id" id="project_select" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all appearance-none cursor-pointer">
                            <option value="">- Select Project -</option>
                            <option value="1">Project Alpha</option>
                            <option value="2">Project Beta</option>
                        </select>
                        <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    </div>

                    <div id="project_input_wrap" class="relative hidden">
                        <i class="ph-fill ph-pencil-simple absolute left-4 top-1/2 -translate-y-1/2 text-indigo-400 text-lg"></i>
                        <input type="text" name="manual_project_name" id="project_input" class="w-full pl-11 pr-4 py-3 bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-500/30 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" placeholder="Type Project Name...">
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Item Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="item_name" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all" placeholder="e.g. Modem, SIM Card" required>
                </div>

                <div class="lg:col-span-1 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Data Pkg</label>
                        <input type="text" name="data_package" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all" placeholder="Optional">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Qty <span class="text-rose-500">*</span></label>
                        <input type="number" name="qty" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white font-bold outline-none transition-all" value="1" min="1" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                    <h3 class="font-bold text-indigo-600 dark:text-indigo-400 text-sm uppercase tracking-widest flex items-center gap-2">
                        <i class="ph-fill ph-paper-plane-right text-lg"></i> Sender Information
                    </h3>
                    <button type="button" onclick="toggleInput('sender')" class="text-[10px] font-bold px-2 py-1 bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 rounded hover:bg-indigo-200 transition-colors">
                        + Create New Sender
                    </button>
                </div>
                
                <div class="p-6 space-y-4 flex-1">
                    <div id="sender_select_wrap" class="relative">
                        <select id="sender_select" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none appearance-none font-bold">
                            <option value="">- Select Saved Sender -</option>
                            <option value="1">PT Linksfield Networks</option>
                        </select>
                        <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Company</label>
                            <input type="text" name="sender_company" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-indigo-500 transition-all sender-input" placeholder="Company Name" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PIC Name</label>
                            <input type="text" name="sender_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-indigo-500 transition-all sender-input" placeholder="Person in Charge" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Phone Number</label>
                        <input type="text" name="sender_phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-indigo-500 transition-all sender-input" placeholder="+62 812...">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Complete Address</label>
                        <textarea name="sender_address" rows="3" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-indigo-500 transition-all resize-none sender-input" placeholder="Full address..."></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                    <h3 class="font-bold text-emerald-600 dark:text-emerald-400 text-sm uppercase tracking-widest flex items-center gap-2">
                        <i class="ph-fill ph-map-pin text-lg"></i> Receiver Information
                    </h3>
                    <button type="button" onclick="toggleInput('receiver')" class="text-[10px] font-bold px-2 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 rounded hover:bg-emerald-200 transition-colors">
                        + Create New Receiver
                    </button>
                </div>
                
                <div class="p-6 space-y-4 flex-1">
                    <div id="receiver_select_wrap" class="relative">
                        <select id="receiver_select" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:text-white outline-none appearance-none font-bold">
                            <option value="">- Select Saved Receiver -</option>
                            <option value="1">Client ABC</option>
                        </select>
                        <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Company</label>
                            <input type="text" name="receiver_company" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all receiver-input" placeholder="Company Name" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PIC Name</label>
                            <input type="text" name="receiver_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all receiver-input" placeholder="Person in Charge" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Phone Number</label>
                        <input type="text" name="receiver_phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all receiver-input" placeholder="+62 812...">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Complete Address</label>
                        <textarea name="receiver_address" rows="3" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all resize-none receiver-input" placeholder="Full address..."></textarea>
                    </div>
                </div>
            </div>

        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-soft border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <h3 class="font-bold text-indigo-600 dark:text-indigo-400 text-sm uppercase tracking-widest flex items-center gap-2">
                    <i class="ph-fill ph-truck text-lg"></i> Delivery Information
                </h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Courier Name <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <i class="ph-fill ph-moped absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <select name="courier_name" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all appearance-none cursor-pointer" required>
                            <option value="">- Select Courier -</option>
                            <option value="JNE">JNE</option>
                            <option value="J&T">J&T Express</option>
                            <option value="SICEPAT">SiCepat</option>
                            <option value="ANTERAJA">AnterAja</option>
                            <option value="GOJEK">GoSend / Grab</option>
                            <option value="OTHER">Other Courier</option>
                        </select>
                        <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Tracking No / Resi</label>
                    <div class="relative">
                        <i class="ph-fill ph-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="text" name="tracking_number" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white font-mono font-bold uppercase outline-none transition-all" placeholder="e.g. JP123456789">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Additional Notes</label>
                    <textarea name="notes" rows="2" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white outline-none transition-all resize-none" placeholder="Any specific instructions or notes..."></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2 pb-10">
            <a href="delivery_list.php" class="px-6 py-3.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-8 py-3.5 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2 active:scale-95">
                <i class="ph-bold ph-floppy-disk text-lg"></i> Save Delivery Data
            </button>
        </div>

    </form>
</div>

<script>
    // Fungsi untuk Toggle antara Select (Pilih Data Lama) dan Input (Buat Data Baru)
    function toggleInput(type) {
        const selectWrap = document.getElementById(type + '_select_wrap');
        const inputWrap = document.getElementById(type + '_input_wrap');
        const selectEl = document.getElementById(type + '_select');
        
        if (type === 'project') {
            if (selectWrap.classList.contains('hidden')) {
                // Balik ke mode Select
                selectWrap.classList.remove('hidden');
                inputWrap.classList.add('hidden');
                document.getElementById(type + '_input').value = '';
            } else {
                // Mode Input Manual
                selectWrap.classList.add('hidden');
                inputWrap.classList.remove('hidden');
                selectEl.value = '';
            }
        } 
        else if (type === 'sender' || type === 'receiver') {
            // Untuk Sender & Receiver (mengosongkan form agar bisa diisi manual)
            const inputs = document.querySelectorAll(`.${type}-input`);
            inputs.forEach(input => {
                input.value = '';
                input.readOnly = false; // Buka kunci form
                input.classList.remove('bg-slate-100', 'dark:bg-slate-800/50');
            });
            document.getElementById(type + '_select').value = '';
        }
    }

    // Dummy script untuk auto-fill Sender/Receiver jika dropdown di klik
    document.addEventListener('DOMContentLoaded', () => {
        ['sender', 'receiver'].forEach(type => {
            const selectEl = document.getElementById(type + '_select');
            if(selectEl) {
                selectEl.addEventListener('change', function() {
                    const inputs = document.querySelectorAll(`.${type}-input`);
                    if(this.value !== '') {
                        // Simulasi Auto Fill (Di sistem nyata gunakan AJAX)
                        inputs[0].value = (type === 'sender') ? 'PT Linksfield Networks' : 'Client ABC';
                        inputs[1].value = 'John Doe';
                        inputs[2].value = '+62 812 3456 7890';
                        inputs[3].value = 'Jl. Jend. Sudirman No.1, Jakarta Pusat';
                        
                        // Kunci Input agar tidak diubah sembarangan jika pakai data master
                        inputs.forEach(input => {
                            input.readOnly = true;
                            input.classList.add('bg-slate-100', 'dark:bg-slate-800/50');
                        });
                    } else {
                        // Kosongkan dan buka form kembali
                        inputs.forEach(input => {
                            input.value = '';
                            input.readOnly = false;
                            input.classList.remove('bg-slate-100', 'dark:bg-slate-800/50');
                        });
                    }
                });
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>