/* CampMart AI search autocomplete.
 * Attaches to any <form data-search-scope="products|services"> containing
 * an <input name="search">. Shows a suggestion dropdown fed by api/search-suggest.php.
 */
(function () {
    'use strict';

    var BASE = '';
    var baseEl = document.querySelector('base');
    if (baseEl) {
        BASE = baseEl.getAttribute('href') || '';
    }

    var ICONS = {
        history: 'history',
        popular: 'trending_up',
        product: 'shopping_bag',
        service: 'design_services',
        category: 'category',
        semantic: 'auto_awesome',
        search: 'search'
    };

    function debounce(fn, wait) {
        var timer;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function toUrl(url) {
        if (!url) return url;
        if (/^(https?:)?\/\//.test(url)) return url;
        return BASE + url;
    }

    function init(input) {
        var form = input.closest('form');
        if (!form) return;
        var scope = form.getAttribute('data-search-scope') || 'products';

        var parent = input.parentElement;
        if (!parent) return;
        parent.classList.add('relative');

        var holder = document.createElement('div');
        holder.className = 'ai-suggest-holder';
        parent.appendChild(holder);

        var box = document.createElement('div');
        box.className = 'ai-suggest hidden';
        box.style.cssText = 'position:absolute;top:calc(100% + 8px);left:0;right:0;z-index:1000;' +
            'background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 32px rgba(2,6,23,.18);' +
            'overflow:hidden;max-height:420px;overflow-y:auto;text-align:left;';
        holder.appendChild(box);

        var items = [];
        var selected = -1;

        function hide() {
            box.classList.add('hidden');
            box.innerHTML = '';
            items = [];
            selected = -1;
        }

        function navigate(item) {
            var url = toUrl(item.url);
            if (url) {
                window.location.href = url;
            } else {
                form.submit();
            }
        }

        function render() {
            if (!items.length) {
                hide();
                return;
            }
            selected = -1;
            box.innerHTML = '';

            items.forEach(function (item, index) {
                var row = document.createElement('a');
                row.href = toUrl(item.url) || '#';
                row.className = 'flex items-center gap-3 px-4 py-2.5 text-sm cursor-pointer border-b border-slate-50 last:border-b-0 hover:bg-slate-50';
                row.setAttribute('data-index', index);

                var icon = document.createElement('span');
                icon.className = 'material-symbols-outlined text-slate-400 text-base shrink-0';
                icon.textContent = ICONS[item.type] || 'search';

                var textWrap = document.createElement('span');
                textWrap.className = 'min-w-0 flex-1';
                var label = document.createElement('span');
                label.className = 'block truncate font-medium text-slate-700';
                label.textContent = item.type === 'search' ? 'Search for "' + item.text + '"' : item.text;
                var sub = document.createElement('span');
                sub.className = 'block text-[11px] text-slate-400 truncate';
                sub.textContent = item.sub || '';

                textWrap.appendChild(label);
                textWrap.appendChild(sub);
                row.appendChild(icon);
                row.appendChild(textWrap);

                if (item.type === 'search') {
                    var arrow = document.createElement('span');
                    arrow.className = 'material-symbols-outlined text-slate-400 text-base shrink-0';
                    arrow.textContent = 'arrow_forward';
                    row.appendChild(arrow);
                }

                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    navigate(item);
                });
                box.appendChild(row);
            });

            box.classList.remove('hidden');
        }

        function fetchSuggestions(q) {
            var url = BASE + 'api/search-suggest.php?scope=' + encodeURIComponent(scope) +
                '&q=' + encodeURIComponent(q);
            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (input.value.trim() !== q) return;
                    items = (data && data.suggestions) || [];
                    render();
                })
                .catch(function () { hide(); });
        }

        var debouncedFetch = debounce(function () {
            var q = this.value.trim();
            if (q.length < 2) {
                hide();
                return;
            }
            fetchSuggestions(q);
        }, 220);

        input.addEventListener('input', debouncedFetch);
        input.addEventListener('focus', function () {
            var q = this.value.trim();
            if (q.length >= 2) fetchSuggestions(q);
        });

        input.addEventListener('keydown', function (e) {
            if (box.classList.contains('hidden') || !items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selected = selected < items.length - 1 ? selected + 1 : 0;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selected = selected > 0 ? selected - 1 : items.length - 1;
            } else if (e.key === 'Enter') {
                if (selected >= 0) {
                    e.preventDefault();
                    navigate(items[selected]);
                }
                return;
            } else if (e.key === 'Escape') {
                hide();
                return;
            }

            var rows = box.querySelectorAll('a[data-index]');
            rows.forEach(function (row, index) {
                row.classList.toggle('bg-slate-50', index === selected);
            });
        });

        input.addEventListener('blur', function () {
            setTimeout(hide, 150);
        });
    }

    function boot() {
        var forms = document.querySelectorAll('form[data-search-scope] input[name="search"]');
        for (var i = 0; i < forms.length; i++) {
            init(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
