</main>
<script>
function formatoMiles(digitos) {
    return digitos === '' ? '' : digitos.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

document.querySelectorAll('[data-moneda]').forEach(function (input) {
    input.addEventListener('input', function () {
        input.value = formatoMiles(input.value.replace(/\D/g, ''));
        input.selectionStart = input.selectionEnd = input.value.length;
    });
});

document.addEventListener('submit', function (e) {
    e.target.querySelectorAll('[data-moneda]').forEach(function (input) {
        input.value = input.value.replace(/\D/g, '');
    });

    var btn = e.target.querySelector('button[type="submit"]');
    if (!btn || btn.disabled) return;
    btn.dataset.originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Guardando…';
});
</script>
</body>
</html>
