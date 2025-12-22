<?php
$dbPath = 'auto_db.db';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получаем активных мастеров
    $stmt = $pdo->query("
        SELECT employee_id, 
               first_name || ' ' || last_name AS fio 
        FROM employee 
        WHERE role = 'master' AND is_active = 1 
        ORDER BY employee_id
    ");
    $masters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Доступные мастера:\n";
    foreach ($masters as $m) {
        printf("%d. %s\n", $m['employee_id'], trim($m['fio']));
    }
    echo "\nВведите номер мастера (Enter для всех): ";
    $input = trim(fgets(STDIN));
    
    // Валидация
    $masterFilter = null;
    if ($input !== '') {
        $masterId = filter_var($input, FILTER_VALIDATE_INT);
        if ($masterId === false || $masterId <= 0) {
            die("❌ Неверный номер мастера!\n");
        }
        // Проверяем существование
        $checkStmt = $pdo->prepare("SELECT employee_id FROM employee WHERE employee_id = ? AND role = 'master' AND is_active = 1");
        $checkStmt->execute([$masterId]);
        if (!$checkStmt->fetch()) {
            die("❌ Мастер #$masterId не найден или неактивен!\n");
        }
        $masterFilter = $masterId;
    }

    // Запрос услуг
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
    if ($masterFilter !== null) {
        $sql .= " AND wr.master_id = ?";
        $params[] = $masterFilter;
    }
    $sql .= " ORDER BY e.last_name, e.first_name, wr.start_time";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($works)) {
        echo "Услуги не найдены.\n";
        exit(0);
    }
    
    // Псевдографика
    $headers = ['Мастер №', 'ФИО', 'Дата', 'Услуга', 'Стоимость'];

    // ширины колонок (чуть с запасом)
    $widths = [10, 25, 12, 30, 12];

    // рамка
    $border = '+';
    foreach ($widths as $w) {
        $border .= str_repeat('-', $w + 2) . '+';
    }
    $border .= PHP_EOL;

    // заголовок
    echo PHP_EOL . $border;
    printf(
        "| %-10s | %-25s | %-12s | %-30s | %12s |\n",
        ...$headers
    );
    echo $border;

    // строки
    foreach ($works as $row) {
        printf(
            "| %10s | %-25s | %-12s | %-30s | %12s |\n",
            $row['master_num'],
            trim($row['fio']),
            $row['work_date'],
            $row['service_name'],
            number_format($row['cost'], 2, ',', ' ')
        );
    }

    echo $border;
    
} catch (PDOException $e) {
    die("❌ Ошибка БД: " . $e->getMessage() . "\n");
}
?>
