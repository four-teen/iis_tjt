(function () {
    var toggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');

    if (!toggle || !sidebar) {
        return;
    }

    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('is-open');
    });
})();
