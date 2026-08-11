<?php
/**
 * Статистика: круговая диаграмма и график по месяцам
 */
require_once __DIR__ . '/../includes/init.php';
requireAuth();
checkRole(['economist', 'admin']);

// Данные для круговой диаграммы (по статусам)
$statusSql = "SELECT status, COUNT(*) as count FROM registers WHERE status != 'deleted' GROUP BY status";
$statusStmt = $pdo->query($statusSql);
$statusData = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$statusLabels = [
    'draft' => 'Черновики',
    'new' => 'На проверке',
    'in_review' => 'На рассмотрении',
    'revision' => 'На доработке',
    'accepted' => 'Приняты'
];

// Данные для графика по месяцам (принятые за последний год)
$monthsSql = "SELECT DATE_FORMAT(accepted_at, '%Y-%m') as month, COUNT(*) as count 
              FROM registers 
              WHERE status = 'accepted' AND accepted_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
              GROUP BY month 
              ORDER BY month";
$monthsStmt = $pdo->query($monthsSql);
$monthsData = $monthsStmt->fetchAll();

$pageTitle = 'Статистика';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
?>

<div class="container-fluid py-4">
    <h2><i class="bi bi-graph-up"></i> Статистика</h2>
    
    <div class="row mt-4">
        <!-- Круговая диаграмма -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Реестры по статусам</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 400px;">
                    <?php
                    $total = array_sum($statusData);
                    if ($total > 0):
                        $startAngle = 0;
                        $colors = ['#6c757d', '#0d6efd', '#0dcaf0', '#ffc107', '#198754'];
                        $svgSize = 300;
                        $radius = 120;
                        $center = $svgSize / 2;
                    ?>
                    <svg width="<?= $svgSize ?>" height="<?= $svgSize ?>" viewBox="0 0 <?= $svgSize ?> <?= $svgSize ?>">
                        <?php
                        $legendY = 20;
                        foreach ($statusData as $status => $count):
                            if ($count == 0) continue;
                            $percentage = $count / $total;
                            $angle = $percentage * 360;
                            $endAngle = $startAngle + $angle;
                            
                            // Конвертация в радианы
                            $startRad = deg2rad($startAngle - 90);
                            $endRad = deg2rad($endAngle - 90);
                            
                            // Координаты
                            $x1 = $center + $radius * cos($startRad);
                            $y1 = $center + $radius * sin($startRad);
                            $x2 = $center + $radius * cos($endRad);
                            $y2 = $center + $radius * sin($endRad);
                            
                            // Флаг для больших сегментов
                            $largeArc = $angle > 180 ? 1 : 0;
                            
                            $colorIndex = array_search($status, array_keys($statusData)) % count($colors);
                            $color = $colors[$colorIndex];
                            
                            echo "<path d='M $center $center L $x1 $y1 A $radius $radius 0 $largeArc 1 $x2 $y2 Z' fill='$color' stroke='white' stroke-width='2'>";
                            echo "<title>" . ($statusLabels[$status] ?? $status) . ": $count (" . round($percentage * 100) . "%)</title>";
                            echo "</path>";
                            
                            $startAngle = $endAngle;
                        endforeach;
                        ?>
                    </svg>
                    
                    <div class="ms-4">
                        <h6>Легенда:</h6>
                        <?php
                        $colorIndex = 0;
                        foreach ($statusData as $status => $count):
                            if ($count == 0) continue;
                            $color = $colors[$colorIndex % count($colors)];
                        ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge me-2" style="background-color: <?= $color ?>; width: 20px; height: 20px;"></span>
                                <span><?= $statusLabels[$status] ?? $status ?>: <strong><?= $count ?></strong></span>
                            </div>
                        <?php 
                            $colorIndex++;
                        endforeach;
                        ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Нет данных для отображения</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- График по месяцам -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Принятые реестры по месяцам</h5>
                </div>
                <div class="card-body" style="min-height: 400px;">
                    <?php if (!empty($monthsData)): ?>
                    <div class="d-flex align-items-end justify-content-around" style="height: 350px; border-left: 2px solid #dee2e6; border-bottom: 2px solid #dee2e6; padding: 10px;">
                        <?php
                        $maxCount = max(array_column($monthsData, 'count'));
                        $barWidth = 40;
                        $chartHeight = 300;
                        foreach ($monthsData as $item):
                            $barHeight = ($item['count'] / $maxCount) * $chartHeight;
                            $monthLabel = date('M Y', strtotime($item['month'] . '-01'));
                        ?>
                        <div class="text-center" style="width: <?= $barWidth ?>px;">
                            <div style="height: <?= $barHeight ?>px; background: linear-gradient(to top, #0d6efd, #0dcaf0); border-radius: 4px 4px 0 0; position: relative;" 
                                 title="<?= $item['count'] ?> реестров">
                                <span style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 12px;"><?= $item['count'] ?></span>
                            </div>
                            <div style="font-size: 10px; margin-top: 5px; transform: rotate(-45deg); transform-origin: top center;"><?= $monthLabel ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-5">Нет данных за последний год</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
