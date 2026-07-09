/**
 * Searchable select fields (Select2) — RTL dashboard defaults.
 */
(function () {
    'use strict';

    const DEFAULT_OPTIONS = {
        dir: 'rtl',
        width: '100%',
        placeholder: 'ابحث أو اختر...',
        allowClear: true,
        language: {
            noResults: function () {
                return 'لا توجد نتائج';
            },
            searching: function () {
                return 'جاري البحث...';
            },
            inputTooShort: function () {
                return 'اكتب للبحث';
            },
        },
    };

    function getJQuery() {
        return window.jQuery || window.$;
    }

    function isInitialized(selectEl) {
        return selectEl.classList.contains('select2-hidden-accessible');
    }

    function wrapForDropdown($el) {
        const parent = $el.parent();

        if (!parent.hasClass('position-relative')) {
            $el.wrap('<div class="position-relative"></div>');
        }

        return $el.parent();
    }

    window.destroySearchableSelect = function (el) {
        const $ = getJQuery();

        if (!$ || !$.fn || !$.fn.select2 || !el) {
            return;
        }

        const $el = $(el);

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
    };

    window.initSearchableSelects = function (root) {
        const $ = getJQuery();

        if (!$ || !$.fn || !$.fn.select2) {
            return;
        }

        const scope = root && root.querySelectorAll ? root : document;
        const selects = scope.querySelectorAll
            ? scope.querySelectorAll('select.select2-searchable')
            : [];

        selects.forEach(function (selectEl) {
            if (isInitialized(selectEl)) {
                return;
            }

            const $el = $(selectEl);
            const dropdownParent = wrapForDropdown($el);

            $el.select2(Object.assign({}, DEFAULT_OPTIONS, {
                dropdownParent: dropdownParent,
            }));
        });
    };

    function bootSearchableSelects() {
        window.initSearchableSelects();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootSearchableSelects);
    } else {
        bootSearchableSelects();
    }
})();
