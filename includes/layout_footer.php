</main>
<script>
document.addEventListener('submit', function (e) {
    var btn = e.target.querySelector('button[type="submit"]');
    if (!btn || btn.disabled) return;
    btn.dataset.originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Guardando…';
});
</script>
</body>
</html>
