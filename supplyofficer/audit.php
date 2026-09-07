<?php

require_once __DIR__ . '/../core/auth_guard.php';
guardRole([2]); // Supply Officer
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../repo/AuditLog.model.php';

$pageTitle = 'Audit Logs';

?>

<?php require_once __DIR__ . '/../partials/head.php'; ?>
<?php require_once __DIR__ . '/../partials/preload.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="dashboard-main-wrapper">
    <div class="dashboard-body">
        <div class="breadcrumb-with-buttons mb-24 flex-between flex-wrap gap-8">
            <div class="breadcrumb mb-24">
                <ul class="flex-align gap-4">
                    <li><a href="index.php" class="text-gray-200 fw-normal text-15 hover-text-main-600">Home</a></li>
                    <li><span class="text-gray-500 fw-normal d-flex"><i class="ph ph-caret-right"></i></span></li>
                    <li><span class="text-main-600 fw-normal text-15"><?= htmlspecialchars($pageTitle) ?></span></li>
                </ul>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header border-bottom border-gray-100">
                <div class="flex-between flex-wrap gap-8">
                    <div>
                        <h5 class="mb-0 text-gray-900">Recent Activity</h5>
                        <p class="text-gray-200 text-13 mb-0">Review the latest actions taken across the system. Click "View Trail" to inspect field-level changes.</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-5 overflow-x-auto">
                <table class="table table-striped" id="auditLogTable">
                    <thead>
                        <tr>
                            <th class="h6 text-gray-300">Timestamp</th>
                            <th class="h6 text-gray-300">User</th>
                            <th class="h6 text-gray-300">Action</th>
                            <th class="h6 text-gray-300">Entity</th>
                            <th class="h6 text-gray-300">Entity ID</th>
                            <th class="h6 text-gray-300">Summary</th>
                            <th class="h6 text-gray-300">IP Address</th>
                            <th class="h6 text-gray-300 text-center">Options</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="flex-between flex-wrap gap-16">
            <p class="text-gray-300 text-13 fw-normal">&copy; Copyright Cotabato State University <?= date('Y') ?>, All Rights Reserved</p>
            <div class="flex-align flex-wrap gap-16"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="auditLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="auditLogModalLabel">Audit Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="auditModalContent" class="text-gray-900"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/alert-scripts.php'; ?>
<?php require_once __DIR__ . '/../partials/scripts.php'; ?>

<script>
    (function() {
        const modalElement = document.getElementById('auditLogModal');
        const modalContent = document.getElementById('auditModalContent');
        const modalTitle = document.getElementById('auditLogModalLabel');
        let modalInstance = null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function ensureModal() {
            if (!modalInstance) {
                modalInstance = new bootstrap.Modal(modalElement);
            }
            return modalInstance;
        }

        function renderMetadata(metadata) {
            if (!metadata || Object.keys(metadata).length === 0) {
                return '<p class="mb-0 text-gray-500">Metadata is empty.</p>';
            }

            let rows = '';
            for (const key of Object.keys(metadata)) {
                const value = metadata[key];
                let formatted = '';
                if (value === null) {
                    formatted = '<span class="text-gray-500">null</span>';
                } else if (typeof value === 'object') {
                    formatted = '<pre class="bg-gray-50 rounded p-12 border border-gray-100 text-13">' + escapeHtml(JSON.stringify(value, null, 2)) + '</pre>';
                } else {
                    formatted = '<code>' + escapeHtml(value) + '</code>';
                }
                rows += `
                    <tr>
                        <th class="text-gray-500 fw-medium text-13 w-200">${key}</th>
                        <td>${formatted}</td>
                    </tr>`;
            }

            return `
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        }

        function renderTrail(trail) {
            if (!Array.isArray(trail) || trail.length === 0) {
                return '<p class="mb-0 text-gray-500">No field-level changes were recorded.</p>';
            }

            const rows = trail.map(entry => {
                const fieldName = escapeHtml(entry.field_name ?? '');
                const oldValue = entry.old_value === null
                    ? '<span class="text-gray-500">null</span>'
                    : '<code>' + escapeHtml(entry.old_value) + '</code>';
                const newValue = entry.new_value === null
                    ? '<span class="text-gray-500">null</span>'
                    : '<code>' + escapeHtml(entry.new_value) + '</code>';
                return `
                    <tr>
                        <td class="text-gray-900">${fieldName}</td>
                        <td class="text-gray-900">${oldValue}</td>
                        <td class="text-gray-900">${newValue}</td>
                    </tr>`;
            }).join('');

            return `
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th class="text-gray-300 text-13">Field</th>
                                <th class="text-gray-300 text-13">Old Value</th>
                                <th class="text-gray-300 text-13">New Value</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        }

        const tableElement = document.getElementById('auditLogTable');
        if (tableElement) {
            tableElement.addEventListener('click', (event) => {
                const metadataButton = event.target.closest('.btn-view-metadata');
                if (metadataButton) {
                    const payload = metadataButton.getAttribute('data-metadata');
                    let metadata = {};
                    try {
                        metadata = JSON.parse(payload || '{}');
                    } catch (err) {
                        metadata = {};
                    }

                    modalTitle.textContent = 'Activity Metadata';
                    modalContent.innerHTML = renderMetadata(metadata);
                    ensureModal().show();
                    return;
                }

                const trailButton = event.target.closest('.btn-view-trail');
                if (!trailButton || trailButton.disabled) {
                    return;
                }

                const activityId = trailButton.getAttribute('data-activity-id');
                modalTitle.textContent = 'Audit Trail';
                modalContent.innerHTML = '<p class="text-gray-500 mb-0">Loading details...</p>';
                ensureModal().show();

                fetch(`audit-actions.php?action=trail&id=${activityId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modalContent.innerHTML = renderTrail(data.trail);
                        } else {
                            const message = escapeHtml(data.message ?? 'Unable to load audit trail.');
                            modalContent.innerHTML = `<p class="text-danger mb-0">${message}</p>`;
                        }
                    })
                    .catch(() => {
                        modalContent.innerHTML = '<p class="text-danger mb-0">Unable to load audit trail.</p>';
                    });
            });
        }

        if (typeof DataTable !== 'undefined') {
            new DataTable('#auditLogTable', {
                processing: true,
                serverSide: true,
                searchDelay: 350,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'desc']],
                ajax: {
                    url: 'audit-actions.php?action=list',
                    type: 'GET'
                },
                columns: [
                    { data: 'timestamp' },
                    { data: 'user' },
                    { data: 'action' },
                    { data: 'entity' },
                    { data: 'entity_id', className: 'text-center' },
                    { data: 'summary' },
                    { data: 'ip_address' },
                    { data: 'options', orderable: false, searchable: false, className: 'text-center' }
                ],
                drawCallback: function() {
                    if (typeof initTooltips === 'function') {
                        initTooltips(document);
                    }
                }
            });
        }
    })();
</script>



