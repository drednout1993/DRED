<?php
/**
 * Создание нового реестра
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/../includes/init.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDbConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $transmittedName = trim($_POST['transmitted_name'] ?? '');
        $transmittedPosition = trim($_POST['transmitted_position'] ?? '');
        $docNames = $_POST['doc_name'] ?? [];
        $docNumbers = $_POST['doc_number'] ?? [];
        $docDates = $_POST['doc_date'] ?? [];
        $contractDetails = $_POST['contract_details'] ?? [];
        $notes = $_POST['notes'] ?? [];
        
        if (empty($transmittedName)) {
            $errors[] = 'Укажите передающего';
        }
        if (empty($docNames) || count(array_filter($docNames)) === 0) {
            $errors[] = 'Добавьте хотя бы один документ';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Создание реестра
                $stmt = $pdo->prepare("
                    INSERT INTO registers (user_id, status, transmitted_name, transmitted_position, created_at)
                    VALUES (?, 'draft', ?, ?, NOW())
                ");
                $stmt->execute([$user['id'], $transmittedName, $transmittedPosition]);
                $registerId = $pdo->lastInsertId();
                
                // Добавление позиций
                $stmt = $pdo->prepare("
                    INSERT INTO register_items (register_id, item_order, doc_name, doc_number, doc_date, contract_details, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($docNames as $i => $docName) {
                    if (empty($docName)) continue;
                    $stmt->execute([
                        $registerId,
                        $i + 1,
                        $docName,
                        $docNumbers[$i] ?? '',
                        $docDates[$i] ?? date('Y-m-d'),
                        $contractDetails[$i] ?? '',
                        $notes[$i] ?? ''
                    ]);
                }
                
                $pdo->commit();
                header('Location: register_view.php?id=' . $registerId);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Создание реестра';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-plus-circle"></i> Новый реестр</h2>
    <a href="dashboard.php" class="btn btn-outline-secondary">Отмена</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <?= csrfField() ?>
    
    <div class="card mb-4">
        <div class="card-header">Информация о передаче</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Передающий (ФИО) *</label>
                    <input type="text" class="form-control" name="transmitted_name" 
                           value="<?= e($user['full_name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Должность передающего</label>
                    <input type="text" class="form-control" name="transmitted_position" 
                           value="<?= e($user['position'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Документы</span>
            <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                <i class="bi bi-plus"></i> Добавить строку
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0" id="registerItems">
                <thead>
                    <tr>
                        <th style="width: 50px;">№</th>
                        <th>Наименование документа *</th>
                        <th style="width: 150px;">Номер *</th>
                        <th style="width: 150px;">Дата *</th>
                        <th>Договор/контракт</th>
                        <th style="width: 200px;">Примечание</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-save"></i> Сохранить черновик
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initRegisterItemsTable();
    // Добавим одну пустую строку
    addRegisterItemRow(document.querySelector('#registerItems tbody'));
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
include __DIR__ . '/../templates/footer.php';
