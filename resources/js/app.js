import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Select avec recherche (combobox) — Tailwind + Alpine, sans Bootstrap/Select2
// Exposé sur window pour éviter conflits avec autres instances Alpine (Laravel Boost, etc.)
const searchableSelectFn = (config) => {
    const cfg = config || {};
    return {
        options: cfg.options || [],
        selectedValue: String(cfg.selected ?? ''),
        selectedLabel: '',
        search: '',
        isOpen: false,
        name: cfg.name || 'select',
        placeholder: cfg.placeholder || 'Choisir...',

        init() {
            const opt = this.options.find(o => String(o.value) === String(this.selectedValue));
            this.selectedLabel = opt ? opt.label : '';
        },

        filteredOptions() {
            if (!String(this.search || '').trim()) return this.options;
            const q = String(this.search).toLowerCase();
            return this.options.filter(o => String(o.label || '').toLowerCase().includes(q));
        },

        select(option) {
            this.selectedValue = String(option.value ?? '');
            this.selectedLabel = option.label || '';
            this.search = '';
            this.isOpen = false;
        },

        clear() {
            this.selectedValue = '';
            this.selectedLabel = '';
            this.search = '';
            this.isOpen = false;
        }
    };
};
window.searchableSelect = searchableSelectFn;
Alpine.data('searchableSelect', searchableSelectFn);
Alpine.start();
