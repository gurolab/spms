    <!-- Jquery js -->
    <script src="<?=JS_PATH?>/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap Bundle Js -->
    <script src="<?=JS_PATH?>/boostrap.bundle.min.js"></script>
    <!-- Phosphor Js -->
    <script src="<?=JS_PATH?>/phosphor-icon.js"></script>
    <!-- file upload -->
    <script src="<?=JS_PATH?>/file-upload.js"></script>
    <!-- file upload -->
    <script src="<?=JS_PATH?>/plyr.js"></script>
    <!-- dataTables -->
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <!-- select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- full calendar -->
    <script src="<?=JS_PATH?>/full-calendar.js"></script>
    <!-- jQuery UI -->
    <script src="<?=JS_PATH?>/jquery-ui.js"></script>
    <!-- jQuery UI -->
    <script src="<?=JS_PATH?>/editor-quill.js"></script>
    <!-- apex charts -->
    <script src="<?=JS_PATH?>/apexcharts.min.js"></script>
    <!-- Calendar Js -->
    <script src="<?=JS_PATH?>/calendar.js"></script>
    <!-- jvectormap Js -->
    <script src="<?=JS_PATH?>/jquery-jvectormap-2.0.5.min.js"></script>
    <!-- jvectormap world Js -->
    <script src="<?=JS_PATH?>/jquery-jvectormap-world-mill-en.js"></script>
    
    <!-- main js -->
    <script src="<?=JS_PATH?>/main.js"></script>
    
    <script src="<?=JS_PATH?>/alert.js"></script>
    <script>
        const baseUrl = "<?= rtrim(BASE_URL, '/') ?>";
        function normalizeSelect2Failure($select) {
            const id = $select.attr('id');
            if (id) {
                $('[aria-labelledby="select2-' + id + '-container"]').remove();
            }
            $select.removeClass('select2-hidden-accessible');
            $select.removeAttr('data-select2-id');
            $select.next('.select2').remove();
        }

        function isSelect2Available() {
            return typeof $.fn.select2 === 'function';
        }

        // Prevent hard JS breaks if CDN/plugin fails to load.
        if (typeof $.fn.select2 !== 'function') {
            $.fn.select2 = function () {
                return this;
            };
        } else {
            const originalSelect2 = $.fn.select2;
            $.fn.select2 = function (...args) {
                try {
                    return originalSelect2.apply(this, args);
                } catch (e) {
                    this.each(function () {
                        normalizeSelect2Failure($(this));
                    });
                    return this;
                }
            };
        }

        function initSelect2(context, forceReinit = false) {
            const $context = context ? $(context) : $(document);
            const contextIsModal = $context.hasClass('modal') || $context.closest('.modal').length > 0;

            $context.find('select.select2').each(function () {
                const $select = $(this);
                const parentModal = $select.closest('.modal');
                const modalIsHidden = parentModal.length && !parentModal.hasClass('show');

                // Defer hidden modal initialization to shown.bs.modal.
                if (!forceReinit && !contextIsModal && modalIsHidden) {
                    return;
                }

                if (forceReinit && $select.data('select2')) {
                    $select.select2('destroy');
                    normalizeSelect2Failure($select);
                }

                if ($select.data('select2')) {
                    return;
                }

                if (!isSelect2Available()) {
                    normalizeSelect2Failure($select);
                    return;
                }

                const options = {
                    width: '100%',
                    dropdownParent: parentModal.length ? parentModal : $(document.body),
                    placeholder: $select.attr('data-placeholder') || $select.attr('placeholder') || '',
                    allowClear: $select.data('allow-clear') !== undefined
                };

                $select.select2(options);
            });
        }

        function injectCsrfToken(context) {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (!tokenMeta) {
                return;
            }
            const token = tokenMeta.getAttribute('content');
            if (!token) {
                return;
            }
            const root = context || document;
            root.querySelectorAll('form[method="post"], form[method="POST"]').forEach((form) => {
                if (form.querySelector('input[name="csrf_token"]')) {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = token;
                form.appendChild(input);
            });
        }

        function initTooltips(context) {
            const root = context || document;
            const tooltipTriggerList = [].slice.call(root.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                }
            });
        }

        $(document).ready(function () {
            initSelect2(document);
            initTooltips(document);
            injectCsrfToken(document);

            $(document).on('shown.bs.modal', '.modal', function () {
                initSelect2(this, true);
                initTooltips(this);
                injectCsrfToken(this);
            });

            $(document).on('ajaxComplete', function () {
                initTooltips(document);
                injectCsrfToken(document);
            });
        });
    </script>
    </body>
</html>
