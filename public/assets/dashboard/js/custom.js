/* ==========================================================================
   Admin dashboard helpers. Requires jQuery, Bootstrap 5, SweetAlert2 and DataTables 2.
   Pages mostly declare behaviour with data-attributes; see the delegated handlers at the end.
   ========================================================================== */

(function () {
    'use strict';

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    // ---------- Escaping & formatting ----------
    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function orDash(value) {
        return value === null || value === undefined || value === '' ? '<span class="text-muted">—</span>' : esc(value);
    }

    function money(value) {
        if (value === null || value === undefined || value === '') return '<span class="text-muted">—</span>';
        const n = Number(value);
        if (Number.isNaN(n)) return esc(value);
        return '<span class="num">' + n.toLocaleString('en-US', { maximumFractionDigits: 2 }) + '</span> <span class="text-muted small">ر.س</span>';
    }

    /** "2026-11-03 14:20" from an ISO string or an already formatted date. */
    function fmtDate(value, withTime) {
        if (!value) return '<span class="text-muted">—</span>';
        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return esc(value);
        const pad = function (n) { return String(n).padStart(2, '0'); };
        let out = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        if (withTime !== false) out += ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        return '<span class="ltr num">' + out + '</span>';
    }

    function initials(name) {
        const parts = String(name || '؟').trim().split(/\s+/);
        return esc((parts[0] || '').charAt(0) + (parts[1] ? parts[1].charAt(0) : ''));
    }

    function avatar(name, imageUrl, size) {
        const cls = 'avatar' + (size ? ' avatar-' + size : '');
        if (imageUrl) {
            return '<span class="' + cls + '"><img src="' + esc(imageUrl) + '" alt="" loading="lazy" onerror="this.parentNode.textContent=\'' + initials(name) + '\'"></span>';
        }
        return '<span class="' + cls + '">' + initials(name) + '</span>';
    }

    function uploadUrl(path) {
        return path ? baseUrl + 'uploads/' + String(path).replace(/^\/+/, '') : null;
    }

    // ---------- Statuses (map comes from App\Support\Dashboard\StatusPresenter) ----------
    function statusInfo(type, value) {
        const map = (window.DASH_STATUS || {})[type] || {};
        return map[value] || [value || '—', 'secondary'];
    }

    function statusPill(type, value, extraClass) {
        const [label, tone] = statusInfo(type, value);
        return '<span class="status-pill tone-' + tone + (extraClass ? ' ' + extraClass : '') + '">' + esc(label) + '</span>';
    }

    /**
     * A status pill that opens a menu of the other statuses.
     * options: { url, id, values: [...], confirm: {value: 'question'} }
     */
    function statusMenu(type, value, options) {
        const [label, tone] = statusInfo(type, value);
        let html = '<div class="dropdown d-inline-block">' +
            '<button type="button" class="status-pill tone-' + tone + '" data-bs-toggle="dropdown" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-expanded="false">' + esc(label) + '</button>' +
            '<ul class="dropdown-menu">' +
            '<li><h6 class="dropdown-header">تغيير الحالة</h6></li>';
        options.values.forEach(function (v) {
            const [l, t] = statusInfo(type, v);
            const confirmText = options.confirm && options.confirm[v] ? ' data-confirm="' + esc(options.confirm[v]) + '"' : '';
            html += '<li><a class="dropdown-item' + (v === value ? ' active' : '') + '" href="#" data-status-url="' + esc(options.url) +
                '" data-id="' + esc(options.id) + '" data-status="' + esc(v) + '"' + confirmText + '>' +
                '<span class="status-pill tone-' + t + ' no-dot" style="padding:.1rem .5rem">●</span>' + esc(l) + '</a></li>';
        });
        return html + '</ul></div>';
    }

    // ---------- Feedback ----------
    function toast(title, icon) {
        if (!title) return;
        Swal.mixin({
            toast: true,
            position: 'top-start',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: function (t) { t.onmouseenter = Swal.stopTimer; t.onmouseleave = Swal.resumeTimer; }
        }).fire({ icon: icon || 'success', title: title });
    }

    function showErrors(errors, title) {
        let list = [];
        if (Array.isArray(errors)) list = errors;
        else if (errors && typeof errors === 'object') {
            Object.values(errors).forEach(function (v) { list = list.concat(Array.isArray(v) ? v : [v]); });
        } else if (errors) list = [errors];

        Swal.fire({
            icon: 'error',
            title: title || 'تعذّر إتمام العملية',
            html: list.length ? '<ul class="text-start mb-0" style="padding-inline-start:1.2rem">' + list.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>' : '',
            confirmButtonText: 'حسناً'
        });
    }

    function confirmAction(opts) {
        opts = opts || {};
        return Swal.fire({
            title: opts.title || 'هل أنت متأكد؟',
            text: opts.text || '',
            icon: opts.danger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'تأكيد',
            cancelButtonText: 'إلغاء',
            reverseButtons: false,
            focusCancel: !!opts.danger,
            customClass: { confirmButton: opts.danger ? 'is-danger' : '' },
            input: opts.input || undefined,
            inputPlaceholder: opts.inputPlaceholder || undefined,
            inputValidator: opts.inputRequired ? function (v) { return !v ? 'هذا الحقل مطلوب' : undefined; } : undefined
        }).then(function (r) { return r.isConfirmed ? (opts.input ? r.value : true) : false; });
    }

    let busyEl = null;
    function busy(on, text) {
        if (!busyEl) {
            busyEl = document.createElement('div');
            busyEl.className = 'busy-overlay';
            busyEl.innerHTML = '<div class="busy-box"><span class="spinner-border spinner-border-sm text-primary"></span><span class="busy-text"></span></div>';
            document.body.appendChild(busyEl);
        }
        busyEl.querySelector('.busy-text').textContent = text || 'جارٍ المعالجة...';
        busyEl.classList.toggle('show', !!on);
    }

    // ---------- Ajax ----------
    function request(method, url, data) {
        return new Promise(function (resolve, reject) {
            const isForm = data instanceof FormData;
            $.ajax({
                url: url,
                type: method,
                data: data,
                dataType: 'json',
                processData: !isForm,
                contentType: isForm ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            }).done(function (res) {
                if (res && res.success === false) {
                    toast(res.message || 'تعذّر إتمام العملية', 'error');
                    reject(res);
                    return;
                }
                if (res && res.message) toast(res.message);
                resolve(res);
            }).fail(function (xhr) {
                const body = xhr.responseJSON || {};
                if (xhr.status === 422) showErrors(body.errors || body);
                else if (xhr.status === 419) showErrors(['انتهت صلاحية الجلسة، حدّث الصفحة وحاول مجدداً']);
                else toast(body.message || 'حدث خطأ غير متوقع (' + xhr.status + ')', 'error');
                reject(xhr);
            });
        });
    }

    // ---------- DataTables ----------
    const dtLanguage = {
        processing: 'جارٍ التحميل...',
        search: '',
        searchPlaceholder: 'بحث...',
        lengthMenu: '_MENU_ لكل صفحة',
        info: 'عرض _START_–_END_ من أصل _TOTAL_',
        infoEmpty: 'لا توجد سجلات',
        infoFiltered: '(من أصل _MAX_)',
        zeroRecords: 'لا توجد نتائج مطابقة',
        emptyTable: 'لا توجد بيانات بعد',
        loadingRecords: 'جارٍ التحميل...',
        paginate: { first: '«', last: '»', next: '‹', previous: '›' },
        aria: { sortAscending: ': ترتيب تصاعدي', sortDescending: ': ترتيب تنازلي' }
    };

    /**
     * Server-side table with the dashboard defaults.
     * opts: { el, url, columns, order, filters: () => ({...}), pageLength, searchPlaceholder, rowUrl: row => url }
     */
    function dashTable(opts) {
        const el = opts.el || '#datatableID';
        const language = Object.assign({}, dtLanguage, opts.searchPlaceholder ? { searchPlaceholder: opts.searchPlaceholder } : {});
        if (opts.emptyTable) language.emptyTable = opts.emptyTable;

        const table = new DataTable(el, {
            processing: true,
            serverSide: true,
            autoWidth: false,
            pageLength: opts.pageLength || 15,
            lengthMenu: [10, 15, 25, 50, 100],
            order: opts.order || [[0, 'desc']],
            searchDelay: 350,
            language: language,
            layout: {
                topStart: 'search',
                topEnd: opts.topEnd || 'pageLength',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            ajax: {
                url: opts.url,
                type: 'GET',
                data: function (d) { if (opts.filters) Object.assign(d, opts.filters()); }
            },
            columns: opts.columns,
            createdRow: function (row, data) {
                if (opts.rowUrl) {
                    const url = opts.rowUrl(data);
                    if (url) { row.dataset.href = url; row.classList.add('row-link'); }
                }
            },
            drawCallback: function () { initTooltips(this.api().table().container()); }
        });

        return table;
    }

    function rowActions(actions) {
        let html = '<div class="row-actions">';
        if (actions.view) {
            html += '<a href="' + esc(actions.view) + '" class="btn btn-icon" data-bs-toggle="tooltip" title="عرض التفاصيل"><i class="bi bi-eye"></i></a>';
        }
        if (actions.edit) {
            html += '<a href="' + esc(actions.edit) + '" class="btn btn-icon" data-bs-toggle="tooltip" title="تعديل"><i class="bi bi-pencil"></i></a>';
        }
        if (actions.del) {
            html += '<button type="button" class="btn btn-icon danger" data-bs-toggle="tooltip" title="حذف" data-delete-url="' + esc(actions.del) +
                '" data-confirm="' + esc(actions.delConfirm || 'سيتم حذف هذا السجل نهائياً.') + '" data-after="table"><i class="bi bi-trash3"></i></button>';
        }
        return html + '</div>';
    }

    function reloadTables() {
        if (window.DataTable) {
            DataTable.tables({ api: true }).ajax.reload(null, false);
        }
    }

    function initTooltips(scope) {
        (scope || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body', trigger: 'hover' });
        });
    }

    function after(mode, res) {
        if (mode === 'table') reloadTables();
        else if (mode === 'remove') { /* handled by caller */ }
        else if (mode && mode !== 'reload') window.location.href = mode;
        else setTimeout(function () { window.location.reload(); }, 450);
    }

    // ---------- Image picker ----------
    function previewSelectedImage(event, elementId) {
        const input = event.target;
        const img = document.getElementById(elementId);
        if (input.files && input.files[0] && img) {
            const reader = new FileReader();
            reader.onload = function (e) { img.src = e.target.result; };
            reader.readAsDataURL(input.files[0]);
            const name = input.closest('.image-picker')?.querySelector('.picker-file');
            if (name) name.textContent = input.files[0].name;
        }
    }

    // ---------- Theme ----------
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.querySelectorAll('[data-theme-icon]').forEach(function (i) {
            i.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        });
    }

    // ---------- Delegated behaviour ----------
    $(document).on('click', 'tr.row-link', function (e) {
        if (e.target.closest('a, button, input, label, .dropdown, .dropdown-menu')) return;
        const href = this.dataset.href;
        if (!href) return;
        if (e.ctrlKey || e.metaKey) window.open(href, '_blank');
        else window.location.href = href;
    });

    // <button data-delete-url="..." data-confirm="..." data-after="table|reload|<url>">
    $(document).on('click', '[data-delete-url]', function (e) {
        e.preventDefault();
        const btn = this;
        bootstrap.Tooltip.getInstance(btn)?.hide();
        confirmAction({
            title: btn.dataset.confirmTitle || 'تأكيد الحذف',
            text: btn.dataset.confirm || 'لا يمكن التراجع عن هذه العملية.',
            confirmText: 'حذف',
            danger: true
        }).then(function (ok) {
            if (!ok) return;
            busy(true);
            request('DELETE', btn.dataset.deleteUrl)
                .then(function (res) { after(btn.dataset.after || 'reload', res); })
                .catch(function () { })
                .finally(function () { busy(false); });
        });
    });

    // <a data-status-url="..." data-id="1" data-status="Active" data-confirm="...">
    $(document).on('click', '[data-status-url]', function (e) {
        e.preventDefault();
        const el = this;
        if (el.classList.contains('active')) return;
        const go = function () {
            const data = new FormData();
            data.append('id', el.dataset.id);
            data.append('status', el.dataset.status);
            busy(true);
            request('POST', el.dataset.statusUrl, data)
                .then(function () { after(el.dataset.after || (el.closest('table.dataTable') ? 'table' : 'reload')); })
                .catch(function () { })
                .finally(function () { busy(false); });
        };
        if (el.dataset.confirm) {
            confirmAction({ text: el.dataset.confirm, danger: true }).then(function (ok) { if (ok) go(); });
        } else go();
    });

    // <form data-confirm="question" [data-confirm-danger]> — asks before submitting.
    // Every regular form also locks its submit button to stop double submissions.
    $(document).on('submit', 'form:not([data-no-lock])', function (e) {
        const form = this;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            e.preventDefault();
            const submitter = e.originalEvent && e.originalEvent.submitter;
            confirmAction({
                text: (submitter && submitter.dataset.confirm) || form.dataset.confirm,
                danger: form.hasAttribute('data-confirm-danger'),
                confirmText: form.dataset.confirmText || 'تأكيد'
            }).then(function (ok) {
                if (!ok) return;
                form.dataset.confirmed = '1';
                if (submitter && submitter.name) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = submitter.name; hidden.value = submitter.value;
                    form.appendChild(hidden);
                }
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
            return;
        }
        const btn = (e.originalEvent && e.originalEvent.submitter) || form.querySelector('[type="submit"]');
        if (btn && !btn.disabled) {
            btn.dataset.originalHtml = btn.innerHTML;
            setTimeout(function () {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + (btn.dataset.loadingText || 'جارٍ الحفظ...');
            }, 0);
        }
    });

    // Restore buttons when the page comes back from the bfcache (browser back button).
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('[data-original-html]').forEach(function (b) {
            b.disabled = false; b.innerHTML = b.dataset.originalHtml;
        });
    });

    // Drag & drop on image pickers.
    $(document).on('dragover', '.image-picker', function (e) { e.preventDefault(); this.classList.add('is-dragover'); });
    $(document).on('dragleave drop', '.image-picker', function () { this.classList.remove('is-dragover'); });
    $(document).on('drop', '.image-picker', function (e) {
        e.preventDefault();
        const input = this.querySelector('input[type=file]');
        const files = e.originalEvent.dataTransfer.files;
        if (input && files.length) {
            input.files = files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    $(document).on('click', '[data-theme-toggle]', function (e) {
        e.preventDefault();
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('dash-theme', next); } catch (err) { }
        applyTheme(next);
        document.dispatchEvent(new CustomEvent('dash:theme', { detail: next }));
    });

    $(function () {
        applyTheme(document.documentElement.getAttribute('data-bs-theme') || 'light');
        initTooltips();
    });

    // ---------- Public API ----------
    Object.assign(window, {
        esc: esc,
        orDash: orDash,
        money: money,
        fmtDate: fmtDate,
        avatar: avatar,
        uploadUrl: uploadUrl,
        statusInfo: statusInfo,
        statusPill: statusPill,
        statusMenu: statusMenu,
        toast: toast,
        swalToast: function (o) { toast(o && o.title, o && o.icon); },
        showErrors: showErrors,
        confirmAction: confirmAction,
        busy: busy,
        dashRequest: request,
        dashTable: dashTable,
        rowActions: rowActions,
        reloadTables: reloadTables,
        initTooltips: initTooltips,
        previewSelectedImage: previewSelectedImage
    });
})();
