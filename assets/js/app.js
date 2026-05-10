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
    var forms = document.querySelectorAll('[data-bulk-delete-form]');

    forms.forEach(function (form) {
        var toggle = form.querySelector('[data-bulk-delete-toggle]');
        var button = form.querySelector('[data-bulk-delete-button]');
        var counter = form.querySelector('[data-bulk-delete-count]');
        var label = form.getAttribute('data-bulk-delete-label') || 'records';
        var selectedValues = {};

        function items() {
            return Array.prototype.slice.call(form.querySelectorAll('[data-bulk-delete-item]')).filter(function (item) {
                return !item.disabled;
            });
        }

        function selectedCount() {
            return Object.keys(selectedValues).length;
        }

        function syncVisibleItems() {
            items().forEach(function (item) {
                item.checked = !!selectedValues[item.value];
            });
        }

        function clearHiddenFields() {
            form.querySelectorAll('[data-bulk-delete-hidden]').forEach(function (item) {
                item.remove();
            });
        }

        function appendHiddenFields() {
            clearHiddenFields();

            Object.keys(selectedValues).forEach(function (value) {
                var input = document.createElement('input');

                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = value;
                input.setAttribute('data-bulk-delete-hidden', '1');
                form.appendChild(input);
            });
        }

        function updateState() {
            var available = items();
            var checkedVisible = available.filter(function (item) {
                return !!selectedValues[item.value];
            });
            var count = selectedCount();

            if (button) {
                button.disabled = count === 0;
            }

            if (counter) {
                counter.textContent = count + ' selected';
            }

            if (toggle) {
                toggle.disabled = available.length === 0;
                toggle.checked = available.length > 0 && checkedVisible.length === available.length;
                toggle.indeterminate = checkedVisible.length > 0 && checkedVisible.length < available.length;
            }
        }

        if (toggle) {
            toggle.addEventListener('change', function () {
                items().forEach(function (item) {
                    if (toggle.checked) {
                        selectedValues[item.value] = true;
                    } else {
                        delete selectedValues[item.value];
                    }
                });
                syncVisibleItems();
                updateState();
            });
        }

        form.addEventListener('change', function (event) {
            if (event.target.matches('[data-bulk-delete-item]')) {
                if (event.target.checked) {
                    selectedValues[event.target.value] = true;
                } else {
                    delete selectedValues[event.target.value];
                }

                updateState();
            }
        });

        form.addEventListener('submit', function (event) {
            var selected = selectedCount();

            if (selected === 0) {
                event.preventDefault();
                updateState();
                return;
            }

            if (!window.confirm('Delete ' + selected + ' selected ' + label + '?')) {
                event.preventDefault();
                return;
            }

            appendHiddenFields();
        });

        form.addEventListener('bulk-delete-refresh', function () {
            syncVisibleItems();
            updateState();
        });

        syncVisibleItems();
        updateState();
    });
})();

