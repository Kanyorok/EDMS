{{--
    Shared drilldown modal component.

    Usage (JS):
        showDrilldown(title, headers, rows)

    Parameters:
        title   – string   – Modal heading (e.g. 'Leave — Annual Leave')
        headers – string[] – Column header labels (e.g. ['Employee', 'From', 'To', 'Days'])
        rows    – array[]  – Each row is an array of cell values matching the headers

    Include once per page that uses Chart.js click-through drilldowns:
        @include('components.drilldown-modal')
--}}
<div class="modal fade" id="drilldownModal" tabindex="-1"
     aria-labelledby="drilldownModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="drilldownModalLabel">Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="drilldownTable">
                        <thead id="drilldownHead"></thead>
                        <tbody id="drilldownBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <small class="text-muted me-auto" id="drilldownCount"></small>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * showDrilldown(title, headers, rows)
 *
 * Populates and opens the shared #drilldownModal.
 *
 * @param {string}   title   – Modal heading text.
 * @param {string[]} headers – Column header labels.
 * @param {Array[]}  rows    – Array of row arrays; each row must match headers length.
 */
window.showDrilldown = function (title, headers, rows) {
    // Set title
    document.getElementById('drilldownModalLabel').textContent = title || 'Detail';

    // Build thead
    const thead = document.getElementById('drilldownHead');
    thead.innerHTML = '';
    const tr = document.createElement('tr');
    (headers || []).forEach(function (h) {
        const th = document.createElement('th');
        th.textContent = h;
        th.style.fontSize = '0.72rem';
        th.style.textTransform = 'uppercase';
        th.style.letterSpacing = '0.05em';
        th.style.color = '#6c757d';
        th.style.background = '#f9fafb';
        th.style.whiteSpace = 'nowrap';
        tr.appendChild(th);
    });
    thead.appendChild(tr);

    // Build tbody
    const tbody = document.getElementById('drilldownBody');
    tbody.innerHTML = '';

    if (!rows || rows.length === 0) {
        const empty = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = (headers || []).length || 1;
        td.textContent = 'No records to display.';
        td.className = 'text-center text-muted py-4';
        empty.appendChild(td);
        tbody.appendChild(empty);
    } else {
        rows.forEach(function (row) {
            const rowEl = document.createElement('tr');
            (Array.isArray(row) ? row : Object.values(row)).forEach(function (cell) {
                const td = document.createElement('td');
                td.textContent = cell ?? '—';
                td.style.fontSize = '0.82rem';
                rowEl.appendChild(td);
            });
            tbody.appendChild(rowEl);
        });
    }

    // Row count
    document.getElementById('drilldownCount').textContent =
        rows && rows.length ? rows.length + ' record' + (rows.length !== 1 ? 's' : '') : '';

    // Open modal
    const modalEl = document.getElementById('drilldownModal');
    const modal = bootstrap.Modal.getOrCreate
        ? bootstrap.Modal.getOrCreate(modalEl)
        : new bootstrap.Modal(modalEl);
    modal.show();
};
</script>
