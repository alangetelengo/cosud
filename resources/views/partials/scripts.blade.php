{{-- Scripts globaux --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // CSRF pour AJAX
    if (typeof $ !== 'undefined') {
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' }
        });
    }
});
</script>
