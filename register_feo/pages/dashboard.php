<?php
/**
 * Дашборд - главная страница после входа
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/../includes/init.php';
requireAuth();

$user = getCurrentUser();
$role = getCurrentUserRole();
$pdo = getDbConnection();

// Получение статистики
$statusCounts = [];
try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM registers WHERE user_id = ? GROUP BY status");
    $stmt->execute([$user['id']]);
    $statusCounts = array_column($stmt->fetchAll(), 'count', 'status');
} catch (Exception $e) {
    // Игнорируем ошибки
}

// Для экономиста - все реестры на проверке
$pendingReviewCount = 0;
if ($role === 'economist' || $role === 'admin') {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM registers WHERE status IN ('new', 'in_review')");
    $pendingReviewCount = $stmt->fetch()['count'];
}

// Последние реестры
$limit = $role === 'economist' || $role === 'admin' ? 50 : 20;
$query = "
    SELECT r.*, u.full_name as author_name 
    FROM registers r 
    JOIN users u ON r.user_id = u.id
";

if ($role !== 'economist' && $role !== 'admin') {
    $query .= " WHERE r.user_id = ?";
}

$query .= " ORDER BY r.created_at DESC LIMIT ?";

$stmt = $pdo->prepare($query);
if ($role !== 'economist' && $role !== 'admin') {
    $stmt->execute([$user['id'], $limit]);
} else {
    $stmt->execute([$limit]);
}
$registers = $stmt->fetchAll();

$pageTitle = 'Дашборд';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Дашборд</h2>
    <a href="register_create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Создать реестр
    </a>
</div>

<!-- Карточки статусов -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-sm-4">
        <div class="card status-card bg-secondary text-white">
            <div class="card-body text-center">
                <div class="card-icon"><i class="bi bi-file-earmark"></i></div>
                <h3><?= $statusCounts['draft'] ?? 0 ?></h3>
                <small>Черновики</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card status-card bg-info text-dark">
            <div class="card-body text-center">
                <div class="card-icon"><i class="bi bi-send"></i></div>
                <h3><?= $statusCounts['new'] ?? 0 ?></h3>
                <small>Отправлены</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card status-card bg-warning text-dark">
            <div class="card-body text-center">
                <div class="card-icon"><i class="bi bi-eye"></i></div>
                <h3><?= $statusCounts['in_review'] ?? 0 ?></h3>
                <small>На проверке</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card status-card bg-orange text-white" style="background-color: #fd7e14 !important;">
            <div class="card-body text-center">
                <div class="card-icon"><i class="bi bi-arrow-return-left"></i></div>
                <h3><?= $statusCounts['revision'] ?? 0 ?></h3>
                <small>На доработке</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card status-card bg-success text-white">
            <div class="card-body text-center">
                <div class="card-icon"><i class="bi bi-check-circle"></i></div>
                <h3><?= $statusCounts['accepted'] ?? 0 ?></h3>
                <small>Приняты</small>
            </div>
        </div>
    </div>
    <?php if ($role === 'economist' || $role === 'admin'): ?>
    <div class="col-md-2 col-sm-4">
        <div class="card status-card bg-primary text-white">
            <div class="card-body text-center">
                <div class="card-icon"><i class="bi bi-inbox"></i></div>
                <h3><?= $pendingReviewCount ?></h3>
                <small>На проверку</small>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Таблица последних реестров -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul"></i> Последние реестры</span>
        <a href="register_list.php" class="btn btn-sm btn-outline-primary">Все реестры</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Статус</th>
                        <th>Автор</th>
                        <th>Передающий</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registers as $reg): ?>
                    <tr>
                        <td>
                            <?php if ($reg['register_number']): ?>
                                <strong><?= e($reg['register_number']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted">#<?= $reg['id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= getStatusClass($reg['status']) ?>">
                                <?= getStatusText($reg['status']) ?>
                            </span>
                        </td>
                        <td><?= e($reg['author_name']) ?></td>
                        <td><?= e($reg['transmitted_name'] ?? '-') ?></td>
                        <td><?= formatDate($reg['created_at']) ?></td>
                        <td>
                            <a href="register_view.php?id=<?= $reg['id'] ?>" class="btn btn-sm btn-outline-primary" title="Просмотр">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($registers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">Реестры отсутствуют</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
include __DIR__ . '/../templates/footer.php';
