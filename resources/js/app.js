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
        searchPlaceholder: cfg.searchPlaceholder || 'Rechercher...',

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

/**
 * Multi-destinataires façon Gmail : un champ, chips, recherche.
 * options: [{value, label, search?}]
 * selected: string[] des values déjà choisies
 * name: nom du champ HTML (ex. agent_confie_ids[])
 */
const searchableMultiSelectFn = (config) => {
    const cfg = config || {};
    return {
        options: cfg.options || [],
        selectedValues: (cfg.selected || []).map((v) => String(v)),
        search: '',
        isOpen: false,
        name: cfg.name || 'ids[]',
        placeholder: cfg.placeholder || 'Ajouter des destinataires…',
        searchPlaceholder: cfg.searchPlaceholder || 'Rechercher une fonction, une structure…',

        selectedOptions() {
            const map = new Map(this.options.map((o) => [String(o.value), o]));
            return this.selectedValues
                .map((v) => map.get(String(v)))
                .filter(Boolean);
        },

        filteredOptions() {
            const selected = new Set(this.selectedValues.map(String));
            const raw = String(this.search || '').trim().toLowerCase();
            const tokens = raw ? raw.split(/\s+/).filter(Boolean) : [];

            return this.options.filter((o) => {
                if (selected.has(String(o.value))) {
                    return false;
                }
                if (!tokens.length) {
                    return true;
                }
                const hay = (String(o.label || '') + ' ' + String(o.search || '')).toLowerCase();

                return tokens.every((t) => hay.includes(t));
            });
        },

        add(option) {
            const value = String(option.value ?? '');
            if (!value || this.selectedValues.includes(value)) {
                return;
            }
            this.selectedValues.push(value);
            this.search = '';
            this.isOpen = true;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        remove(value) {
            const v = String(value);
            this.selectedValues = this.selectedValues.filter((x) => x !== v);
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        onKeydown(event) {
            if (event.key === 'Backspace' && !String(this.search || '') && this.selectedValues.length) {
                this.selectedValues.pop();
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                const first = this.filteredOptions()[0];
                if (first) {
                    this.add(first);
                }
            }
            if (event.key === 'Escape') {
                this.isOpen = false;
            }
        },
    };
};

window.searchableSelect = searchableSelectFn;
window.searchableMultiSelect = searchableMultiSelectFn;
Alpine.data('searchableSelect', searchableSelectFn);
Alpine.data('searchableMultiSelect', searchableMultiSelectFn);

/** Montant FCFA : espaces comme séparateurs de milliers (ex. 1 949 700). */
function formatMontantFcfa(value) {
    const chiffres = String(value ?? '').replace(/\D/g, '');
    if (!chiffres) {
        return '';
    }

    return chiffres.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

window.formatMontantFcfa = formatMontantFcfa;
Alpine.data('montantFcfa', (initial = '') => ({
    montant: formatMontantFcfa(initial),
    format: formatMontantFcfa,
}));

Alpine.start();
