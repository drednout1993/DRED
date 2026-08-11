<?php
/**
 * Экспорт журнала в CSV (Excel)
 */
require_once __DIR__ . '/includes/init.php';
requireAuth();
checkRole(['economist', 'admin']);

// Получение всех принятых реестров
$sql = "SELECT r.register_number, r.receive_date_feo, r.transmitted_name, r.transmitted_position,
               r.accepted_name, r.accepted_position,
               (SELECT COUNT(*) FROM register_items WHERE register_id = r.id) as items_count
        FROM registers r
        WHERE r.status = 'accepted'
        ORDER BY r.accepted_at DESC";

$stmt = $pdo->query($sql);
$registers = $stmt->fetchAll();

// Установка заголовков для скачивания
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="journal_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM для корректного отображения кириллицы в Excel
echo "\xEF\xBB\xBF";

// Заголовки столбцов
$output = fopen('php://output', 'w');
fputcsv($output, ['№ реестра', 'Дата поступления в ФЭО', 'Передал (ФИО)', 'Передал (должность)', 
                  'Принял (ФИО)', 'Принял (должность)', 'Количество позиций'], ';');

// Данные
foreach ($registers as $reg) {
    fputcsv($output, [
        $reg['register_number'],
        $reg['receive_date_feo'],
        $reg['transmitted_name'],
        $reg['transmitted_position'],
        $reg['accepted_name'],
        $reg['accepted_position'],
        $reg['items_count']
    ], ';');
}

fclose($output);
exit;
