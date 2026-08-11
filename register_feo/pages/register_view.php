<?php
/**
 * Просмотр реестра
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/../includes/init.php';
requireAuth();

$user = getCurrentUser();
$role = getCurrentUserRole();
$pdo = getDbConnection();
$registerId = (int)($_GET['id'] ?? 0);

if (!$registerId) {
    showErrorPage(404, 'Реестр не найден');
}

$stmt = $pdo->prepare("SELECT r.*, u.full_name as author_name FROM registers r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$registerId]);
$register = $stmt->fetch();

if (!$register) {
    showErrorPage(404, 'Реестр не найден');
}

$isAuthor = $register['user_id'] == $user['id'];
$canView = $isAuthor || $role === 'economist' || $role === 'admin';

if (!$canView) {
    showErrorPage(403, 'Доступ запрещён');
}

$stmt = $pdo->prepare("SELECT * FROM register_items WHERE register_id = ? ORDER BY item_order");
$stmt->execute([$registerId]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT c.*, u.full_name FROM register_comments c JOIN users u ON c.user_id = u.id WHERE c.register_id = ? ORDER BY c.created_at ASC");
$stmt->execute([$registerId]);
$comments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM register_files WHERE register_id = ? ORDER BY uploaded_at");
$stmt->execute([$registerId]);
$files = $stmt->fetchAll();

$pageTitle = 'Реестр #' . $register['id'];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-text"></i> Реестр <?= e($register['register_number'] ?? '#' . $register['id']) ?></h2>
    <div class="btn-group">
        <?php if ($register['status'] === 'draft' && $isAuthor): ?>
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="send">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Отправить</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($register['status'], ['new', 'in_review']) && $isAuthor): ?>
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="recall">
                <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-return-left"></i> Отозвать</button>
            </form>
        <?php endif; ?>
        <a href="print_register.php?id=<?= $registerId ?>" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Печать</a>
        <a href="dashboard.php" class="btn btn-outline-secondary">Назад</a>
    </div>
</div>

<div class="alert alert-<?= getStatusClass($register['status']) ?>">
    <strong>Статус:</strong> <?= getStatusText($register['status']) ?>
    <?php if ($register['register_number']): ?>| <strong>Номер:</strong> <?= e($register['register_number']) ?><?php endif; ?>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">Информация</div>
            <div class="card-body">
                <p><strong>Передающий:</strong> <?= e($register['transmitted_name']) ?></p>
                <p><strong>Должность:</strong> <?= e($register['transmitted_position'] ?? '-') ?></p>
                <p><strong>Автор:</strong> <?= e($register['author_name']) ?></p>
                <p><strong>Дата создания:</strong> <?= formatDateTime($register['created_at']) ?></p>
                <?php if ($register['accepted_at']): ?>
                    <p><strong>Принят:</strong> <?= formatDateTime($register['accepted_at']) ?></p>
                    <p><strong>Принявший:</strong> <?= e($register['accepted_name']) ?></p>
                    <p><strong>Дата поступления в ФЭО:</strong> <?= formatDate($register['receive_date_feo']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Документы</div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th style="width:50px">№</th><th>Наименование</th><th style="width:150px">Номер</th><th style="width:120px">Дата</th><th>Договор/контракт</th><th>Примечание</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr><td><?= $item['item_order'] ?></td><td><?= e($item['doc_name']) ?></td><td><?= e($item['doc_number']) ?></td><td><?= formatDate($item['doc_date']) ?></td><td><?= e($item['contract_details'] ?? '-') ?></td><td><?= e($item['notes'] ?? '') ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($comments): ?>
<div class="card mb-3">
    <div class="card-header">Комментарии</div>
    <div class="card-body">
        <?php foreach ($comments as $comment): ?>
            <div class="border-bottom pb-2 mb-2">
                <strong><?= e($comment['full_name']) ?></strong> <small class="text-muted"><?= formatDateTime($comment['created_at']) ?></small>
                <p class="mb-0 mt-1"><?= nl2br(e($comment['comment'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
include __DIR__ . '/../templates/footer.php';
