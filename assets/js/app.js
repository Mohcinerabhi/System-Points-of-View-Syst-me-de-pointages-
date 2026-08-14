/**
 * AttendPro - Dynamic Application JavaScript
 * Real-time updates, AJAX CRUD, live notifications, dynamic charts
 */
(function ($) {
    'use strict';

    const App = {
        baseUrl: '',
        csrfToken: '',
        refreshInterval: null,

        init() {
            this.csrfToken = this.getCsrfToken();
            this.baseUrl = this.getBaseUrl();

            this.initSidebar();
            this.initActiveMenu();
            this.initAjaxForms();
            this.initDynamicTables();
            this.initLiveSearch();
            this.initDashboardAutoRefresh();
            this.initConfirmDelete();
            this.initFormValidation();
            this.initDatePickers();
            this.initTimePickers();
            this.initFileUpload();
            this.initCalendarNav();
            this.initPrint();
            this.initExportCsv();
            this.initRealTimeNotifications();
            this.initNotificationButton();
            this.initNotificationChecks();
            this.highlightInvalid();
        },

        getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) return meta.content;
            const input = document.querySelector('input[name="csrf_token"]');
            if (input) return input.value;
            return '';
        },

        getBaseUrl() {
            const meta = document.querySelector('meta[name="base-url"]');
            return meta ? meta.content : '';
        },

        /* ---------------- Sidebar ---------------- */
        initSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('sidebarToggle');
            if (!sidebar || !toggle) return;

            const isMobile = () => window.innerWidth < 1024;

            const openSidebar = () => {
                if (isMobile()) {
                    sidebar.classList.remove('-translate-x-full');
                    if (overlay) overlay.classList.remove('hidden');
                } else {
                    sidebar.classList.remove('w-20');
                    sidebar.classList.add('w-64');
                }
            };

            const closeSidebar = () => {
                if (isMobile()) {
                    sidebar.classList.add('-translate-x-full');
                    if (overlay) overlay.classList.add('hidden');
                } else {
                    sidebar.classList.remove('w-64');
                    sidebar.classList.add('w-20');
                }
            };

            toggle.addEventListener('click', () => {
                if (isMobile()) {
                    if (sidebar.classList.contains('-translate-x-full')) {
                        openSidebar();
                    } else {
                        closeSidebar();
                    }
                } else {
                    if (sidebar.classList.contains('w-64')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                }
            });

            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }

            $(window).on('resize', () => {
                if (!isMobile()) {
                    sidebar.classList.remove('-translate-x-full');
                    if (overlay) overlay.classList.add('hidden');
                }
            });
        },

        /* ---------------- Active menu ---------------- */
        initActiveMenu() {
            const path = (window.location.pathname + window.location.search).replace(this.baseUrl, '');
            $('.sidebar .nav-link').each(function () {
                const href = ($(this).attr('href') || '').replace(this.baseUrl, '');
                if (href && href !== '/' && path.indexOf(href) === 0) {
                    $(this).addClass('bg-gray-800', 'text-white', 'border-r-4', 'border-blue-500');
                    $(this).removeClass('text-gray-300', 'hover:bg-gray-800', 'hover:text-white');
                }
            });
            const current = $('.sidebar .nav-link').filter(function () {
                return $(this).hasClass('bg-gray-800');
            }).first();
            if (current.length) {
                const title = current.data('title') || current.text().trim();
                if ($('.topbar-title').length) {
                    $('.topbar-title').text(title);
                }
            }
        },

        /* ---------------- CSRF helpers ---------------- */
        csrfData() {
            return this.csrfToken ? { csrf_token: this.csrfToken } : {};
        },

        refreshToken(newToken) {
            if (newToken) {
                this.csrfToken = newToken;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = newToken;
            }
        },

        /* ---------------- AJAX forms ---------------- */
        initAjaxForms() {
            $(document).on('submit', 'form[data-ajax]', function (e) {
                e.preventDefault();
                const $form = $(this);
                if (App.validateForm($form[0])) return;

                const url = $form.attr('action') || window.location.href;
                const method = ($form.attr('method') || 'POST').toUpperCase();
                const $btn = $form.find('[type="submit"]');
                const original = $btn.html();

                const formData = new FormData($form[0]);
                formData.append('csrf_token', App.csrfToken);

                $btn.prop('disabled', true).html('<div class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>');
                App.clearErrors($form);

                $.ajax({
                    url: url,
                    method: method,
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done((res) => {
                    if (res && res.csrf_token) App.refreshToken(res.csrf_token);
                    if (res && res.success) {
                        App.toast(res.message || 'Opération réussie', 'success');
                        if (res.redirect) {
                            setTimeout(() => window.location.href = res.redirect, 600);
                        } else if (res.reload) {
                            setTimeout(() => window.location.reload(), 600);
                        } else if (res.html && $form.data('target')) {
                            $($form.data('target')).html(res.html);
                        }
                    } else {
                        App.toast(res && res.message ? res.message : 'Erreur lors de l\'enregistrement', 'error');
                        if (res && res.errors) App.showErrors($form, res.errors);
                    }
                }).fail(() => {
                    App.toast('Erreur de communication avec le serveur', 'error');
                }).always(() => {
                    $btn.prop('disabled', false).html(original);
                });
            });
        },

        showErrors($form, errors) {
            $.each(errors, (field, message) => {
                const input = $form.find('[name="' + field + '"]');
                if (input.length) {
                    input.addClass('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                    input.removeClass('border-gray-300', 'focus:border-blue-500', 'focus:ring-blue-500');
                    const err = input.closest('.form-group').find('.form-error');
                    if (err.length) err.text(Array.isArray(message) ? message[0] : message).removeClass('hidden');
                }
            });
        },

        clearErrors($form) {
            $form.find('.border-red-500').removeClass('border-red-500', 'focus:border-red-500', 'focus:ring-red-500').addClass('border-gray-300', 'focus:border-blue-500', 'focus:ring-blue-500');
            $form.find('.form-error').addClass('hidden');
        },

        /* ---------------- Dynamic tables (AJAX) ---------------- */
        initDynamicTables() {
            $(document).on('change', '.data-table-filter', function () {
                App.reloadDataTable();
            });

            $(document).on('input', '.data-table-search', function () {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => App.reloadDataTable(), 300);
            });

            $(document).on('click', '.data-pagination a', function (e) {
                e.preventDefault();
                const href = $(this).attr('href');
                if (href && href.includes('page=')) {
                    const url = new URL(href, window.location.href);
                    const page = url.searchParams.get('page');
                    App.loadDataTablePage(page);
                }
            });
        },

        reloadDataTable() {
            const $table = $('.data-table');
            if (!$table.length) return;

            const url = $table.data('url') || window.location.href;
            const params = new URLSearchParams();

            $('.data-table-filter').each(function () {
                const name = $(this).attr('name');
                const value = $(this).val();
                if (name && value !== '') {
                    params.set(name, value);
                }
            });

            const searchInput = $('.data-table-search');
            if (searchInput.length) {
                params.set('search', searchInput.val());
            }

            const page = $('.data-pagination .bg-blue-600').data('page') || 1;
            params.set('page', page);

            App.showTableLoading($table);

            $.get(url + '&' + params.toString(), function (html) {
                const $newTable = $(html).find('.data-table');
                if ($newTable.length) {
                    $table.replaceWith($newTable);
                } else {
                    App.hideTableLoading($table);
                }
            }).fail(() => {
                App.hideTableLoading($table);
                App.toast('Erreur lors du chargement des données', 'error');
            });
        },

        loadDataTablePage(page) {
            const $table = $('.data-table');
            if (!$table.length) return;

            const url = $table.data('url') || window.location.href;
            const params = new URLSearchParams();
            params.set('page', page);

            $('.data-table-filter').each(function () {
                const name = $(this).attr('name');
                const value = $(this).val();
                if (name && value !== '') {
                    params.set(name, value);
                }
            });

            const searchInput = $('.data-table-search');
            if (searchInput.length) {
                params.set('search', searchInput.val());
            }

            App.showTableLoading($table);

            $.get(url + '&' + params.toString(), function (html) {
                const $newTable = $(html).find('.data-table');
                if ($newTable.length) {
                    $table.replaceWith($newTable);
                    $('html, body').animate({ scrollTop: $table.offset().top - 100 }, 300);
                } else {
                    App.hideTableLoading($table);
                }
            }).fail(() => {
                App.hideTableLoading($table);
                App.toast('Erreur lors du chargement', 'error');
            });
        },

        showTableLoading($table) {
            const $tbody = $table.find('tbody');
            $tbody.html('<tr><td colspan="99" class="text-center py-4"><div class="inline-block w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div></td></tr>');
        },

        hideTableLoading($table) {
            // Handled by replacement or can be used for fallback
        },

        /* ---------------- Live search ---------------- */
        initLiveSearch() {
            $(document).on('input', '#empSearch', function () {
                const query = this.value.trim();
                if (query.length < 2) {
                    App.reloadDataTable();
                    return;
                }

                const $table = $('.data-table');
                if (!$table.length) return;

                $.get($table.data('url') || 'index.php?controller=employees&action=ajaxList', { q: query }, function (res) {
                    if (res && res.success && res.data) {
                        App.renderEmployeeTable(res.data);
                    }
                });
            });
        },

        renderEmployeeTable(employees) {
            const $tbody = $('#empTable tbody');
            if (!$tbody.length) return;

            if (!employees.length) {
                $tbody.html('<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Aucun employé trouvé</td></tr>');
                return;
            }

            let html = '';
            employees.forEach(emp => {
                html += `<tr>
                    <td class="px-6 py-4">${emp.photo}</td>
                    <td class="px-6 py-4"><strong>${emp.code}</strong></td>
                    <td class="px-6 py-4">${emp.name}</td>
                    <td class="px-6 py-4">${emp.department}</td>
                    <td class="px-6 py-4">${emp.badge}</td>
                    <td class="px-6 py-4">${emp.registration}</td>
                    <td class="px-6 py-4">${emp.status}</td>
                    <td class="px-6 py-4">${emp.actions}</td>
                </tr>`;
            });

            $tbody.html(html);
        },

        /* ---------------- Dashboard auto-refresh ---------------- */
        initDashboardAutoRefresh() {
            if (!$('.stat-total, .stat-present, .stat-absent, .stat-late, .stat-early, .stat-current').length) return;

            this.refreshInterval = setInterval(() => {
                this.refreshDashboardStats();
            }, 30000);
        },

        refreshDashboardStats() {
            const url = 'index.php?controller=dashboard&action=ajaxStats';
            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                cache: false
            }).done((res) => {
                if (res && res.success && res.data) {
                    App.updateStatCards(res.data.stats || res.data);
                    if (res.data.chart) {
                        App.updateCharts(res.data.chart);
                    }
                }
            }).fail((xhr, status, error) => {
                console.warn('Dashboard refresh failed:', status, error);
            });
        },

        updateStatCards(stats) {
            const cards = {
                'total_employees': '.stat-total',
                'present_today': '.stat-present',
                'absent': '.stat-absent',
                'late': '.stat-late',
                'early_departures': '.stat-early',
                'currently_present': '.stat-current'
            };

            $.each(cards, (key, selector) => {
                const $el = $(selector);
                if ($el.length && stats[key] !== undefined) {
                    const oldVal = parseInt($el.text(), 10) || 0;
                    const newVal = parseInt(stats[key], 10) || 0;
                    if (oldVal !== newVal) {
                        $el.text(stats[key]);
                        $el.addClass('text-green-600');
                        setTimeout(() => $el.removeClass('text-green-600'), 1000);
                    }
                }
            });
        },

        updateCharts(chartData) {
            if (window.AppCharts && typeof AppCharts.update === 'function') {
                AppCharts.update(chartData);
            }
        },

        /* ---------------- Real-time notifications ---------------- */
        initRealTimeNotifications() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            this.refreshInterval = setInterval(() => {
                this.checkNotifications();
            }, 60000);
        },

        initNotificationButton() {
            const $btn = $('#notificationBtn');
            if (!$btn.length) return;

            $btn.on('click', () => {
                const icon = $btn.find('i');
                icon.addClass('fa-spin');
                this.checkNotifications();
                setTimeout(() => icon.removeClass('fa-spin'), 800);
            });
        },

        initNotificationChecks() {
            setInterval(() => {
                $.post('index.php?controller=api&action=runChecks', { csrf_token: App.csrfToken })
                    .done(() => {
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            $.get('index.php?controller=api&action=notifications')
                                .done((res) => {
                                    if (res && res.success && res.data && res.data.length) {
                                        badge.classList.remove('hidden');
                                    } else {
                                        badge.classList.add('hidden');
                                    }
                                });
                        }
                    })
                    .fail(() => {});
            }, 300000);
        },

        checkNotifications() {
            $.get('index.php?controller=api&action=notifications')
                .done((res) => {
                    if (res && res.success && res.data && res.data.length) {
                        res.data.forEach(notif => {
                            this.showNotification(notif.title, notif.body, notif.type);
                        });
                        App.toast(res.data.length + ' nouvelle(s) notification(s)', 'info', 'Notifications');
                    } else {
                        App.toast('Aucune notification', 'info', 'Notifications');
                    }
                })
                .fail(() => App.toast('Erreur lors de la récupération des notifications', 'error'));
        },

        showNotification(title, body, type) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, { body, icon: 'assets/images/icon.png' });
            }
            this.toast(body, type || 'info', title);
        },

        /* ---------------- Confirm delete ---------------- */
        initConfirmDelete() {
            $(document).on('click', '[data-confirm]', function (e) {
                const message = $(this).data('confirm') || 'Êtes-vous sûr ?';
                if (!window.confirm(message)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
                const href = $(this).data('href') || $(this).attr('href');
                if (href && $(this).is('[data-ajax-delete]')) {
                    e.preventDefault();
                    App.ajaxDelete(href, $(this).closest('tr, .card, li'));
                }
            });
        },

        ajaxDelete(url, $row) {
            $.ajax({
                url: url,
                method: 'POST',
                data: App.csrfData(),
                dataType: 'json'
            }).done((res) => {
                if (res && res.csrf_token) App.refreshToken(res.csrf_token);
                if (res && res.success) {
                    App.toast(res.message || 'Supprimé', 'success');
                    if ($row && $row.length) $row.fadeOut(250, function () { $(this).remove(); });
                } else {
                    App.toast(res && res.message ? res.message : 'Échec de la suppression', 'error');
                }
            }).fail(() => App.toast('Erreur de communication', 'error'));
        },

        /* ---------------- Form validation ---------------- */
        initFormValidation() {
            $(document).on('blur', 'input[required], select[required], textarea[required]', function () {
                App.validateField(this);
            });
            $(document).on('input', 'input, select, textarea', function () {
                if ($(this).hasClass('border-red-500')) App.validateField(this);
            });
        },

        validateField(field) {
            const $f = $(field);
            let valid = true;
            let msg = '';
            if (field.hasAttribute('required') && !field.value.trim()) {
                valid = false; msg = 'Ce champ est obligatoire';
            } else if (field.type === 'email' && field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                valid = false; msg = 'Adresse e-mail invalide';
            } else if (field.type === 'number' && field.value && isNaN(field.value)) {
                valid = false; msg = 'Veuillez saisir un nombre';
            } else if (field.minLength > 0 && field.value && field.value.length < field.minLength) {
                valid = false; msg = 'Minimum ' + field.minLength + ' caractères';
            }
            const err = $f.closest('.form-group').find('.form-error');
            if (valid) {
                $f.removeClass('border-red-500', 'focus:border-red-500', 'focus:ring-red-500').addClass('border-gray-300', 'focus:border-blue-500', 'focus:ring-blue-500');
                if (err.length) err.addClass('hidden');
            } else {
                $f.removeClass('border-gray-300', 'focus:border-blue-500', 'focus:ring-blue-500').addClass('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                if (err.length) { err.text(msg).removeClass('hidden'); }
            }
            return valid;
        },

        validateForm(form) {
            let ok = true;
            const fields = form.querySelectorAll('input[required], select[required], textarea[required]');
            fields.forEach((f) => { if (!App.validateField(f)) ok = false; });
            if (!ok) App.toast('Veuillez corriger les champs en erreur', 'warning');
            return ok;
        },

        highlightInvalid() {
            $('.border-red-500').first().focus();
        },

        /* ---------------- Date pickers ---------------- */
        initDatePickers() {
            if (window.flatpickr) {
                flatpickr('[data-datepicker]', { dateFormat: 'Y-m-d', allowInput: true });
                flatpickr('[data-daterange]', { mode: 'range', dateFormat: 'Y-m-d' });
            } else {
                $('[data-datepicker]').attr('type', 'date');
            }
        },

        /* ---------------- Time pickers ---------------- */
        initTimePickers() {
            if (window.flatpickr) {
                flatpickr('[data-timepicker]', { enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true });
            } else {
                $('[data-timepicker]').attr('type', 'time');
            }
        },

        /* ---------------- File upload preview ---------------- */
        initFileUpload() {
            $(document).on('change', '.file-upload input[type="file"]', function () {
                const file = this.files[0];
                const $wrap = $(this).closest('.file-upload').parent();
                $wrap.find('.upload-preview').remove();
                if (!file) return;
                const preview = $('<div class="upload-preview"></div>');
                const name = $('<span class="file-name"></span>').text(file.name);
                preview.append(name);
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => preview.prepend($('<img>').attr('src', e.target.result));
                    reader.readAsDataURL(file);
                } else {
                    preview.prepend($('<span class="upload-icon"></span>').text('📄'));
                }
                $wrap.append(preview);
            });

            $(document).on('dragover dragenter', '.file-upload', function (e) {
                e.preventDefault(); $(this).addClass('border-blue-500', 'bg-blue-50');
            });
            $(document).on('dragleave drop', '.file-upload', function (e) {
                e.preventDefault(); $(this).removeClass('border-blue-500', 'bg-blue-50');
            });
        },

        /* ---------------- Toast notifications ---------------- */
        toast(message, type, title) {
            type = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
            const icons = { success: '✔', error: '✕', warning: '!', info: 'ℹ' };
            const titles = { success: 'Succès', error: 'Erreur', warning: 'Attention', info: 'Information' };
            let container = $('.toast-container');
            if (!container.length) { container = $('<div class="toast-container"></div>').appendTo('body'); }
            const $toast = $(`
                <div class="toast toast-${type}">
                    <span class="toast-icon">${icons[type]}</span>
                    <div class="toast-body">
                        <span class="toast-title">${title || titles[type]}</span>
                        <span>${message}</span>
                    </div>
                    <button class="toast-close" aria-label="Fermer">&times;</button>
                </div>`);
            container.append($toast);
            requestAnimationFrame(() => $toast.addClass('show'));
            const remove = () => { $toast.removeClass('show'); setTimeout(() => $toast.remove(), 350); };
            const timer = setTimeout(remove, 4000);
            $toast.find('.toast-close').on('click', () => { clearTimeout(timer); remove(); });
        },

        /* ---------------- Modal helpers ---------------- */
        openModal(options) {
            const opts = Object.assign({ title: '', body: '', size: '', footer: '' }, options);
            let overlay = $('.modal-overlay');
            if (!overlay.length) overlay = $('<div class="modal-overlay"></div>').appendTo('body');
            overlay.html(`
                <div class="modal${opts.size ? ' modal-' + opts.size : ''}">
                    <div class="modal-header">
                        <h3>${opts.title}</h3>
                        <button class="modal-close" aria-label="Fermer">&times;</button>
                    </div>
                    <div class="modal-body">${opts.body}</div>
                    ${opts.footer ? '<div class="modal-footer">' + opts.footer + '</div>' : ''}
                </div>`);
            overlay.addClass('show');
            overlay.find('.modal-close').on('click', App.closeModal);
            overlay.off('click.modal').on('click.modal', function (e) { if (e.target === this) App.closeModal(); });
            return overlay;
        },

        closeModal() {
            $('.modal-overlay').removeClass('show');
            setTimeout(() => $('.modal-overlay').remove(), 250);
        },

        /* ---------------- Calendar navigation ---------------- */
        initCalendarNav() {
            $(document).on('click', '[data-cal-prev], [data-cal-next], [data-cal-today]', function (e) {
                e.preventDefault();
                const url = $(this).data('url') || $(this).attr('href');
                if (url) window.location.href = url;
            });
            $(document).on('click', '.cal-cell[data-date]', function () {
                const $cell = $(this);
                const date = $cell.data('date');
                const details = $cell.data('details') || '';
                const title = date ? 'Détails du ' + date : 'Détails';
                App.openModal({ title: title, body: '<div class="day-details">' + details + '</div>' });
            });
        },

        /* ---------------- Print ---------------- */
        initPrint() {
            $(document).on('click', '[data-print]', function (e) {
                e.preventDefault();
                const target = $(this).data('print');
                if (target) {
                    const content = $(target).html();
                    const w = window.open('', '_blank');
                    w.document.write('<html><head><title>Impression</title>');
                    w.document.write('<link rel="stylesheet" href="https://cdn.tailwindcss.com">');
                    w.document.write('</head><body>' + content + '</body></html>');
                    w.document.close();
                    w.focus();
                    setTimeout(() => w.print(), 300);
                } else {
                    window.print();
                }
            });
        },

        /* ---------------- Export CSV ---------------- */
        initExportCsv() {
            $(document).on('click', '[data-export-csv]', function (e) {
                e.preventDefault();
                const tableId = $(this).data('export-csv');
                App.exportTableToCsv(tableId, $(this).data('filename') || 'export.csv');
            });
        },

        exportTableToCsv(tableId, filename) {
            const rows = document.querySelectorAll('#' + tableId + ' tr');
            const csv = [];
            rows.forEach((row) => {
                const cols = row.querySelectorAll('th, td');
                const line = [];
                cols.forEach((col) => {
                    let text = (col.innerText || '').replace(/\s+/g, ' ').trim();
                    text = text.replace(/"/g, '""');
                    line.push('"' + text + '"');
                });
                csv.push(line.join(','));
            });
            const csvContent = '\uFEFF' + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
            App.toast('Export CSV généré', 'success');
        },

        /* ---------------- Chart.js helpers ---------------- */
        chartDefaults() {
            if (window.Chart) {
                Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
                Chart.defaults.color = '#6B7280';
                Chart.defaults.plugins.legend.labels.usePointStyle = true;
            }
        },

        initCharts() {
            this.chartDefaults();
            if (!window.Chart) return;

            const palette = ['#2563EB', '#16A34A', '#F59E0B', '#DC2626', '#0EA5E9', '#8B5CF6'];

            const daily = document.getElementById('chart-daily');
            if (daily && daily.dataset.labels) {
                new Chart(daily, {
                    type: 'line',
                    data: {
                        labels: JSON.parse(daily.dataset.labels),
                        datasets: [{
                            label: 'Présences',
                            data: JSON.parse(daily.dataset.values || '[]'),
                            borderColor: palette[0],
                            backgroundColor: 'rgba(37,99,235,0.12)',
                            fill: true, tension: 0.35, pointRadius: 3
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true } } }
                });
            }

            const delays = document.getElementById('chart-delays');
            if (delays && delays.dataset.labels) {
                new Chart(delays, {
                    type: 'bar',
                    data: {
                        labels: JSON.parse(delays.dataset.labels),
                        datasets: [{
                            label: 'Retards (min)',
                            data: JSON.parse(delays.dataset.values || '[]'),
                            backgroundColor: palette[2], borderRadius: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

            const monthly = document.getElementById('chart-monthly');
            if (monthly && monthly.dataset.labels) {
                new Chart(monthly, {
                    type: 'line',
                    data: {
                        labels: JSON.parse(monthly.dataset.labels),
                        datasets: [
                            { label: 'Présences', data: JSON.parse(monthly.dataset.present || '[]'), borderColor: palette[1], backgroundColor: 'rgba(22,163,74,0.1)', fill: true, tension: 0.35 },
                            { label: 'Absences', data: JSON.parse(monthly.dataset.absent || '[]'), borderColor: palette[3], backgroundColor: 'rgba(220,38,38,0.1)', fill: true, tension: 0.35 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

            const dept = document.getElementById('chart-department');
            if (dept && dept.dataset.labels) {
                new Chart(dept, {
                    type: 'doughnut',
                    data: {
                        labels: JSON.parse(dept.dataset.labels),
                        datasets: [{
                            data: JSON.parse(dept.dataset.values || '[]'),
                            backgroundColor: palette,
                            borderWidth: 2, borderColor: '#fff'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '65%' }
                });
            }
        }
    };

    window.confirmDelete = function (msg) {
        return window.confirm(msg || 'Êtes-vous sûr ?');
    };

    window.App = App;

    $(function () {
        App.init();
        App.initCharts();
    });

})(jQuery);
