<?php
/**
 * Footer - Close main content and include all scripts
 */
?>
</main><!-- /.main-content -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.0/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.0/vfs_fonts.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.18/dist/sweetalert2.all.min.js"></script>

<!-- Toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    // ============================================
    // Global Configuration
    // ============================================
    const APP_URL = '<?php echo APP_URL; ?>';
    const CURRENCY_SYMBOL = '<?php echo CURRENCY_SYMBOL; ?>';

    // Configure Toastr
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 4000
    };

    // Configure SweetAlert2
    Swal.fire.DismissReason = Swal.DismissReason || {};

    // ============================================
    // Sidebar Toggle
    // ============================================
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        localStorage.setItem('sidebar-collapsed', 
            document.getElementById('sidebar').classList.contains('collapsed'));
    });

    // Restore sidebar state
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        document.getElementById('sidebar').classList.add('collapsed');
    }

    // ============================================
    // Notification Bell
    // ============================================
    document.getElementById('notificationBell').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('notificationDropdown').classList.toggle('show');
        loadNotifications();
    });

    function loadNotifications() {
        // Load notifications via AJAX
        fetch(APP_URL + '/admin/api/notifications.php')
            .then(response => response.json())
            .then(data => {
                let notificationList = document.getElementById('notificationList');
                
                if (data.notifications && data.notifications.length > 0) {
                    notificationList.innerHTML = data.notifications.map(n => `
                        <div class="notification-item ${n.is_read ? '' : 'unread'} ${n.type}">
                            <p class="notification-text">${n.title}</p>
                            <div class="notification-time">${n.time_ago || 'Just now'}</div>
                        </div>
                    `).join('');
                } else {
                    notificationList.innerHTML = `
                        <div style="padding: 20px; text-align: center; color: #999;">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            No notifications
                        </div>
                    `;
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    // Close notification dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#notificationBell') && !e.target.closest('#notificationDropdown')) {
            document.getElementById('notificationDropdown').classList.remove('show');
        }
    });

    // ============================================
    // Form Helpers
    // ============================================

    /**
     * Initialize all form elements on page load
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all select2 dropdowns
        $('.select2').select2({
            width: '100%',
            allowClear: true,
            placeholder: 'Select an option...'
        });

        // Initialize all Flatpickr date pickers
        document.querySelectorAll('[data-flatpickr]').forEach(el => {
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd M Y'
            });
        });

        // Initialize DataTables
        document.querySelectorAll('[data-datatable]').forEach(table => {
            new DataTable(table, {
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records..."
                },
                dom: '<"row mb-3"<"col-md-6"l><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>',
                pageLength: 10,
                displayLength: 10
            });
        });

        // Initialize DataTables with export buttons
        document.querySelectorAll('[data-datatable-export]').forEach(table => {
            new DataTable(table, {
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records..."
                },
                dom: 'Brtip',
                buttons: [
                    {extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-sm btn-success me-2'},
                    {extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-sm btn-danger me-2'},
                    {extend: 'print', text: '<i class="fas fa-print"></i> Print', className: 'btn btn-sm btn-info'}
                ],
                pageLength: 10
            });
        });
    });

    // ============================================
    // Delete Confirmation
    // ============================================
    function confirmDelete(title = 'Delete?', message = 'Are you sure? This cannot be undone.', callback = null) {
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF5E5E',
            cancelButtonColor: '#999',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof callback === 'function') {
                    callback();
                } else if (typeof callback === 'string') {
                    window.location.href = callback;
                }
            }
        });
    }

    // ============================================
    // AJAX Delete
    // ============================================
    async function deleteRecord(url) {
        try {
            const response = await fetch(url, { method: 'DELETE' });
            const data = await response.json();
            
            if (data.success) {
                toastr.success('Record deleted successfully');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(data.message || 'Failed to delete record');
            }
        } catch (error) {
            toastr.error('An error occurred');
            console.error(error);
        }
    }

    // ============================================
    // Format Currency
    // ============================================
    function formatCurrency(amount) {
        return CURRENCY_SYMBOL + parseFloat(amount).toLocaleString('en-PK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ============================================
    // Dynamic Form Calculation
    // ============================================
    function calculateInvoiceTotal() {
        const subtotal = parseFloat(document.getElementById('subtotal')?.value || 0);
        const taxPercent = parseFloat(document.getElementById('tax_percent')?.value || 0);
        const discountPercent = parseFloat(document.getElementById('discount_percent')?.value || 0);

        const taxAmount = (subtotal * taxPercent) / 100;
        const discountAmount = (subtotal * discountPercent) / 100;
        const total = subtotal + taxAmount - discountAmount;

        if (document.getElementById('tax_amount')) {
            document.getElementById('tax_amount').value = taxAmount.toFixed(2);
        }
        if (document.getElementById('discount_amount')) {
            document.getElementById('discount_amount').value = discountAmount.toFixed(2);
        }
        if (document.getElementById('total')) {
            document.getElementById('total').value = total.toFixed(2);
            document.getElementById('total_display').textContent = formatCurrency(total);
        }
    }

    // ============================================
    // Status Change Confirmation
    // ============================================
    function changeStatus(elementId, newStatus, recordId, endpoint) {
        fetch(APP_URL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                record_id: recordId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success('Status updated successfully');
                location.reload();
            } else {
                toastr.error(data.message || 'Failed to update status');
            }
        })
        .catch(error => {
            toastr.error('An error occurred');
            console.error(error);
        });
    }

    // ============================================
    // WhatsApp Integration
    // ============================================
    function sendWhatsAppMessage(phone, message, type = 'custom') {
        Swal.fire({
            title: 'Send WhatsApp Message',
            html: `
                <div class="text-start">
                    <label>Message:</label>
                    <textarea id="waMessage" class="form-control mt-2" rows="4">${message}</textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Send'
        }).then((result) => {
            if (result.isConfirmed) {
                const msg = document.getElementById('waMessage').value;
                
                fetch(APP_URL + '/admin/api/send-whatsapp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone: phone,
                        message: msg,
                        type: type
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        toastr.success('Message sent successfully');
                    } else {
                        toastr.error(data.message || 'Failed to send message');
                    }
                })
                .catch(error => {
                    toastr.error('An error occurred');
                    console.error(error);
                });
            }
        });
    }
</script>

</body>
</html>