(function () {
    var tables = document.querySelectorAll('table.record-table');

    function cellText(row, index) {
        var cell = row.cells[index];

        if (!cell) {
            return '';
        }

        return cell.textContent.trim().replace(/\s+/g, ' ');
    }

    function makePageSizeOptions(defaultSize) {
        var sizes = [10, 20, 25, 50, 100];

        if (sizes.indexOf(defaultSize) === -1) {
            sizes.unshift(defaultSize);
        }

        return sizes.map(function (size) {
            var selected = size === defaultSize ? ' selected' : '';
            return '<option value="' + size + '"' + selected + '>' + size + '</option>';
        }).join('') + '<option value="all">All</option>';
    }

    function pageWindow(currentPage, maxPage) {
        var pages = [];
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(maxPage, start + 4);

        start = Math.max(1, end - 4);

        for (var page = start; page <= end; page += 1) {
            pages.push(page);
        }

        return pages;
    }

    function pageItem(label, page, className, disabled, active) {
        var itemClass = 'page-item' + (className ? ' ' + className : '') + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        var disabledAttr = disabled ? ' disabled aria-disabled="true"' : '';
        var activeAttr = active ? ' aria-current="page"' : '';

        return '<li class="' + itemClass + '">'
            + '<button type="button" class="page-link" data-datatable-page="' + page + '"' + disabledAttr + activeAttr + '>' + label + '</button>'
            + '</li>';
    }

    function hideLegacyStatus(tbody) {
        if (!tbody.id) {
            return;
        }

        var legacyStatus = document.querySelector('[data-infinite-status="' + tbody.id + '"]');

        if (legacyStatus) {
            legacyStatus.hidden = true;
        }
    }

    function applyBootstrapTableClasses(table) {
        table.classList.add('table', 'table-striped', 'table-hover', 'align-middle');
    }

    function hasOfficialDataTable() {
        return window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable;
    }

    function hasPendingTables() {
        return Array.prototype.some.call(tables, function (table) {
            return !table.dataset.datatableReady;
        });
    }

    function loadScript(src, callback) {
        var script = document.createElement('script');

        script.src = src;
        script.async = true;
        script.onload = function () {
            callback(true);
        };
        script.onerror = function () {
            callback(false);
        };

        document.head.appendChild(script);
    }

    function loadOfficialDataTables(callback) {
        var scripts = [];

        if (!window.jQuery) {
            scripts.push('https://code.jquery.com/jquery-3.7.1.min.js');
        }

        scripts.push('https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js');
        scripts.push('https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js');

        function next(index) {
            if (index >= scripts.length) {
                callback(hasOfficialDataTable());
                return;
            }

            loadScript(scripts[index], function (loaded) {
                if (!loaded) {
                    callback(false);
                    return;
                }

                next(index + 1);
            });
        }

        next(0);
    }

    function initOfficialTables() {
        tables.forEach(function (table) {
            if (table.dataset.datatableReady) {
                return;
            }

            var tbody = table.tBodies[0];

            if (!tbody) {
                return;
            }

            var defaultPageSize = parseInt(tbody.getAttribute('data-page-size'), 10) || 10;
            var emptyRow = tbody.querySelector('.empty-row');
            var disabledTargets = [table.rows[0].cells.length - 1];

            if (table.tHead && table.tHead.querySelector('[data-bulk-delete-toggle]')) {
                disabledTargets.push(0);
            }

            table.dataset.datatableReady = '1';
            applyBootstrapTableClasses(table);
            hideLegacyStatus(tbody);

            if (emptyRow) {
                emptyRow.remove();
            }

            var dataTable = window.jQuery(table).DataTable({
                pageLength: defaultPageSize,
                lengthMenu: [[10, 20, 25, 50, 100, -1], [10, 20, 25, 50, 100, 'All']],
                columnDefs: [
                    {
                        targets: disabledTargets,
                        orderable: false,
                        searchable: false
                    }
                ],
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 to 0 of 0 entries',
                    infoFiltered: '(filtered from _MAX_ total entries)',
                    zeroRecords: 'No matching records found',
                    emptyTable: 'No records found'
                }
            });

            dataTable.on('draw', function () {
                table.dispatchEvent(new CustomEvent('bulk-delete-refresh', { bubbles: true }));
            });
        });
    }

    function initFallbackTables() {
        tables.forEach(function (table) {
        if (table.dataset.datatableReady) {
            return;
        }

        var tbody = table.tBodies[0];
        var headerRow = table.tHead ? table.tHead.rows[0] : null;

        if (!tbody || !headerRow) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.rows).filter(function (row) {
            return !row.classList.contains('empty-row');
        });
        var emptyRow = tbody.querySelector('.empty-row');
        var defaultPageSize = parseInt(tbody.getAttribute('data-page-size'), 10) || 25;
        var pageSize = defaultPageSize;
        var currentPage = 1;
        var sortIndex = -1;
        var sortDirection = 'asc';
        var searchValue = '';
        var shell = table.closest('.table-wrap') || table;
        var parent = shell.parentNode;

        table.dataset.datatableReady = '1';
        table.classList.add('dataTable', 'no-footer');
        applyBootstrapTableClasses(table);
        hideLegacyStatus(tbody);

        var toolbar = document.createElement('div');
        toolbar.className = 'dt-bootstrap5 datatable-controls';
        toolbar.innerHTML = ''
            + '<div class="row g-2 align-items-center mb-3">'
            + '<div class="col-sm-12 col-md-6">'
            + '<div class="dataTables_length">'
            + '<label>Show <select class="form-select form-select-sm" data-datatable-length>'
            + makePageSizeOptions(defaultPageSize)
            + '</select> entries</label>'
            + '</div>'
            + '</div>'
            + '<div class="col-sm-12 col-md-6">'
            + '<div class="dataTables_filter">'
            + '<label>Search:<input type="search" class="form-control form-control-sm" data-datatable-search placeholder=""></label>'
            + '</div>'
            + '</div>'
            + '</div>';

        var footer = document.createElement('div');
        footer.className = 'dt-bootstrap5 datatable-footer';
        footer.innerHTML = ''
            + '<div class="row align-items-center mt-3">'
            + '<div class="col-sm-12 col-md-5">'
            + '<div class="dataTables_info" role="status" aria-live="polite"></div>'
            + '</div>'
            + '<div class="col-sm-12 col-md-7">'
            + '<div class="dataTables_paginate paging_simple_numbers">'
            + '<ul class="pagination"></ul>'
            + '</div>'
            + '</div>'
            + '</div>';

        parent.insertBefore(toolbar, shell);
        parent.insertBefore(footer, shell.nextSibling);

        var searchInput = toolbar.querySelector('[data-datatable-search]');
        var lengthSelect = toolbar.querySelector('[data-datatable-length]');
        var status = footer.querySelector('.dataTables_info');
        var pagination = footer.querySelector('.pagination');
        var headers = Array.prototype.slice.call(headerRow.cells);

        function filteredRows() {
            if (!searchValue) {
                return rows.slice();
            }

            return rows.filter(function (row) {
                return row.textContent.toLowerCase().indexOf(searchValue) !== -1;
            });
        }

        function sortedRows(filtered) {
            if (sortIndex < 0) {
                return filtered;
            }

            return filtered.slice().sort(function (left, right) {
                var leftText = cellText(left, sortIndex);
                var rightText = cellText(right, sortIndex);
                var direction = sortDirection === 'asc' ? 1 : -1;

                return leftText.localeCompare(rightText, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                }) * direction;
            });
        }

        function renderPagination(maxPage, total) {
            var pages = pageWindow(currentPage, maxPage);
            var html = '';

            html += pageItem('Previous', Math.max(1, currentPage - 1), 'previous', currentPage <= 1 || total === 0, false);
            pages.forEach(function (page) {
                html += pageItem(String(page), page, '', total === 0, page === currentPage && total !== 0);
            });
            html += pageItem('Next', Math.min(maxPage, currentPage + 1), 'next', currentPage >= maxPage || total === 0, false);

            pagination.innerHTML = html;

            pagination.querySelectorAll('[data-datatable-page]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = parseInt(button.getAttribute('data-datatable-page'), 10);

                    if (!button.disabled && target && target !== currentPage) {
                        currentPage = target;
                        render();
                    }
                });
            });
        }

        function render() {
            var filtered = sortedRows(filteredRows());
            var total = filtered.length;
            var sourceTotal = rows.length;
            var effectivePageSize = pageSize === 'all' ? Math.max(total, 1) : pageSize;
            var maxPage = Math.max(1, Math.ceil(total / effectivePageSize));
            var start;
            var end;

            if (currentPage > maxPage) {
                currentPage = maxPage;
            }

            start = total ? (currentPage - 1) * effectivePageSize : 0;
            end = pageSize === 'all' ? total : Math.min(start + effectivePageSize, total);

            rows.forEach(function (row) {
                row.hidden = true;
            });

            filtered.forEach(function (row, index) {
                row.hidden = index < start || index >= end;
                tbody.appendChild(row);
            });

            if (emptyRow) {
                emptyRow.hidden = total !== 0;
                tbody.appendChild(emptyRow);
            }

            status.textContent = total
                ? 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries'
                : 'Showing 0 to 0 of 0 entries';

            if (searchValue && total !== sourceTotal) {
                status.textContent += ' (filtered from ' + sourceTotal + ' total entries)';
            }

            renderPagination(maxPage, total);
            table.dispatchEvent(new CustomEvent('bulk-delete-refresh', { bubbles: true }));
        }

        headers.forEach(function (header, index) {
            var label = header.textContent.trim();

            if (!label || index === headers.length - 1) {
                return;
            }

            header.classList.add('sorting');
            header.tabIndex = 0;
            header.setAttribute('role', 'button');
            header.setAttribute('aria-sort', 'none');

            function sortByColumn() {
                if (sortIndex === index) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortIndex = index;
                    sortDirection = 'asc';
                }

                headers.forEach(function (item) {
                    item.classList.remove('sorting_asc', 'sorting_desc');
                    if (item.textContent.trim()) {
                        item.classList.add('sorting');
                    }
                    item.setAttribute('aria-sort', 'none');
                });

                header.classList.remove('sorting');
                header.classList.add(sortDirection === 'asc' ? 'sorting_asc' : 'sorting_desc');
                header.setAttribute('aria-sort', sortDirection === 'asc' ? 'ascending' : 'descending');
                currentPage = 1;
                render();
            }

            header.addEventListener('click', sortByColumn);
            header.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sortByColumn();
                }
            });
        });

        searchInput.addEventListener('input', function () {
            searchValue = searchInput.value.trim().toLowerCase();
            currentPage = 1;
            render();
        });

        lengthSelect.addEventListener('change', function () {
            pageSize = lengthSelect.value === 'all' ? 'all' : parseInt(lengthSelect.value, 10);
            currentPage = 1;
            render();
        });

        render();
        });
    }

    if (hasOfficialDataTable()) {
        initOfficialTables();
        return;
    }

    window.setTimeout(initFallbackTables, 120);
    loadOfficialDataTables(function (loaded) {
        if (!loaded || !hasPendingTables()) {
            return;
        }

        initOfficialTables();
    });
})();

(function () {
    var lists = document.querySelectorAll('[data-infinite-list]');

    lists.forEach(function (list) {
        if (list.closest('table.record-table')) {
            return;
        }

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
