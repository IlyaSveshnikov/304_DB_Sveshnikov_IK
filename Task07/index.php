<?php
// index.php
$dbPath = 'auto_db.db';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем список активных мастеров для выпадающего списка
    $stmt = $pdo->query("
        SELECT employee_id,
               first_name || ' ' || last_name AS fio
        FROM employee
        WHERE role = 'master' AND is_active = 1
        ORDER BY employee_id
    ");
    $masters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Текущий фильтр по мастеру
    $masterFilter = isset($_GET['master']) ? (int)$_GET['master'] : 0;

    // Основной запрос: все выполненные работы
    $sql = "
        SELECT 
            wr.master_id AS master_num,
            e.first_name || ' ' || e.last_name AS fio,
            date(wr.start_time) AS work_date,
            s.name AS service_name,
            wr.price_cents / 100.0 AS cost
        FROM work_record wr
        JOIN employee e ON wr.master_id = e.employee_id
        JOIN service s ON wr.service_id = s.service_id
        WHERE e.role = 'master' AND e.is_active = 1
    ";
    $params = [];
    if ($masterFilter > 0) {
        $sql .= " AND wr.master_id = ?";
        $params[] = $masterFilter;
    }
    $sql .= " ORDER BY e.last_name, e.first_name, wr.start_time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Ошибка БД: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Услуги мастеров</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background-color: #f5f5f5; }
        select, button { padding: 6px 10px; font-size: 14px; }
        .filter-form { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Список оказанных услуг мастеров</h1>

    <form method="get" class="filter-form">
        <label>
            Мастер:
            <select name="master">
                <option value="0" <?= $masterFilter === 0 ? 'selected' : '' ?>>Все мастера</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= $master['employee_id'] ?>"
                        <?= $masterFilter === (int)$master['employee_id'] ? 'selected' : '' ?>>
                        <?= $master['employee_id'] ?>. <?= htmlspecialchars($master['fio']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </label>
        <button type="submit">Фильтровать</button>
    </form>

    <?php if (isset($error)): ?>
        <p style="color: red; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
    <?php elseif (empty($works)): ?>
        <p>Услуги не найдены.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>№ мастера</th>
                    <th>ФИО</th>
                    <th>Дата работы</th>
                    <th>Услуга</th>
                    <th>Стоимость, ₽</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($works as $work): ?>
                    <tr>
                        <td><?= (int)$work['master_num'] ?></td>
                        <td><?= htmlspecialchars($work['fio']) ?></td>
                        <td><?= htmlspecialchars($work['work_date']) ?></td>
                        <td><?= htmlspecialchars($work['service_name']) ?></td>
                        <td><?= number_format($work['cost'], 2, ',', ' ') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</body>
</html>
