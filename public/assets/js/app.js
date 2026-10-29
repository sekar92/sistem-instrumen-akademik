/**
 * SIMIA - Core Client Script & Interactions
 * Acuan: skills.md & PRD SIMIA
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Drawer Navigation Toggle
    const mobileMenuBtn = document.getElementById('btnMobileMenu');
    const mobileDrawer = document.getElementById('mobileDrawer');
    const mobileDrawerBackdrop = document.getElementById('mobileDrawerBackdrop');
    const btnCloseDrawer = document.getElementById('btnCloseDrawer');

    if (mobileMenuBtn && mobileDrawer) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileDrawer.classList.remove('hidden');
            setTimeout(() => {
                mobileDrawer.classList.remove('-translate-x-full');
            }, 10);
        });
    }

    const closeDrawer = () => {
        if (mobileDrawer) {
            mobileDrawer.classList.add('-translate-x-full');
            setTimeout(() => {
                mobileDrawer.classList.add('hidden');
            }, 200);
        }
    };

    if (mobileDrawerBackdrop) mobileDrawerBackdrop.addEventListener('click', closeDrawer);
    if (btnCloseDrawer) btnCloseDrawer.addEventListener('click', closeDrawer);

    // 2. Profile Dropdown Toggle
    const profileDropdownBtn = document.getElementById('profileDropdownBtn');
    const profileDropdownMenu = document.getElementById('profileDropdownMenu');

    if (profileDropdownBtn && profileDropdownMenu) {
        profileDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdownMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!profileDropdownMenu.contains(e.target) && !profileDropdownBtn.contains(e.target)) {
                profileDropdownMenu.classList.add('hidden');
            }
        });
    }

    // 3. Auto dismiss flash alerts after 6 seconds
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 6000);
    });

    // 4. Inisialisasi Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

/**
 * Modal Management Helpers
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

/**
 * Progressive Upload Animation (Acuan: skills.md Bagian 5.3)
 */
function submitWithProgressiveAnimation(formElement, submitButtonId, statusContainerId) {
    const btn = document.getElementById(submitButtonId);
    const container = document.getElementById(statusContainerId);

    if (!formElement.checkValidity()) {
        formElement.reportValidity();
        return false;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `
            <svg class="animate-spin h-4 w-4 mr-2 inline-block text-white" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8z"></path>
            </svg>
            Memproses...
        `;
    }

    if (container) {
        container.classList.remove('hidden');
        const steps = [
            '1. Memvalidasi format & integritas berkas...',
            '2. Mengompresi & memeriksa keamanan MIME type...',
            '3. Mengunggah berkas ke server penyimpanan...',
            '4. Menyimpan tautan & mencatat log audit trail...'
        ];
        
        let i = 0;
        container.innerHTML = `<p class="text-xs text-sky-700 font-semibold animate-pulse">${steps[0]}</p>`;
        
        const interval = setInterval(() => {
            i++;
            if (i < steps.length) {
                container.innerHTML = `<p class="text-xs text-sky-700 font-semibold animate-pulse">${steps[i]}</p>`;
            } else {
                clearInterval(interval);
            }
        }, 500);
    }

    setTimeout(() => {
        formElement.submit();
    }, 1200);

    return false;
}

/**
 * Excel / TSV Parser for Bulk Import Mata Kuliah (PRD FR-3.4)
 */
function parseExcelPaste(tsvText) {
    const rows = tsvText.trim().split(/\r\n|\n|\r/);
    const result = [];

    rows.forEach((row, index) => {
        if (!row.trim()) return;
        const cols = row.split('\t').map(c => c.trim());
        // Kolom yang diharapkan: Kode MK, Nama MK, SKS, Semester, Deskripsi (opsional)
        if (cols.length >= 2) {
            result.push({
                kode: cols[0] || '',
                nama: cols[1] || '',
                sks: parseInt(cols[2], 10) || 2,
                semester: cols[3] || 'Ganjil',
                deskripsi: cols[4] || ''
            });
        }
    });

    return result;
}
