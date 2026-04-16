<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
$pageTitle = 'Document Bank';

global $db;

// Create documents table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS `document_bank` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_id` INT NOT NULL,
        `document_title` VARCHAR(255) NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_size` INT,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
    )
");

// Add original_name column if it doesn't exist (for existing tables)
try {
    $db->exec("ALTER TABLE `document_bank` ADD COLUMN `original_name` VARCHAR(255) DEFAULT ''");
} catch (Exception $e) {
    // Column already exists
}

// Fetch all clients for dropdown
$stmt = $db->prepare("SELECT id, client_name FROM clients ORDER BY client_name ASC");
$stmt->execute();
$clients = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #6418C3;
            --secondary: #1EAAE7;
            --success: #2BC155;
            --warning: #FF9B52;
            --danger: #FF5E5E;
            --dark: #1D1D1D;
            --body-bg: #F4F4F4;
            --card-bg: #FFFFFF;
            --border: #EEEEEE;
        }

        .doc-main {
            /* margin-left: 270px; */
            min-height: calc(100vh - 60px);
            padding: 30px;
            background: var(--body-bg);
        }

        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .doc-header h1 {
            margin: 0;
            font-size: 2rem;
            color: var(--dark);
            font-weight: 700;
        }

        .btn-add-doc {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-add-doc:hover {
            background: #5310a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 24, 195, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-top: 4px solid;
        }

        .stat-card.total {
            border-top-color: var(--primary);
        }

        .stat-card.recent {
            border-top-color: var(--secondary);
        }

        .stat-card.images {
            border-top-color: var(--success);
        }

        .stat-card.pdfs {
            border-top-color: var(--warning);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            color: #666;
            margin-top: 8px;
            font-weight: 600;
        }

        .upload-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .upload-section h5 {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark);
            margin-bottom: 8px;
            display: block;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(100, 24, 195, 0.1);
        }

        .drag-drop-area {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .drag-drop-area:hover {
            border-color: var(--primary);
            background: #f5f0ff;
        }

        .drag-drop-area.dragover {
            border-color: var(--primary);
            background: #f0e8ff;
        }

        .drag-drop-area i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            display: block;
        }

        .drag-drop-area p {
            color: #666;
            font-size: 0.95rem;
            margin: 0;
        }

        .drag-drop-area small {
            display: block;
            color: #999;
            font-size: 0.85rem;
            margin-top: 10px;
        }

        #fileInput {
            display: none;
        }

        .file-info {
            background: #f0f0f0;
            border-radius: 6px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9rem;
            display: none;
        }

        .file-info.show {
            display: block;
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn-upload {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-upload:hover {
            background: #5211a8;
        }

        .btn-clear {
            background: var(--body-bg);
            color: var(--dark);
            border: 1px solid var(--border);
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-clear:hover {
            background: #e8e8e8;
        }

        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .documents-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .documents-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .document-item {
            background: #f9f9f9;
            padding: 18px;
            border-radius: 10px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
            border-left: 4px solid var(--secondary);
            transition: all 0.3s ease;
        }

        .document-item:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateX(4px);
        }

        .document-icon {
            font-size: 1.8rem;
            color: var(--secondary);
            min-width: 40px;
            text-align: center;
        }

        .document-content {
            flex: 1;
        }

        .document-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark);
            margin: 0 0 6px 0;
        }

        .document-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .doc-client {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(100, 24, 195, 0.15);
            color: var(--primary);
        }

        .doc-size {
            font-size: 0.85rem;
            color: #666;
        }

        .doc-date {
            font-size: 0.85rem;
            color: #666;
        }

        .document-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .btn-view {
            color: var(--secondary);
            background: rgba(30, 170, 231, 0.1);
        }

        .btn-view:hover {
            background: rgba(30, 170, 231, 0.2);
        }

        .btn-download {
            color: var(--success);
            background: rgba(43, 193, 85, 0.1);
        }

        .btn-download:hover {
            background: rgba(43, 193, 85, 0.2);
        }

        .btn-delete {
            color: var(--danger);
            background: rgba(255, 94, 94, 0.1);
        }

        .btn-delete:hover {
            background: rgba(255, 94, 94, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-backdrop.show {
            display: flex;
        }

        .modal-content-box {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 900px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header-box h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .modal-close:hover {
            color: var(--dark);
        }

        .file-viewer {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .file-viewer img {
            max-width: 100%;
            max-height: 500px;
            border-radius: 6px;
        }

        .file-viewer iframe {
            width: 100%;
            height: 500px;
            border: 1px solid var(--border);
            border-radius: 6px;
        }

        .file-info-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }

        .file-info-box p {
            margin: 8px 0;
            font-size: 0.9rem;
        }

        .file-info-label {
            font-weight: 600;
            color: var(--dark);
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            animation: slideIn 0.3s ease;
            border-left: 4px solid var(--primary);
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .toast-notification.success {
            border-left-color: var(--success);
        }

        .toast-notification.error {
            border-left-color: var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .doc-main {
                margin-left: 0;
                padding: 20px;
            }

            .doc-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-bar input,
            .filter-bar select {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .document-item {
                flex-direction: column;
            }

            .document-actions {
                width: 100%;
            }

            .btn-action {
                flex: 1;
            }
        }
    </style>
</head>
<body>

<div class="doc-main">
    <!-- HEADER -->
    <div class="doc-header">
        <h1><i class="fas fa-file-archive"></i> Document Bank</h1>
        <button class="btn-add-doc" onclick="toggleUploadForm()"><i class="fas fa-plus"></i> Upload Document</button>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-number" id="statTotal">0</div>
            <div class="stat-label">Total Documents</div>
        </div>
        <div class="stat-card recent">
            <div class="stat-number" id="statRecent">0</div>
            <div class="stat-label">This Month</div>
        </div>
        <div class="stat-card images">
            <div class="stat-number" id="statImages">0</div>
            <div class="stat-label">Images</div>
        </div>
        <div class="stat-card pdfs">
            <div class="stat-number" id="statPdfs">0</div>
            <div class="stat-label">PDFs</div>
        </div>
    </div>

    <!-- UPLOAD SECTION (HIDDEN BY DEFAULT) -->
    <div class="upload-section" id="uploadForm" style="display: none;">
        <h5><i class="fas fa-cloud-upload-alt"></i> Upload New Document</h5>
        
        <div class="form-grid">
            <!-- Left Column -->
            <div>
                <div class="form-group">
                    <label>Document Title *</label>
                    <input type="text" id="docTitle" placeholder="e.g., Invoice #2024-001" required>
                </div>

                <div class="form-group">
                    <label>Select Client *</label>
                    <select id="docClient" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"><?php echo clean($client['client_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Right Column - File Upload -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.9rem; color: var(--dark); margin-bottom: 8px;">Upload File *</label>
                <div class="drag-drop-area" id="dragDropArea">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Drag & drop your file here</p>
                    <small>or click to browse (PDF, Images - Max 10MB)</small>
                </div>
                <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                <div id="fileInfo" class="file-info">
                    <strong id="fileName"></strong><br>
                    <small id="fileSize"></small>
                </div>
            </div>
        </div>

        <div class="button-group">
            <button class="btn-clear" id="clearBtn"><i class="fas fa-redo"></i> Clear</button>
            <button class="btn-upload" id="uploadBtn"><i class="fas fa-check"></i> Upload Document</button>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search by title or client..." style="flex: 1; min-width: 200px;">
        <select id="filterClient" style="min-width: 150px;">
            <option value="">All Clients</option>
            <?php foreach($clients as $client): ?>
                <option value="<?php echo $client['id']; ?>"><?php echo clean($client['client_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- DOCUMENTS LIST CONTAINER -->
    <div class="documents-container">
        <div id="documentsList" class="documents-list">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No documents yet. Upload your first document above!</p>
            </div>
        </div>
    </div>
</div>

<!-- FILE VIEWER MODAL -->
<div class="modal-backdrop" id="fileModal">
    <div class="modal-content-box">
        <div class="modal-header-box">
            <h3 id="modalTitle">View Document</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="fileViewer" class="file-viewer">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Data storage
    let documents = [];
    let clientNames = {};

    // DOM Elements
    const dragDropArea = document.getElementById('dragDropArea');
    const fileInput = document.getElementById('fileInput');
    const docTitle = document.getElementById('docTitle');
    const docClient = document.getElementById('docClient');
    const filterClient = document.getElementById('filterClient');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadBtn = document.getElementById('uploadBtn');
    const clearBtn = document.getElementById('clearBtn');
    const documentsList = document.getElementById('documentsList');
    const searchInput = document.getElementById('searchInput');
    const uploadForm = document.getElementById('uploadForm');

    let selectedFile = null;

    // Store client names
    const clientOptions = document.getElementById('docClient');
    clientOptions.querySelectorAll('option').forEach(opt => {
        if (opt.value) {
            clientNames[opt.value] = opt.text;
        }
    });

    // Toggle upload form
    function toggleUploadForm() {
        uploadForm.style.display = uploadForm.style.display === 'none' ? 'block' : 'none';
    }

    // File drag and drop
    dragDropArea.addEventListener('click', () => fileInput.click());

    dragDropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dragDropArea.classList.add('dragover');
    });

    dragDropArea.addEventListener('dragleave', () => {
        dragDropArea.classList.remove('dragover');
    });

    dragDropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dragDropArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length) handleFileSelect(files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) handleFileSelect(e.target.files[0]);
    });

    function handleFileSelect(file) {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (!validTypes.includes(file.type)) {
            showToast('Invalid file type. Only PDF and images allowed.', 'error');
            return;
        }

        if (file.size > maxSize) {
            showToast('File too large. Max 10MB allowed.', 'error');
            return;
        }

        selectedFile = file;
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.classList.add('show');
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    clearBtn.addEventListener('click', () => {
        docTitle.value = '';
        docClient.value = '';
        fileInput.value = '';
        selectedFile = null;
        fileInfo.classList.remove('show');
    });

    uploadBtn.addEventListener('click', () => {
        if (!docTitle.value || !docClient.value || !selectedFile) {
            showToast('Please fill all fields and select a file.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('document_title', docTitle.value);
        formData.append('client_id', docClient.value);
        formData.append('file', selectedFile);

        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

        fetch('<?php echo APP_URL; ?>/admin/api/upload-document.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Document uploaded successfully!', 'success');
                clearBtn.click();
                uploadForm.style.display = 'none';
                fetchDocuments();
            } else {
                showToast(data.error || 'Upload failed', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            showToast('Upload failed', 'error');
        })
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-check"></i> Upload Document';
        });
    });

    searchInput.addEventListener('input', renderDocuments);
    filterClient.addEventListener('change', renderDocuments);

    function fetchDocuments() {
        fetch(`<?php echo APP_URL; ?>/admin/api/get-documents.php`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    documents = data.documents || [];
                    renderDocuments();
                    updateStats();
                } else {
                    documents = [];
                    renderDocuments();
                }
            })
            .catch(err => {
                console.error('Error fetching documents:', err);
                documents = [];
                renderDocuments();
            });
    }

    function updateStats() {
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();

        let total = documents.length;
        let thisMonth = 0;
        let images = 0;
        let pdfs = 0;

        documents.forEach(doc => {
            const uploadDate = new Date(doc.uploaded_at);
            if (uploadDate.getMonth() === currentMonth && uploadDate.getFullYear() === currentYear) {
                thisMonth++;
            }

            const ext = doc.file_path.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                images++;
            } else if (ext === 'pdf') {
                pdfs++;
            }
        });

        document.getElementById('statTotal').textContent = total;
        document.getElementById('statRecent').textContent = thisMonth;
        document.getElementById('statImages').textContent = images;
        document.getElementById('statPdfs').textContent = pdfs;
    }

    function renderDocuments() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedClientId = filterClient.value;

        const filtered = documents.filter(doc => {
            const matchSearch = doc.document_title.toLowerCase().includes(searchTerm) ||
                              doc.client_name.toLowerCase().includes(searchTerm);
            const matchClient = !selectedClientId || doc.client_id === parseInt(selectedClientId);
            return matchSearch && matchClient;
        });

        if (filtered.length === 0) {
            documentsList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>${searchTerm || selectedClientId ? 'No documents found.' : 'No documents yet.'}</p>
                </div>
            `;
            return;
        }

        documentsList.innerHTML = filtered.map(doc => {
            const ext = doc.file_path.split('.').pop().toLowerCase();
            const icon = ['pdf'].includes(ext) ? 'fas fa-file-pdf' : 'fas fa-image';
            const iconColor = ['pdf'].includes(ext) ? '#FF5E5E' : '#2BC155';

            return `
                <div class="document-item">
                    <div class="document-icon" style="color: ${iconColor};">
                        <i class="${icon}"></i>
                    </div>
                    <div class="document-content">
                        <div class="document-title">${doc.document_title}</div>
                        <div class="document-meta">
                            <span class="doc-client"><i class="fas fa-user"></i> ${doc.client_name}</span>
                            <span class="doc-size"><i class="fas fa-weight"></i> ${formatFileSize(doc.file_size)}</span>
                            <span class="doc-date"><i class="fas fa-calendar"></i> ${new Date(doc.uploaded_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                    <div class="document-actions">
                        <button class="btn-action btn-view" onclick="viewDoc(${doc.id}, '${doc.document_title}')" title="View"><i class="fas fa-eye"></i> View</button>
                        <button class="btn-action btn-download" onclick="downloadDoc(${doc.id})" title="Download"><i class="fas fa-download"></i> Download</button>
                        <button class="btn-action btn-delete" onclick="confirmDelete(${doc.id}, '${doc.document_title.replace(/'/g, "\\'")}')" title="Delete"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function downloadDoc(id) {
        const doc = documents.find(d => d.id === id);
        if (!doc) return;

        window.location.href = `<?php echo APP_URL; ?>/admin/api/download-document.php?id=${id}`;
        showToast('Download started!', 'success');
    }

    function viewDoc(id, title) {
        const doc = documents.find(d => d.id === id);
        if (!doc) return;

        const fileModal = document.getElementById('fileModal');
        const modalTitle = document.getElementById('modalTitle');
        const fileViewer = document.getElementById('fileViewer');
        
        modalTitle.textContent = `View: ${title}`;
        
        // Get file extension
        const fileExt = doc.file_path.split('.').pop().toLowerCase();
        const apiUrl = `<?php echo APP_URL; ?>/admin/api`;
        
        // Display based on file type
        if (['jpg', 'jpeg', 'png', 'webp'].includes(fileExt)) {
            fileViewer.innerHTML = `
                <img src="${apiUrl}/download-document.php?id=${id}" alt="${title}">
                <div class="file-info-box">
                    <p><span class="file-info-label">File Name:</span> ${doc.file_name}</p>
                    <p><span class="file-info-label">Size:</span> ${formatFileSize(doc.file_size)}</p>
                    <p><span class="file-info-label">Uploaded:</span> ${new Date(doc.uploaded_at).toLocaleString()}</p>
                </div>
            `;
        } else if (fileExt === 'pdf') {
            fileViewer.innerHTML = `
                <iframe src="${apiUrl}/download-document.php?id=${id}"></iframe>
                <div class="file-info-box">
                    <p><span class="file-info-label">File Name:</span> ${doc.file_name}</p>
                    <p><span class="file-info-label">Size:</span> ${formatFileSize(doc.file_size)}</p>
                    <p><span class="file-info-label">Uploaded:</span> ${new Date(doc.uploaded_at).toLocaleString()}</p>
                </div>
            `;
        } else {
            fileViewer.innerHTML = `
                <p style="font-size: 1.1rem; color: #666;">Preview not available for this file type</p>
                <div class="file-info-box">
                    <p><span class="file-info-label">File Name:</span> ${doc.file_name}</p>
                    <p><span class="file-info-label">Size:</span> ${formatFileSize(doc.file_size)}</p>
                    <p><span class="file-info-label">Uploaded:</span> ${new Date(doc.uploaded_at).toLocaleString()}</p>
                    <p style="margin-top: 15px;"><button class="btn-download" onclick="downloadDoc(${id})" style="padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-download"></i> Download File</button></p>
                </div>
            `;
        }
        
        fileModal.classList.add('show');
    }

    function closeModal() {
        const fileModal = document.getElementById('fileModal');
        fileModal.classList.remove('show');
    }

    // Close modal when clicking backdrop
    document.getElementById('fileModal').addEventListener('click', (e) => {
        if (e.target.id === 'fileModal') {
            closeModal();
        }
    });

    function confirmDelete(id, title) {
        if (confirm(`Are you sure you want to delete "${title}"? This cannot be undone.`)) {
            deleteDoc(id);
        }
    }

    function deleteDoc(id) {
        const formData = new FormData();
        formData.append('id', id);

        fetch(`<?php echo APP_URL; ?>/admin/api/delete-document.php`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Document deleted successfully!', 'success');
                fetchDocuments();
            } else {
                showToast(data.error || 'Delete failed', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            showToast('Delete failed', 'error');
        });
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
        toast.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // Load documents on page load
    document.addEventListener('DOMContentLoaded', fetchDocuments);

</script>

</body>
</html>
