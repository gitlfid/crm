<?php
$page_title = "Input Delivery Request";
include 'includes/header.php';
include 'includes/sidebar.php';
// require_once '../config/database.php'; // Aktifkan jika butuh query dropdown
?>

<style>
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .step-line { position: absolute; left: 1.25rem; top: 3rem; bottom: -2rem; width: 2px; background-color: #e2e8f0; z-index: 0; }
    .dark .step-line { background-color: #334155; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-5xl mx-auto min-h-screen bg-slate-50 dark:bg-[#1A222C] transition-colors duration-300">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 animate-fade-in-up">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Delivery Request</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Alur pengajuan pengiriman SIM Card / Perangkat IoT.</p>
        </div>
        <a href="delivery_list.php" class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all active:scale-95">
            <i class="ph-bold ph-arrow-left text-lg"></i> Back to List
        </a>
    </div>

    <form action="process_delivery.php" method="POST" enctype="multipart/form-data" class="space-y-8 animate-fade-in-up" style="animation-delay: 0.1s;">
        <input type="hidden" name="action" value="create_logistic">

        <div class="relative">
            <div class="step-line hidden md:block"></div>
            
            <div class="flex flex-col md:flex-row gap-4 md:gap-6 relative z-10">
                <div class="shrink-0 flex items-start">
                    <div class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-indigo-600/30 border-4 border-slate-50 dark:border-[#1A222C]">1</div>
                </div>
                
                <div class="flex-1 bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-indigo-50/50 dark:bg-indigo-500/10 flex justify-between items-center">
                        <div>
                            <h3 class="font-black text-indigo-600 dark:text-indigo-400 text-sm uppercase tracking-widest flex items-center gap-2">
                                <i class="ph-fill ph-hard-drives text-lg"></i> IT Department Section
                            </h3>
                            <p class="text-[10px] text-slate-500 font-bold mt-0.5">Submit Delivery Request Data</p>
                        </div>
                    </div>
                    
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Client Name <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                <select name="client_id" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none appearance-none font-bold" required>
                                    <option value="">- Select Client from Database -</option>
                                    <option value="1">PT Linksfield Networks</option>
                                    <option value="2">Client ABC</option>
                                </select>
                                <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Card Type / Item Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="item_name" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" placeholder="e.g. M2M SIM Card" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Card Qty <span class="text-rose-500">*</span></label>
                            <input type="number" name="qty" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white font-bold outline-none transition-all" value="1" min="1" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Data Package</label>
                            <input type="text" name="data_package" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all" placeholder="e.g. 100MB / 1GB">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Request Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="delivery_date" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-indigo-500 dark:text-white outline-none transition-all font-medium" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="lg:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Ref Invoice (File/No)</label>
                                    <input type="text" name="invoice_ref" class="w-full px-4 py-2.5 mb-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white" placeholder="Nomor Invoice...">
                                    <input type="file" name="invoice_file" class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Ref PO (File/No)</label>
                                    <input type="text" name="po_ref" class="w-full px-4 py-2.5 mb-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white" placeholder="Nomor Purchase Order...">
                                    <input type="file" name="po_file" class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="relative">
            <div class="flex flex-col md:flex-row gap-4 md:gap-6 relative z-10">
                <div class="shrink-0 flex items-start">
                    <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-emerald-500/30 border-4 border-slate-50 dark:border-[#1A222C]">2</div>
                </div>
                
                <div class="flex-1 bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-emerald-50/50 dark:bg-emerald-500/10 flex justify-between items-center">
                        <div>
                            <h3 class="font-black text-emerald-600 dark:text-emerald-400 text-sm uppercase tracking-widest flex items-center gap-2">
                                <i class="ph-fill ph-truck text-lg"></i> HR / Logistics Section
                            </h3>
                            <p class="text-[10px] text-slate-500 font-bold mt-0.5">Continue Submit Process (Address & Tracking)</p>
                        </div>
                    </div>
                    
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div class="md:col-span-2 space-y-4">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2">Receiver Info</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PIC Name</label>
                                    <input type="text" name="receiver_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all" placeholder="Person in Charge">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Phone Number</label>
                                    <input type="text" name="receiver_phone" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all" placeholder="+62 812...">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Full Receiver Address</label>
                                <textarea name="receiver_address" rows="2" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm dark:text-white outline-none focus:border-emerald-500 transition-all resize-none" placeholder="Detailed address for delivery..."></textarea>
                            </div>
                        </div>

                        <div class="md:col-span-2 pt-2 border-t border-slate-100 dark:border-slate-700 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Delivery Method (Courier)</label>
                                <div class="relative">
                                    <i class="ph-fill ph-moped absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <select name="courier_name" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 dark:text-white outline-none transition-all appearance-none cursor-pointer">
                                        <option value="">- Select Courier -</option>
                                        <option value="JNE">JNE</option>
                                        <option value="J&T">J&T Express</option>
                                        <option value="SICEPAT">SiCepat</option>
                                        <option value="GOJEK">GoSend / Grab</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tracking Number (Resi)</label>
                                <div class="relative">
                                    <i class="ph-fill ph-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="text" name="tracking_number" class="w-full pl-11 pr-4 py-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/30 rounded-xl focus:ring-2 focus:ring-amber-500 dark:text-white font-mono font-bold uppercase outline-none transition-all" placeholder="Leave empty if not shipped yet">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-6 pb-10">
            <a href="delivery_list.php" class="px-6 py-3.5 rounded-xl font-bold text-slate-600 bg-white hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-8 py-3.5 rounded-xl font-bold text-white bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-700 shadow-lg transition-all flex items-center gap-2 active:scale-95">
                <i class="ph-bold ph-paper-plane-tilt text-lg"></i> Submit & Process Delivery
            </button>
        </div>

    </form>
</div>

<?php include 'includes/footer.php'; ?>