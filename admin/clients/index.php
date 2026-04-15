<?php
/**
 * CLIENTS LIST PAGE
 * Card grid view with search and filter
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Clients';

global $db;

// Get total clients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM clients");
$stmt->execute();
$totalClients = $stmt->fetch()['count'];

// Get clients with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("
    SELECT c.*, u.name as assigned_user FROM clients c
    LEFT JOIN users u ON c.assigned_user_id = u.id
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$clients = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .page-header h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        color: #1D1D1D;
    }

    .client-badge {
        background: rgba(100, 24, 195, 0.1);
        color: #6418C3;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-left: 10px;
    }

    .search-section {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
    }

    .search-section .search-box {
        flex: 1;
        position: relative;
    }

    .search-section input {
        width: 100%;
        padding: 12px 15px;
        padding-left: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.95rem;
    }

    .search-section input:focus {
        outline: none;
        border-color: #6418C3;
        box-shadow: 0 0 0 3px rgba(100,24,195,0.15);
    }

    .search-icon {
        position: absolute;
        left: 15px;
        top: 12px;
        color: #999;
    }

    .btn-add {
        background: #6418C3;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-add:hover {
        background: #5910b8;
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(100,24,195,0.3);
        color: white;
    }

    .view-toggle {
        display: flex;
        gap: 10px;
        margin-left: 10px;
    }

    .view-toggle button {
        padding: 8px 12px;
        border: 1px solid #e0e0e0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .view-toggle button.active {
        background: #6418C3;
        color: white;
        border-color: #6418C3;
    }

    .clients-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .client-card {
        background: white;
        border-radius: 12px;
        card-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 24px;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid #f0f0f0;
    }

    .client-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        border-color: #6418C3;
    }

    .client-card-header {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .client-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6418C3, #9B59B6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .client-info {
        flex: 1;
    }

    .client-name {
        font-weight: 600;
        font-size: 1rem;
        color: #1D1D1D;
        margin: 0 0 3px 0;
    }

    .client-company {
        font-size: 0.85rem;
        color: #888;
        margin: 0 0 5px 0;
    }

    .client-username {
        font-size: 0.8rem;
        color: #aaa;
        margin: 0;
    }

    .client-card-divider {
        border-top: 1px solid #f0f0f0;
        margin: 12px 0;
    }

    .client-detail-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: #555;
    }

    .client-detail-row i {
        width: 16px;
        color: #6418C3;
    }

    .client-detail-row a {
        color: #6418C3;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .client-detail-row a:hover {
        text-decoration: underline;
    }

    .client-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 12px;
        border-top: 1px solid #f0f0f0;
    }

    .client-stats {
        display: flex;
        gap: 15px;
        font-size: 0.85rem;
    }

    .stat {
        text-align: center;
    }

    .stat-number {
        font-weight: 700;
        color: #6418C3;
        display: block;
        font-size: 1.1rem;
    }

    .stat-label {
        color: #999;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .client-actions {
        display: flex;
        gap: 5px;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }

    .action-btn.view {
        background: rgba(100, 24, 195, 0.15);
        color: #6418C3;
    }

    .action-btn.view:hover {
        background: #6418C3;
        color: white;
    }

    .action-btn.edit {
        background: rgba(30, 170, 231, 0.15);
        color: #1EAAE7;
    }

    .action-btn.edit:hover {
        background: #1EAAE7;
        color: white;
    }

    .action-btn.delete {
        background: rgba(255, 94, 94, 0.15);
        color: #FF5E5E;
    }

    .action-btn.delete:hover {
        background: #FF5E5E;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.3rem;
        color: #555;
        margin-bottom: 10px;
    }

    .empty-state p {
        margin-bottom: 20px;
    }

    .pagination {
        margin-top: 30px;
    }
</style>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1>Clients <span class="client-badge"><?php echo $totalClients; ?></span></h1>
    </div>
    <div style="display: flex; gap: 15px; align-items: center;">
        <div class="view-toggle">
            <button class="active" data-view="grid">
                <i class="fas fa-th"></i> Grid
            </button>
            <button data-view="table">
                <i class="fas fa-th-list"></i> Table
            </button>
        </div>
        <a href="<?php echo APP_URL; ?>/admin/clients/add.php" class="btn-add">
            <i class="fas fa-plus"></i> Add New Client
        </a>
    </div>
</div>

<!-- SEARCH SECTION -->
<div class="search-section">
    <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search clients by name, email, or phone...">
    </div>
</div>

<!-- CLIENTS GRID -->
<div id="clientsGrid" class="clients-grid">
    <?php if (count($clients) > 0): ?>
        <?php foreach ($clients as $client): 
            $initials = getInitials($client['client_name']);
        ?>
        <div class="client-card">
            <div class="client-card-header">
                <div class="client-avatar"><?php echo $initials; ?></div>
                <div class="client-info">
                    <p class="client-name"><?php echo clean($client['client_name']); ?></p>
                    <p class="client-company"><?php echo clean($client['company_name'] ?? 'N/A'); ?></p>
                    <p class="client-username">@<?php echo strtolower(str_replace(' ', '.', $client['client_name'])); ?></p>
                </div>
            </div>

            <div class="client-card-divider"></div>

            <div class="client-detail-row">
                <i class="fas fa-phone"></i>
                <a href="tel:<?php echo $client['primary_phone']; ?>"><?php echo clean($client['primary_phone']); ?></a>
            </div>

            <div class="client-detail-row">
                <i class="fas fa-envelope"></i>
                <a href="mailto:<?php echo sanitizeEmail($client['email']); ?>"><?php echo clean($client['email']); ?></a>
            </div>

            <div class="client-detail-row">
                <i class="fas fa-globe"></i>
                <span><?php echo clean($client['country'] ?? 'N/A'); ?></span>
            </div>

            <div class="client-card-divider"></div>

            <div class="client-card-footer">
                <div class="client-stats">
                    <?php
                    // Get client projects count
                    $stmt2 = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE client_id = :id");
                    $stmt2->execute([':id' => $client['id']]);
                    $projectCount = $stmt2->fetch()['count'];

                    // Get client invoices count
                    $stmt3 = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = :id");
                    $stmt3->execute([':id' => $client['id']]);
                    $invoiceCount = $stmt3->fetch()['count'];
                    
                    // Status
                    $statusClass = $client['client_status'] === 'active' ? 'success' : ($client['client_status'] === 'inactive' ? 'secondary' : 'warning');
                    ?>
                    <div class="stat">
                        <span class="stat-number"><?php echo $projectCount; ?></span>
                        <span class="stat-label">Projects</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number"><?php echo $invoiceCount; ?></span>
                        <span class="stat-label">Invoices</span>
                    </div>
                    <div class="stat">
                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($client['client_status']); ?> ✓</span>
                    </div>
                </div>

                <div class="client-actions">
                    <a href="<?php echo APP_URL; ?>/admin/clients/profile.php?id=<?php echo $client['id']; ?>" class="action-btn view" title="View Profile">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/clients/add.php?id=<?php echo $client['id']; ?>" class="action-btn edit" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="action-btn delete" title="Delete" onclick="confirmDelete('Delete Client?', 'Are you sure? All related data will be removed.', '<?php echo APP_URL; ?>/admin/clients/delete.php?id=<?php echo $client['id']; ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state" style="grid-column: 1 / -1;">
            <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
            <h3>No Clients Yet</h3>
            <p>Get started by adding your first client</p>
            <a href="<?php echo APP_URL; ?>/admin/clients/add.php" class="btn-add">
                <i class="fas fa-plus"></i> Add First Client
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- PAGINATION -->
<?php if ($totalClients > $limit): ?>
<nav class="pagination">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= ceil($totalClients / $limit); $i++): ?>
        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
