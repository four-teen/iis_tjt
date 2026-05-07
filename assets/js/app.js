(function () {
    var toggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }
})();

(function () {
    var openButtons = document.querySelectorAll('[data-modal-open]');
    var closeButtons = document.querySelectorAll('[data-modal-close]');

    function openModal(id) {
        var modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');

        var firstField = modal.querySelector('input, select, textarea, button');
        if (firstField) {
            firstField.focus();
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.hidden = true;

        if (!document.querySelector('.modal.is-open')) {
            document.body.classList.remove('modal-open');
        }
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.getAttribute('data-modal-open'));
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.closest('.modal'));
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.modal.is-open').forEach(function (modal) {
            closeModal(modal);
        });
    });
})();

(function () {
    var lists = document.querySelectorAll('[data-infinite-list]');

    lists.forEach(function (list) {
        var pageSize = parseInt(list.getAttribute('data-page-size'), 10) || 20;
        var visible = pageSize;
        var items = Array.prototype.slice.call(list.querySelectorAll('[data-infinite-item]'));
        var scroller = list.closest('[data-infinite-scroll]') || window;
        var status = document.querySelector('[data-infinite-status="' + list.id + '"]');

        function render() {
            items.forEach(function (item, index) {
                item.hidden = index >= visible;
            });

            if (status) {
                status.textContent = 'Showing ' + Math.min(visible, items.length) + ' of ' + items.length + ' records';
            }
        }

        function canLoadMore() {
            return visible < items.length;
        }

        function nearBottom() {
            if (scroller === window) {
                return window.innerHeight + window.scrollY >= document.body.offsetHeight - 240;
            }

            return scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 120;
        }

        function loadMore() {
            if (canLoadMore() && nearBottom()) {
                visible += pageSize;
                render();
            }
        }

        render();

        if (items.length > pageSize) {
            scroller.addEventListener('scroll', loadMore);
        }
    });
})();
