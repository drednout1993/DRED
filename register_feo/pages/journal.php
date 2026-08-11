<?php
/**
 * Журнал принятых реестров с пагинацией, экспортом и печатью
 */
require_once __DIR__ . '/../includes/init.php';
requireAuth();

checkRole(['economist', 'admin']);

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Фильтры
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$where = ['r.status = ?'];
$params = ['accepted'];

if ($dateFrom) {
    $where[] = 'r.receive_date_feo >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'r.receive_date_feo <= ?';
    $params[] = $dateTo;
}
if ($searchQuery) {
    $where[] = '(r.register_number LIKE ? OR r.transmitted_name LIKE ?)';
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereSql = implode(' AND ', $where);

// Получение общего количества
$countSql = "SELECT COUNT(*) FROM registers r WHERE $whereSql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Получение записей
$sql = "SELECT r.*, u.full_name as author_name 
        FROM registers r
        JOIN users u ON r.user_id = u.id
        WHERE $whereSql
        ORDER BY r.accepted_at DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registers = $stmt->fetchAll();

$pageTitle = 'Журнал принятых реестров';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-journal-text"></i> Журнал принятых реестров</h2>
                <div>
                    <a href="export.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Экспорт в Excel
                    </a>
                    <a href="print_journal.php" target="_blank" class="btn btn-outline-dark">
                        <i class="bi bi-printer"></i> Печать
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">С даты</label>
                    <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">По дату</label>
                    <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text" name="search" class="form-control" placeholder="Номер или наименование" value="<?= e($searchQuery) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Найти
                    </button>
                    <a href="journal.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Сброс
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($registers)): ?>
                <p class="text-muted text-center py-4">Записей не найдено</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>№ реестра</th>
                                <th>Дата поступления в ФЭО</th>
                                <th>Передал (ФИО)</th>
                                <th>Принял (ФИО)</th>
                                <th>Позиций</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registers as $reg): ?>
                                <tr>
                                    <td><strong><?= e($reg['register_number']) ?></strong></td>
                                    <td><?= $reg['receive_date_feo'] ? date('d.m.Y', strtotime($reg['receive_date_feo'])) : '-' ?></td>
                                    <td><?= e($reg['transmitted_name']) ?></td>
                                    <td><?= e($reg['accepted_name']) ?></td>
                                    <td>
                                        <?php
                                        $stmtItems = $pdo->prepare("SELECT COUNT(*) FROM register_items WHERE register_id = ?");
                                        $stmtItems->execute([$reg['id']]);
                                        echo $stmtItems->fetchColumn();
                                        ?>
                                    </td>
                                    <td>
                                        <a href="register_view.php?id=<?= $reg['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Просмотр
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Пагинация -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Пагинация">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&search=<?= e(urlencode($searchQuery)) ?>">Назад</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&search=<?= e(urlencode($searchQuery)) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&search=<?= e(urlencode($searchQuery)) ?>">Вперед</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
