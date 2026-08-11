<?php
/**
 * Список всех реестров с фильтрами
 */
require_once __DIR__ . '/../includes/init.php';
requireAuth();

$userId = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Фильтры
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$showAll = ($role === 'economist' || $role === 'admin') && isset($_GET['all']);

// Построение запроса
$where = [];
$params = [];

if (!$showAll) {
    $where[] = 'r.user_id = ?';
    $params[] = $userId;
}

if ($statusFilter) {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $where[] = '(r.register_number LIKE ? OR u.full_name LIKE ?)';
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT r.*, u.full_name as author_name, 
               (SELECT COUNT(*) FROM register_items WHERE register_id = r.id) as items_count
        FROM registers r
        JOIN users u ON r.user_id = u.id
        $whereSql
        ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registers = $stmt->fetchAll();

$pageTitle = 'Реестры';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-ul"></i> Реестры</h2>
                <?php if ($role !== 'user' || $showAll): ?>
                    <a href="?all=<?= $showAll ? '0' : '1' ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-toggle-on"></i> <?= $showAll ? 'Только мои' : 'Все реестры' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-select">
                        <option value="">Все статусы</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Черновик</option>
                        <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>На проверке</option>
                        <option value="in_review" <?= $statusFilter === 'in_review' ? 'selected' : '' ?>>На рассмотрении</option>
                        <option value="revision" <?= $statusFilter === 'revision' ? 'selected' : '' ?>>На доработке</option>
                        <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Принят</option>
                        <option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>Удалён</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" placeholder="Номер или ФИО" value="<?= e($searchQuery) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Найти
                    </button>
                    <a href="register_list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Сброс
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица реестров -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($registers)): ?>
                <p class="text-muted text-center py-4">Реестры не найдены</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Автор</th>
                                <th>Дата создания</th>
                                <th>Позиций</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registers as $reg): ?>
                                <tr>
                                    <td><?= e($reg['register_number'] ?? '-') ?></td>
                                    <td><?= e($reg['author_name']) ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($reg['created_at'])) ?></td>
                                    <td><?= $reg['items_count'] ?></td>
                                    <td><?= getStatusBadge($reg['status']) ?></td>
                                    <td>
                                        <a href="register_view.php?id=<?= $reg['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
