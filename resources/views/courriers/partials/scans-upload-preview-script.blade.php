<script>
window.scansUploadPreview = function (required) {
    return {
        required: !!required,
        files: [],
        init() {},
        statusLabel() {
            const n = this.files.length;
            if (n === 0) return 'Aucun fichier choisi';
            if (n === 1) return this.files[0].name;
            return n + ' fichiers sélectionnés';
        },
        onSelect(event) {
            const list = Array.from(event.target.files || []);
            this.replaceFiles(list);
        },
        replaceFiles(list) {
            this.revokeAll();
            this.files = list.map((file, i) => {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const type = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)
                    ? 'image'
                    : (ext === 'pdf' ? 'pdf' : 'other');
                return {
                    key: file.name + '-' + file.size + '-' + i + '-' + Date.now(),
                    name: file.name,
                    file,
                    type,
                    url: URL.createObjectURL(file),
                };
            });
            this.syncInput();
        },
        removeAt(index) {
            const [removed] = this.files.splice(index, 1);
            if (removed?.url) URL.revokeObjectURL(removed.url);
            this.syncInput();
        },
        syncInput() {
            const input = this.$refs.fileInput;
            if (!input) return;
            const dt = new DataTransfer();
            this.files.forEach((item) => dt.items.add(item.file));
            input.files = dt.files;
        },
        revokeAll() {
            this.files.forEach((item) => {
                if (item.url) URL.revokeObjectURL(item.url);
            });
            this.files = [];
        },
    };
};
</script>
