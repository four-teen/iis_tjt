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

        var firstField = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
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
    function initBulkDeleteForms(root) {
        var forms = (root || document).querySelectorAll('[data-bulk-delete-form]');

        forms.forEach(function (form) {
        if (form.dataset.bulkDeleteReady) {
            return;
        }

        form.dataset.bulkDeleteReady = '1';

        var toggle = form.querySelector('[data-bulk-delete-toggle]');
        var button = form.querySelector('[data-bulk-delete-button]');
        var counter = form.querySelector('[data-bulk-delete-count]');
        var label = form.getAttribute('data-bulk-delete-label') || 'records';
        var action = form.getAttribute('data-bulk-delete-action') || 'Delete';
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

            if (!window.confirm(action + ' ' + selected + ' selected ' + label + '?')) {
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
    }

    window.initBulkDeleteForms = initBulkDeleteForms;
    initBulkDeleteForms(document);
})();

(function () {
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

    function recordTables(root) {
        return Array.prototype.slice.call((root || document).querySelectorAll('table.record-table'));
    }

    function hasOfficialDataTable() {
        return window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable;
    }

    function hasPendingTables(root) {
        return recordTables(root).some(function (table) {
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

    function initOfficialTables(root) {
        recordTables(root).forEach(function (table) {
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
            var bulkToggle = table.tHead ? table.tHead.querySelector('[data-bulk-delete-toggle]') : null;

            if (bulkToggle && bulkToggle.closest('th')) {
                disabledTargets.push(Array.prototype.indexOf.call(bulkToggle.closest('tr').cells, bulkToggle.closest('th')));
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

    function initFallbackTables(root) {
        recordTables(root).forEach(function (table) {
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

    function initRecordTables(root) {
        if (hasOfficialDataTable()) {
            initOfficialTables(root);
            return;
        }

        window.setTimeout(function () {
            initFallbackTables(root);
        }, 120);
        loadOfficialDataTables(function (loaded) {
            if (!loaded || !hasPendingTables(root)) {
                return;
            }

            initOfficialTables(root);
        });
    }

    window.initRecordTables = initRecordTables;
    initRecordTables(document);
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

(function () {
    if (!document.querySelector('[data-fleet-profile-page]') || !window.fetch || !window.DOMParser) {
        return;
    }

    function crewPanel() {
        return document.querySelector('[data-fleet-fragment="crew-panel"]');
    }

    function setFormBusy(form, submitter, isBusy) {
        var controls = Array.prototype.slice.call(form.querySelectorAll('button, input, select, textarea'));

        if (submitter && submitter.matches && submitter.matches('button, input, select, textarea') && controls.indexOf(submitter) === -1) {
            controls.push(submitter);
        }

        controls.forEach(function (control) {
            if (isBusy) {
                control.dataset.fleetWasDisabled = control.disabled ? '1' : '0';
                control.disabled = true;
            } else if (control.dataset.fleetWasDisabled === '0') {
                control.disabled = false;
                delete control.dataset.fleetWasDisabled;
            } else {
                delete control.dataset.fleetWasDisabled;
            }
        });
    }

    function showPanelMessage(panel, type, message) {
        var oldMessage = panel.querySelector('[data-fleet-ajax-message]');
        var header = panel.querySelector('.panel-header');
        var alert = document.createElement('div');

        if (oldMessage) {
            oldMessage.remove();
        }

        alert.className = 'alert alert-' + type;
        alert.setAttribute('role', 'alert');
        alert.setAttribute('data-fleet-ajax-message', '1');
        alert.textContent = message;

        if (header && header.nextSibling) {
            panel.insertBefore(alert, header.nextSibling);
        } else {
            panel.insertBefore(alert, panel.firstChild);
        }
    }

    function copyMessages(doc, panel) {
        var alerts = Array.prototype.slice.call(doc.querySelectorAll('[data-fleet-alerts] .alert'));
        var header = panel.querySelector('.panel-header');
        var oldMessage = panel.querySelector('[data-fleet-ajax-message]');
        var container;

        if (oldMessage) {
            oldMessage.remove();
        }

        if (!alerts.length) {
            return;
        }

        container = document.createElement('div');
        container.setAttribute('data-fleet-ajax-message', '1');

        alerts.forEach(function (alert) {
            container.appendChild(alert.cloneNode(true));
        });

        if (header && header.nextSibling) {
            panel.insertBefore(container, header.nextSibling);
        } else {
            panel.insertBefore(container, panel.firstChild);
        }
    }

    function refreshCrewPanel(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var currentPanel = crewPanel();
        var nextPanel = doc.querySelector('[data-fleet-fragment="crew-panel"]');
        var pageAlerts = document.querySelector('[data-fleet-alerts]');

        if (!currentPanel || !nextPanel) {
            throw new Error('The updated crew assignment panel was not found.');
        }

        currentPanel.replaceWith(nextPanel);
        copyMessages(doc, nextPanel);

        if (pageAlerts) {
            pageAlerts.innerHTML = '';
        }

        if (window.initBulkDeleteForms) {
            window.initBulkDeleteForms(nextPanel);
        }

        if (window.initRecordTables) {
            window.initRecordTables(nextPanel);
        }
    }

    function submitCrewForm(form, submitter) {
        var action = form.getAttribute('action') || window.location.href;
        var panel = form.closest('[data-fleet-fragment="crew-panel"]') || crewPanel();
        var formData = new FormData(form);
        var scrollX = window.scrollX;
        var scrollY = window.scrollY;

        if (form.dataset.fleetAjaxBusy === '1') {
            return;
        }

        form.dataset.fleetAjaxBusy = '1';
        setFormBusy(form, submitter, true);

        fetch(action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed. Please try again.');
            }

            return response.text();
        }).then(function (html) {
            refreshCrewPanel(html);
            window.scrollTo(scrollX, scrollY);
            window.setTimeout(function () {
                window.scrollTo(scrollX, scrollY);
            }, 180);
        }).catch(function (error) {
            if (panel) {
                showPanelMessage(panel, 'error', error.message || 'Fleet assignment could not be updated.');
            }

            setFormBusy(form, submitter, false);
        }).finally(function () {
            delete form.dataset.fleetAjaxBusy;
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.matches('[data-fleet-ajax-form]') || !form.closest('[data-fleet-fragment="crew-panel"]')) {
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        submitCrewForm(form, event.submitter || document.activeElement);
    });
})();

(function () {
    var workspaces = Array.prototype.slice.call(document.querySelectorAll('[data-booking-workspace]'));

    if (!workspaces.length) {
        return;
    }

    function readableDate(value) {
        if (!value) {
            return 'Not set';
        }

        var date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function textFromSelect(select, fallback) {
        var option = select && select.selectedOptions ? select.selectedOptions[0] : null;

        if (!option || !option.value) {
            return fallback;
        }

        return option.textContent.trim().replace(/\s+/g, ' ');
    }

    function optionSupportsType(option, type) {
        var tokens = (option.getAttribute('data-booking-types') || '').split(/\s+/).filter(Boolean);

        return tokens.indexOf(type) !== -1;
    }

    function nextReference() {
        var now = new Date();

        return String(Math.floor(now.getTime() / 1000));
    }

    function initBookingWorkspace(workspace) {
        var customerSelect = workspace.querySelector('[data-booking-customer]');
        var routeSelect = workspace.querySelector('[data-booking-route-select]');
        var typeRadios = Array.prototype.slice.call(workspace.querySelectorAll('[data-booking-type]'));
        var previewTargets = {};

        workspace.querySelectorAll('[data-booking-preview]').forEach(function (target) {
            var name = target.getAttribute('data-booking-preview');

            if (!previewTargets[name]) {
                previewTargets[name] = [];
            }

            previewTargets[name].push(target);
        });

        function selectedType() {
            var selected = typeRadios.filter(function (radio) {
                return radio.checked;
            })[0];

            return selected ? selected.value : 'per_trip';
        }

        function selectedTypeLabel() {
            var selected = typeRadios.filter(function (radio) {
                return radio.checked;
            })[0];
            var label = selected ? selected.closest('label') : null;
            var strong = label ? label.querySelector('strong') : null;

            return strong ? strong.textContent.trim() : 'Per Trip';
        }

        function setPreview(name, value) {
            (previewTargets[name] || []).forEach(function (target) {
                target.textContent = value;
            });
        }

        function renderRoutePreview(route) {
            (previewTargets.route || []).forEach(function (target) {
                target.textContent = '';

                if (!route || !route.value) {
                    target.textContent = 'Select a route';
                    return;
                }

                var wrap = document.createElement('div');
                var details = [
                    route.dataset.truckType || '',
                    route.dataset.deliveryType || '',
                ].filter(Boolean).join(' / ');
                var routeLine = document.createElement('div');
                var origin = document.createElement('div');
                var arrow = document.createElement('span');
                var destination = document.createElement('div');
                var meta = document.createElement('small');

                function stop(labelText, valueText) {
                    var label = document.createElement('small');
                    var value = document.createElement('strong');

                    label.textContent = labelText;
                    value.textContent = valueText;

                    return [label, value];
                }

                wrap.className = 'booking-route-preview';
                routeLine.className = 'booking-route-preview-line';
                origin.className = 'booking-route-preview-origin';
                arrow.className = 'booking-route-preview-arrow';
                destination.className = 'booking-route-preview-destination';
                meta.className = 'booking-route-preview-details';

                stop('Origin', route.dataset.origin || 'Origin pending').forEach(function (node) {
                    origin.appendChild(node);
                });
                arrow.textContent = 'to';
                stop('Destination', route.dataset.destination || 'Destination pending').forEach(function (node) {
                    destination.appendChild(node);
                });
                meta.textContent = details || 'Route details pending';

                routeLine.appendChild(origin);
                routeLine.appendChild(arrow);
                routeLine.appendChild(destination);
                wrap.appendChild(routeLine);
                wrap.appendChild(meta);
                target.appendChild(wrap);
            });
        }

        function setReference(value) {
            var field = workspace.querySelector('[data-booking-reference-field]');

            if (field) {
                field.value = value;
            }

            setPreview('reference', value);
        }

        function filterCustomers() {
            if (!customerSelect) {
                return;
            }

            var currentType = selectedType();
            var customerOptions = Array.prototype.slice.call(customerSelect.options);
            var firstMatch = null;

            customerOptions.forEach(function (option) {
                var isPlaceholder = option.value === '';
                var matches = isPlaceholder || optionSupportsType(option, currentType);

                option.hidden = !matches;
                option.disabled = !matches || (!isPlaceholder && !option.getAttribute('data-booking-types'));

                if (!isPlaceholder && matches && !option.disabled && !firstMatch) {
                    firstMatch = option;
                }
            });

            if (customerSelect.selectedOptions[0] && customerSelect.selectedOptions[0].disabled) {
                customerSelect.value = firstMatch ? firstMatch.value : '';
            } else if (!customerSelect.value && firstMatch) {
                customerSelect.value = firstMatch.value;
            }
        }

        function filterRoutes() {
            var currentType = selectedType();
            var customerId = customerSelect ? customerSelect.value : '';
            var routeOptions = routeSelect ? Array.prototype.slice.call(routeSelect.options) : [];
            var firstMatch = null;

            routeOptions.forEach(function (option) {
                var isPlaceholder = option.value === '';
                var matches = isPlaceholder || (
                    option.getAttribute('data-customer-id') === customerId &&
                    option.getAttribute('data-booking-type') === currentType
                );

                option.hidden = !matches;
                option.disabled = !matches;

                if (!isPlaceholder && matches && !firstMatch) {
                    firstMatch = option;
                }
            });

            if (routeSelect && routeSelect.selectedOptions[0] && routeSelect.selectedOptions[0].disabled) {
                routeSelect.value = firstMatch ? firstMatch.value : '';
            } else if (routeSelect && !routeSelect.value && firstMatch) {
                routeSelect.value = firstMatch.value;
            }
        }

        function updatePreview() {
            var route = routeSelect && routeSelect.selectedOptions ? routeSelect.selectedOptions[0] : null;
            var plate = workspace.querySelector('[data-booking-preview-source="plate"]');
            var plateOption = plate && plate.selectedOptions ? plate.selectedOptions[0] : null;
            var plateNote = workspace.querySelector('[data-booking-plate-note]');
            var pickup = workspace.querySelector('[data-booking-preview-source="pickup_date"]');
            var delivery = workspace.querySelector('[data-booking-preview-source="delivery_date"]');
            var shipment = workspace.querySelector('[data-booking-preview-source="shipment_number"]');
            var representative = workspace.querySelector('[data-booking-preview-source="representative"]');

            setPreview('type', selectedTypeLabel());
            setPreview('customer', textFromSelect(customerSelect, 'Select a customer'));
            renderRoutePreview(route);
            setPreview('plate', textFromSelect(plate, 'Select a fleet unit'));
            setPreview('pickup_date', readableDate(pickup ? pickup.value : ''));
            setPreview('delivery_date', readableDate(delivery ? delivery.value : ''));
            setPreview('shipment_number', shipment && shipment.value.trim() ? shipment.value.trim() : 'Not encoded');
            setPreview('representative', representative && representative.value.trim() ? representative.value.trim() : 'Not encoded');

            setPreview('delivery_rate', route && route.dataset.deliveryRate ? route.dataset.deliveryRate : '0.00');
            setPreview('driver_rate', route && route.dataset.driverRate ? route.dataset.driverRate : '0.00');
            setPreview('helper_rate', route && route.dataset.helperRate ? route.dataset.helperRate : '0.00');

            if (plateNote) {
                var note = plateOption && plateOption.dataset.plateNote ? plateOption.dataset.plateNote : '';

                plateNote.textContent = note;
                plateNote.hidden = note === '';
                plateNote.classList.toggle('booking-plate-note-warning', note !== '');
            }
        }

        function syncBookingForm() {
            filterCustomers();
            filterRoutes();
            updatePreview();
        }

        workspace.addEventListener('change', syncBookingForm);
        workspace.addEventListener('input', updatePreview);
        workspace.addEventListener('reset', function () {
            window.setTimeout(syncBookingForm, 0);
        });

        workspace.setBookingType = function (type) {
            typeRadios.forEach(function (radio) {
                radio.checked = radio.value === type;
            });
            syncBookingForm();
        };

        workspace.prepareBookingModal = function (type) {
            workspace.setBookingType(type);
            setReference(nextReference());
        };

        syncBookingForm();
    }

    workspaces.forEach(initBookingWorkspace);

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-booking-modal-type]');

        if (!trigger) {
            return;
        }

        var targetId = trigger.getAttribute('data-modal-open');
        var target = targetId ? document.getElementById(targetId) : null;
        var type = trigger.getAttribute('data-booking-modal-type') || 'per_trip';
        var workspace = target && target.matches('[data-booking-workspace]')
            ? target
            : target ? target.querySelector('[data-booking-workspace]') : null;

        if (workspace && typeof workspace.prepareBookingModal === 'function') {
            workspace.prepareBookingModal(type);
        } else if (workspace && typeof workspace.setBookingType === 'function') {
            workspace.setBookingType(type);
        }
    });
})();

(function () {
    function syncHelperGroup(group) {
        var countTarget = group.querySelector('[data-dispatch-helper-count]');

        if (!countTarget) {
            return;
        }

        countTarget.textContent = group.querySelectorAll('input[type="checkbox"]:checked').length;
    }

    document.querySelectorAll('[data-dispatch-helper-group]').forEach(function (group) {
        syncHelperGroup(group);

        group.addEventListener('change', function () {
            syncHelperGroup(group);
        });
    });
})();
