/* =============================================================================
 *  APARTMENT RENTAL MARKETPLACE  —  js/marketplace.js
 * =============================================================================
 *  All client-side behaviour lives here. The application ships ZERO inline
 *  <script> blocks and ZERO onclick="" attributes, so the strict
 *  Content-Security-Policy declared in config/db.php is never violated.
 *
 *  MODULES (each is an isolated initialiser, all run on DOMContentLoaded)
 *  --------------------------------------------------------------------------
 *  01. initFeedFilters()   — instant client-side filtering + sorting of the
 *                            tenant marketplace feed (explore.php)
 *  02. initContactModal()  — "View Contact Details" overlay: builds the white
 *                            modal card from the listing's data-* attributes,
 *                            traps focus, closes on Esc / backdrop / button
 *  03. initPhoneDialer()   — detects mobile viewports and turns the call
 *                            button into a one-tap tel: dialer
 *  04. initPostForm()      — landlord form validation, description counter,
 *                            submit de-duplication (post_apartment.php)
 *  05. initFlash()         — auto-dismisses confirmation / error messages
 *
 *  Every DOM write uses textContent or createElement — never innerHTML with
 *  user supplied data — so listing content can never inject markup.
 * ========================================================================== */

(function () {
    'use strict';

    /* =====================================================================
     * SHARED UTILITIES
     * ================================================================== */

    /** Short query helpers. */
    function $(selector, scope) {
        return (scope || document).querySelector(selector);
    }
    function $$(selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    }

    /** Trailing-edge debounce — keeps typing feather-light on long feeds. */
    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            var self = this;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(self, args);
            }, wait);
        };
    }

    /** Normalises text for accent/case-insensitive matching. */
    function normalise(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    /** Formats a number as 12,000.00 (matches the PHP money() helper). */
    function formatMoney(value) {
        var n = Number(value) || 0;
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /** "Listed 3 days ago" style label from a UNIX timestamp (seconds). */
    function relativeAge(unixSeconds) {
        var created = Number(unixSeconds) || 0;
        if (!created) { return ''; }
        var diff = Math.max(0, Math.floor(Date.now() / 1000) - created);
        if (diff < 3600) { return 'Listed ' + Math.max(1, Math.floor(diff / 60)) + ' min ago'; }
        if (diff < 86400) { return 'Listed ' + Math.floor(diff / 3600) + ' h ago'; }
        return 'Listed ' + Math.floor(diff / 86400) + ' d ago';
    }

    /** Creates an element with optional class and text in one call. */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    /** True on touch-first / small-viewport devices (used for the dialer). */
    function isMobileViewport() {
        var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        var narrow = window.matchMedia && window.matchMedia('(max-width: 780px)').matches;
        var uaTouch = /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(navigator.userAgent || '');
        return Boolean((coarse && narrow) || uaTouch);
    }

    /* =====================================================================
     * 01. TENANT FEED FILTERING  (explore.php)
     * ---------------------------------------------------------------------
     * The toolbar filters already-rendered cards — no page reload and no
     * extra AJAX round trip. The same filters also work server-side (GET
     * parameters) so the feed stays fully functional without JavaScript.
     * ================================================================== */
    function initFeedFilters() {
        var form = $('#filter-form');
        var grid = $('#feed-grid');
        if (!form || !grid) { return; }

        var locationSelect = $('#filter-location', form);
        var typeSelect     = $('#filter-type', form);
        var searchInput    = $('#filter-search', form);
        var sortSelect     = $('#filter-sort', form);
        var resetButton    = $('#filter-reset', form);
        var countOut       = $('#result-count');
        var chipRow        = $('#active-filters');
        var emptyState     = $('#feed-empty');
        var cards          = $$('.listing-card', grid);
        var total          = cards.length;

        /** Reads the current control values. */
        function state() {
            return {
                location: locationSelect ? locationSelect.value : '',
                type: typeSelect ? typeSelect.value : '',
                term: normalise(searchInput ? searchInput.value : ''),
                sort: sortSelect ? sortSelect.value : 'newest'
            };
        }

        /** Applies filters + ordering to the rendered cards. */
        function apply() {
            var s = state();
            var visible = 0;

            cards.forEach(function (card) {
                var haystack = normalise([
                    card.dataset.building,
                    card.dataset.location,
                    card.dataset.unitType,
                    card.dataset.description
                ].join(' '));

                var matchesLocation = !s.location || card.dataset.location === s.location;
                var matchesType     = !s.type || card.dataset.unitType === s.type;
                var matchesTerm     = !s.term || haystack.indexOf(s.term) !== -1;
                var show = matchesLocation && matchesType && matchesTerm;

                card.classList.toggle('is-hidden', !show);
                if (show) { visible += 1; }
            });

            // Reorder the visible cards (flex/grid honour the `order` property).
            var sorted = cards.slice().sort(function (a, b) {
                if (s.sort === 'price_asc')  { return Number(a.dataset.rent) - Number(b.dataset.rent); }
                if (s.sort === 'price_desc') { return Number(b.dataset.rent) - Number(a.dataset.rent); }
                return Number(b.dataset.created) - Number(a.dataset.created);   // newest first
            });
            sorted.forEach(function (card, index) { card.style.order = String(index); });

            if (countOut) {
                countOut.textContent = visible === total
                    ? total + ' apartment' + (total === 1 ? '' : 's') + ' available in Addis Ababa'
                    : visible + ' of ' + total + ' apartments match your filters';
            }
            if (emptyState) { emptyState.hidden = visible !== 0; }

            renderChips(s);
            syncUrl(s);
        }

        /** Renders the removable "active filter" chips. */
        function renderChips(s) {
            if (!chipRow) { return; }
            chipRow.textContent = '';

            var chips = [];
            if (s.location) { chips.push({ key: 'location', label: s.location }); }
            if (s.type)     { chips.push({ key: 'type', label: s.type }); }
            if (s.term && searchInput) { chips.push({ key: 'term', label: '“' + searchInput.value.trim() + '”' }); }

            chips.forEach(function (item) {
                var chip = el('span', 'chip', item.label);
                var clear = el('button', 'chip__clear', '×');
                clear.type = 'button';
                clear.setAttribute('aria-label', 'Remove filter ' + item.label);
                clear.addEventListener('click', function () {
                    if (item.key === 'location' && locationSelect) { locationSelect.value = ''; }
                    if (item.key === 'type' && typeSelect) { typeSelect.value = ''; }
                    if (item.key === 'term' && searchInput) { searchInput.value = ''; }
                    apply();
                });
                chip.appendChild(clear);
                chipRow.appendChild(chip);
            });
        }

        /** Mirrors the active filters into the address bar (shareable URLs). */
        function syncUrl(s) {
            if (!window.history || !window.history.replaceState) { return; }
            var params = new URLSearchParams();
            if (s.location) { params.set('location', s.location); }
            if (s.type) { params.set('unit_type', s.type); }
            if (s.term && searchInput) { params.set('q', searchInput.value.trim()); }
            if (s.sort && s.sort !== 'newest') { params.set('sort', s.sort); }
            var query = params.toString();
            window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
        }

        /* --- Wiring ---------------------------------------------------- */
        if (locationSelect) { locationSelect.addEventListener('change', apply); }
        if (typeSelect)     { typeSelect.addEventListener('change', apply); }
        if (sortSelect)     { sortSelect.addEventListener('change', apply); }
        if (searchInput)    { searchInput.addEventListener('input', debounce(apply, 160)); }

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                if (locationSelect) { locationSelect.value = ''; }
                if (typeSelect) { typeSelect.value = ''; }
                if (searchInput) { searchInput.value = ''; }
                if (sortSelect) { sortSelect.value = 'newest'; }
                apply();
                if (searchInput) { searchInput.focus(); }
            });
        }

        // The server may have pre-filtered; still align chips/order on load.
        apply();
    }

    /* =====================================================================
     * 02. CONTACT DETAILS MODAL
     * ---------------------------------------------------------------------
     * Clicking "View Contact Details" opens a white card over a blurred
     * backdrop and prints the landlord metadata straight from the database
     * rows embedded in the card's data-* attributes.
     * ================================================================== */
    function initContactModal() {
        var root = $('#contact-modal');
        var grid = $('#feed-grid');
        if (!root || !grid) { return; }

        var lastFocused = null;

        /* Cache the modal's own sub-nodes once. */
        var nodes = {
            image:      $('#modal-image', root),
            mediaHood:  $('#modal-media-location', root),
            title:      $('#modal-title', root),
            location:   $('#modal-location', root),
            type:       $('#modal-type', root),
            rent:       $('#modal-rent', root),
            building:   $('#modal-building', root),
            added:      $('#modal-added', root),
            instruction:$('#modal-instruction', root),
            phoneLabel: $('#modal-phone-label', root),
            callBtn:    $('#modal-call', root),
            copyBtn:    $('#modal-copy', root),
            closeBtn:   $('#modal-close', root),
            status:     $('#modal-copy-status', root)
        };

        /** Collects the dialog's focusable controls for the focus trap. */
        function focusables() {
            return $$('button, a[href], [tabindex]:not([tabindex="-1"])', root)
                .filter(function (node) { return node.offsetParent !== null; });
        }

        /** Opens the overlay and hydrates it from a listing card. */
        function open(card) {
            var data = card.dataset;
            lastFocused = document.activeElement;

            /* Neighbourhood photograph + district label in the media strip.
             * The src is only assigned when a path is present, because an empty
             * src would re-request the current page. */
            if (nodes.image && data.image) {
                nodes.image.src = data.image;
                nodes.image.alt = 'Apartments in ' + (data.district || 'Addis Ababa') + ', Addis Ababa';
            }
            if (nodes.mediaHood) { nodes.mediaHood.textContent = data.district || 'Addis Ababa'; }

            if (nodes.title)      { nodes.title.textContent = data.building || 'Apartment unit'; }
            if (nodes.location)   { nodes.location.textContent = data.location || '—'; }
            if (nodes.type)       { nodes.type.textContent = data.unitType || '—'; }
            if (nodes.building)   { nodes.building.textContent = data.building || '—'; }
            if (nodes.added)      { nodes.added.textContent = relativeAge(data.created) || 'Recently'; }
            if (nodes.rent)       { nodes.rent.textContent = 'ETB ' + formatMoney(data.rent); }

            /* The exact tenant instruction required by the specification. */
            if (nodes.instruction) {
                nodes.instruction.textContent = '';
                nodes.instruction.appendChild(document.createTextNode('To rent this unit, please call '));
                nodes.instruction.appendChild(el('strong', null, data.landlord || 'the landlord'));
                nodes.instruction.appendChild(document.createTextNode(' directly at '));
                nodes.instruction.appendChild(el('strong', null, data.phone || '—'));
                nodes.instruction.appendChild(document.createTextNode('.'));
            }

            if (nodes.phoneLabel) { nodes.phoneLabel.textContent = data.phone || '—'; }

            if (nodes.callBtn) {
                nodes.callBtn.setAttribute('href', 'tel:' + (data.phone || ''));
                nodes.callBtn.setAttribute('aria-label', 'Call ' + (data.landlord || 'landlord') + ' at ' + (data.phone || ''));
            }
            if (nodes.copyBtn) {
                nodes.copyBtn.dataset.phone = data.phone || '';
                nodes.copyBtn.dataset.landlord = data.landlord || '';
                nodes.copyBtn.setAttribute('aria-label', 'Copy the landlord phone number ' + (data.phone || ''));
            }
            if (nodes.status)   { nodes.status.textContent = ''; }

            root.classList.add('is-open');
            root.setAttribute('aria-hidden', 'false');
            document.body.classList.add('is-modal-open');

            var first = focusables()[0];
            if (first) { first.focus(); }
        }

        /** Closes the overlay and restores focus to the trigger card. */
        function close() {
            root.classList.remove('is-open');
            root.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('is-modal-open');
            if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
        }

        /* --- Triggers (event delegation covers dynamically rendered cards) */
        grid.addEventListener('click', function (event) {
            var trigger = event.target.closest('.js-contact');
            if (!trigger) { return; }
            var card = trigger.closest('.listing-card');
            if (!card) { return; }
            event.preventDefault();
            open(card);
        });

        /* --- Dismissal ------------------------------------------------- */
        root.addEventListener('click', function (event) {
            if (event.target === root) { close(); }                 // backdrop click
        });
        if (nodes.closeBtn) { nodes.closeBtn.addEventListener('click', close); }

        document.addEventListener('keydown', function (event) {
            if (!root.classList.contains('is-open')) { return; }

            if (event.key === 'Escape') { close(); return; }

            if (event.key === 'Tab') {                              // focus trap
                var items = focusables();
                if (!items.length) { return; }
                var first = items[0];
                var last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        /* --- Copy the phone number (desktop convenience) ---------------- */
        if (nodes.copyBtn) {
            nodes.copyBtn.addEventListener('click', function () {
                var phone = nodes.copyBtn.dataset.phone || '';
                if (!phone) { return; }

                var done = function () {
                    if (nodes.status) { nodes.status.textContent = 'Phone number copied — ' + phone; }
                };
                var failed = function () {
                    if (nodes.status) { nodes.status.textContent = 'Copy failed. Please dial ' + phone + ' manually.'; }
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(phone).then(done, failed);
                } else {
                    // Legacy fallback for older browsers / insecure contexts.
                    var helper = el('textarea');
                    helper.value = phone;
                    helper.setAttribute('readonly', 'readonly');
                    helper.style.position = 'fixed';
                    helper.style.top = '-1000px';
                    document.body.appendChild(helper);
                    helper.select();
                    try { document.execCommand('copy'); done(); } catch (err) { failed(); }
                    document.body.removeChild(helper);
                }
            });
        }
    }

    /* =====================================================================
     * 03. MOBILE PHONE DIALER BEHAVIOUR
     * ================================================================== */
    function initPhoneDialer() {
        var root = $('#contact-modal');
        if (!root) { return; }

        var callBtn = $('#modal-call', root);
        var hint    = $('#modal-call-hint', root);
        var mobile  = isMobileViewport();

        if (callBtn) {
            callBtn.classList.toggle('is-mobile-dialer', mobile);
            var label = callBtn.querySelector('.js-call-label');
            if (label) { label.textContent = mobile ? 'Tap to call now' : 'Call the landlord'; }
        }
        if (hint) {
            // The hint is a static element that must always be re-enabled for
            // desktop users after a mobile visit (no inline styles involved).
            hint.hidden = !mobile;
        }
    }

    /* =====================================================================
     * 04. LANDLORD POSTING FORM UX  (post_apartment.php)
     * ================================================================== */
    function initPostForm() {
        var form = $('#post-form');
        if (!form) { return; }

        var description = $('#description', form);
        var counter = $('#description-counter');
        var rentInput = $('#monthly_rent', form);
        var submitted = false;

        /* --- Live character counter for the description --------------- */
        if (description && counter) {
            var max = Number(description.getAttribute('maxlength')) || 700;
            var updateCounter = function () {
                var used = description.value.length;
                counter.textContent = used + ' / ' + max + ' characters';
                counter.classList.toggle('is-limit', used > max * 0.9);
            };
            description.addEventListener('input', updateCounter);
            updateCounter();
        }

        /* --- Rent: strip anything that is not a digit or a dot -------- */
        if (rentInput) {
            rentInput.addEventListener('input', function () {
                var cleaned = rentInput.value.replace(/[^0-9.]/g, '');
                if (cleaned !== rentInput.value) { rentInput.value = cleaned; }
            });
        }

        /** Marks a field invalid and writes the message under it. */
        function setError(field, message) {
            if (!field) { return; }
            var wrapper = field.closest('.field');
            if (!wrapper) { return; }
            wrapper.classList.add('has-error');
            var slot = wrapper.querySelector('.field__error');
            if (slot) { slot.textContent = message; }
            field.setAttribute('aria-invalid', 'true');
        }

        function clearErrors() {
            $$('.field.has-error', form).forEach(function (wrapper) {
                wrapper.classList.remove('has-error');
                var input = wrapper.querySelector('.input, .select, .textarea');
                if (input) { input.removeAttribute('aria-invalid'); }
            });
        }

        /** Client-side mirror of the PHP validation rules. */
        function validate() {
            clearErrors();
            var firstInvalid = null;
            var fields = $$('[data-required]', form);

            fields.forEach(function (field) {
                var value = (field.value || '').trim();
                var message = '';

                if (!value) {
                    message = 'This field is required.';
                } else if (field.id === 'monthly_rent') {
                    var rent = Number(value);
                    if (!(rent > 0)) {
                        message = 'Enter a monthly rent greater than 0.';
                    } else if (rent > 1000000) {
                        message = 'That rent looks unrealistic — check the amount.';
                    }
                } else if (field.id === 'landlord_phone') {
                    if (!/^(\+?251|0)?[79]\d{8}$/.test(value.replace(/[\s-]/g, ''))) {
                        message = 'Enter a valid Ethiopian mobile number, e.g. 0911234567.';
                    }
                } else if (field.minLength > 0 && value.length < field.minLength) {
                    message = 'Please use at least ' + field.minLength + ' characters.';
                }

                if (message) {
                    setError(field, message);
                    if (!firstInvalid) { firstInvalid = field; }
                }
            });

            return firstInvalid;
        }

        /* --- Validate on submit, then let PHP re-validate authoritatively */
        form.addEventListener('submit', function (event) {
            if (submitted) { event.preventDefault(); return; }   // double-click guard
            var firstInvalid = validate();
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }
            submitted = true;
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('is-disabled');
                var label = submitBtn.querySelector('.js-submit-label');
                if (label) { label.textContent = 'Publishing your listing…'; }
            }
        });

        /* --- Clear a field's error as soon as the user fixes it ------- */
        form.addEventListener('input', function (event) {
            var wrapper = event.target.closest('.field.has-error');
            if (wrapper) {
                wrapper.classList.remove('has-error');
                event.target.removeAttribute('aria-invalid');
            }
        });

        /* --- Tiny live preview of the listing headline ---------------- */
        var building = $('#building_name', form);
        var location = $('#location', form);
        var types    = $('#unit_type', form);
        var preview  = $('#post-preview');
        if (preview && building && location) {
            var paint = function () {
                var name = building.value.trim() || 'Building name';
                var place = location.value || 'Location';
                var kind = (types && types.value) || 'Unit type';
                var price = rentInput && rentInput.value ? 'ETB ' + formatMoney(rentInput.value) + ' / month' : 'price on request';
                preview.textContent = '';
                preview.appendChild(el('b', null, name));
                preview.appendChild(document.createTextNode(' · ' + place + ' · ' + kind + ' · ' + price));
            };
            ['input', 'change'].forEach(function (evt) {
                building.addEventListener(evt, paint);
                if (location) { location.addEventListener(evt, paint); }
                if (types) { types.addEventListener(evt, paint); }
                if (rentInput) { rentInput.addEventListener(evt, paint); }
            });
            paint();
        }
    }

    /* =====================================================================
     * 05. FLASH MESSAGES
     * ================================================================== */
    function initFlash() {
        var dismissers = $$('[data-dismiss-flash]');

        dismissers.forEach(function (button) {
            button.addEventListener('click', function () {
                var flash = button.closest('.flash');
                if (flash) { dismiss(flash); }
            });
        });

        // Auto-fade success and info messages after a comfortable pause.
        $$('.flash--success, .flash--info').forEach(function (flash) {
            window.setTimeout(function () { dismiss(flash); }, 7000);
        });

        function dismiss(flash) {
            flash.classList.add('is-dismissed');
            window.setTimeout(function () {
                if (flash.parentNode) { flash.parentNode.removeChild(flash); }
            }, 320);
        }
    }

    /* =====================================================================
     * BOOTSTRAP
     * ================================================================== */
    function init() {
        initFeedFilters();
        initContactModal();
        initPhoneDialer();
        initPostForm();
        initFlash();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
