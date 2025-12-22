<?php

// ---------- Подключение к БД ----------
$dbPath = __DIR__ . '/../data/auto_db.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

// ---------- Параметры запроса ----------
$action = $_GET['action'] ?? 'masters';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- ОБРАБОТКА POST (CRUD) ----------

// Сохранение мастера (добавление / редактирование)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'master_save') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $firstName  = trim($_POST['first_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $role       = 'master';

    if ($employeeId > 0) {
        $stmt = $pdo->prepare("
            UPDATE employee
            SET first_name = :first_name,
                last_name  = :last_name,
                phone      = :phone
            WHERE employee_id = :id
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':phone'      => $phone,
            ':id'         => $employeeId,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO employee (first_name, last_name, role, phone, is_active)
            VALUES (:first_name, :last_name, :role, :phone, 1)
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':role'       => $role,
            ':phone'      => $phone,
        ]);
    }

    header('Location: index.php?action=masters');
    exit;
}

// Удаление мастера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'master_delete') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if ($employeeId > 0) {
        $stmt = $pdo->prepare("DELETE FROM employee WHERE employee_id = :id");
        $stmt->execute([':id' => $employeeId]);
    }
    header('Location: index.php?action=masters');
    exit;
}

// Сохранение записи графика
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'schedule_save') {
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);
    $masterId   = (int)($_POST['master_id'] ?? 0);
    $workDate   = trim($_POST['work_date'] ?? '');
    $startTime  = trim($_POST['start_time'] ?? '');
    $endTime    = trim($_POST['end_time'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($masterId > 0 && $workDate !== '' && $startTime !== '' && $endTime !== '') {
        if ($scheduleId > 0) {
            $stmt = $pdo->prepare("
                UPDATE master_schedule
                SET work_date  = :work_date,
                    start_time = :start_time,
                    end_time   = :end_time,
                    notes      = :notes
                WHERE schedule_id = :id
            ");
            $stmt->execute([
                ':work_date'  => $workDate,
                ':start_time' => $startTime,
                ':end_time'   => $endTime,
                ':notes'      => $notes,
                ':id'         => $scheduleId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO master_schedule (master_id, work_date, start_time, end_time, notes)
                VALUES (:master_id, :work_date, :start_time, :end_time, :notes)
            ");
            $stmt->execute([
                ':master_id'  => $masterId,
                ':work_date'  => $workDate,
                ':start_time' => $startTime,
                ':end_time'   => $endTime,
                ':notes'      => $notes,
            ]);
        }
    }

    header('Location: index.php?action=master_schedule&id=' . $masterId);
    exit;
}

// Удаление записи графика
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'schedule_delete') {
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);
    $masterId   = (int)($_POST['master_id'] ?? 0);

    if ($scheduleId > 0) {
        $stmt = $pdo->prepare("DELETE FROM master_schedule WHERE schedule_id = :id");
        $stmt->execute([':id' => $scheduleId]);
    }

    header('Location: index.php?action=master_schedule&id=' . $masterId);
    exit;
}

// ---------- ВЫБОР ЭКРАНА (SELECT) ----------

if ($action === 'masters') {

    // список мастеров
    $stmt = $pdo->query("
        SELECT employee_id,
               first_name || ' ' || last_name AS fio,
               phone,
               is_active
        FROM employee
        WHERE role = 'master'
        ORDER BY last_name, first_name
    ");
    $masters = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($action === 'master_form') {

    // форма добавления / редактирования мастера
    $master = [
        'employee_id' => 0,
        'first_name'  => '',
        'last_name'   => '',
        'phone'       => '',
    ];
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT employee_id, first_name, last_name, phone
            FROM employee
            WHERE employee_id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $master = $row;
        }
    }

} elseif ($action === 'master_delete') {

    // подтверждение удаления мастера
    $stmt = $pdo->prepare("
        SELECT employee_id, first_name, last_name
        FROM employee
        WHERE employee_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $master = $stmt->fetch(PDO::FETCH_ASSOC);

} elseif ($action === 'master_works') {

    // выполненные работы мастера
    $stmt = $pdo->prepare("
        SELECT 
            wr.work_id,
            date(wr.start_time)        AS work_date,
            s.name                     AS service_name,
            wr.price_cents / 100.0     AS cost,
            wr.notes
        FROM work_record wr
        JOIN service s ON wr.service_id = s.service_id
        WHERE wr.master_id = :id
        ORDER BY wr.start_time DESC
    ");
    $stmt->execute([':id' => $id]);
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT first_name || ' ' || last_name AS fio
        FROM employee
        WHERE employee_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $masterName = $stmt->fetchColumn();

} elseif ($action === 'master_schedule') {

    // имя мастера
    $stmt = $pdo->prepare("
        SELECT first_name || ' ' || last_name AS fio
        FROM employee
        WHERE employee_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $masterName = $stmt->fetchColumn();

    // все записи графика
    $stmt = $pdo->prepare("
        SELECT schedule_id, work_date, start_time, end_time, notes
        FROM master_schedule
        WHERE master_id = :id
        ORDER BY work_date, start_time
    ");
    $stmt->execute([':id' => $id]);
    $scheduleRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // запись для формы (добавление или редактирование)
    $scheduleId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
    $schedule = [
        'schedule_id' => 0,
        'master_id'   => $id,
        'work_date'   => '',
        'start_time'  => '',
        'end_time'    => '',
        'notes'       => '',
    ];
    if ($scheduleId > 0) {
        $stmt = $pdo->prepare("
            SELECT schedule_id, master_id, work_date, start_time, end_time, notes
            FROM master_schedule
            WHERE schedule_id = :sid AND master_id = :mid
        ");
        $stmt->execute([':sid' => $scheduleId, ':mid' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $schedule = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мойка авто — мастера</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background-color: #f5f5f5; }
        a.button, button { padding: 4px 8px; font-size: 13px; text-decoration: none; border: 1px solid #ccc; border-radius: 3px; background: #f7f7f7; cursor: pointer; }
        a.button:hover, button:hover { background: #eaeaea; }
        form.inline { display: inline; }
    </style>
</head>
<body>

<?php if ($action === 'masters'): ?>

    <h1>Мастера</h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Активен</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($masters as $m): ?>
                <tr>
                    <td><?= (int)$m['employee_id'] ?></td>
                    <td><?= htmlspecialchars($m['fio']) ?></td>
                    <td><?= htmlspecialchars($m['phone']) ?></td>
                    <td><?= $m['is_active'] ? 'Да' : 'Нет' ?></td>
                    <td>
                        <a class="button" href="index.php?action=master_form&id=<?= (int)$m['employee_id'] ?>">Редактировать</a>
                        <a class="button" href="index.php?action=master_delete&id=<?= (int)$m['employee_id'] ?>">Удалить</a>
                        <a class="button" href="index.php?action=master_schedule&id=<?= (int)$m['employee_id'] ?>">График</a>
                        <a class="button" href="index.php?action=master_works&id=<?= (int)$m['employee_id'] ?>">Выполненные работы</a>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <p>
        <a class="button" href="index.php?action=master_form">Добавить мастера</a>
    </p>

<?php elseif ($action === 'master_form'): ?>

    <h1><?= $master['employee_id'] ? 'Редактирование мастера' : 'Добавление мастера' ?></h1>

    <form method="post">
        <input type="hidden" name="form" value="master_save">
        <input type="hidden" name="employee_id" value="<?= (int)$master['employee_id'] ?>">

        <p>
            <label>Имя:<br>
                <input type="text" name="first_name" value="<?= htmlspecialchars($master['first_name']) ?>" required>
            </label>
        </p>

        <p>
            <label>Фамилия:<br>
                <input type="text" name="last_name" value="<?= htmlspecialchars($master['last_name']) ?>" required>
            </label>
        </p>

        <p>
            <label>Телефон:<br>
                <input type="text" name="phone" value="<?= htmlspecialchars($master['phone']) ?>">
            </label>
        </p>

        <p>
            <button type="submit">Сохранить</button>
            <a class="button" href="index.php?action=masters">Отмена</a>
        </p>
    </form>

<?php elseif ($action === 'master_delete' && $master): ?>

    <h1>Удалить мастера</h1>

    <p>Вы действительно хотите удалить мастера
        <strong><?= htmlspecialchars($master['first_name'] . ' ' . $master['last_name']) ?></strong>?
    </p>

    <form method="post" class="inline">
        <input type="hidden" name="form" value="master_delete">
        <input type="hidden" name="employee_id" value="<?= (int)$master['employee_id'] ?>">
        <button type="submit">Удалить</button>
    </form>

    <a class="button" href="index.php?action=masters">Отмена</a>

<?php elseif ($action === 'master_works'): ?>

    <h1>Выполненные работы — <?= htmlspecialchars($masterName) ?></h1>

    <?php if (empty($works)): ?>
        <p>Для этого мастера пока нет выполненных работ.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID работы</th>
                    <th>Дата</th>
                    <th>Услуга</th>
                    <th>Стоимость, ₽</th>
                    <th>Примечание</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($works as $w): ?>
                    <tr>
                        <td><?= (int)$w['work_id'] ?></td>
                        <td><?= htmlspecialchars($w['work_date']) ?></td>
                        <td><?= htmlspecialchars($w['service_name']) ?></td>
                        <td><?= number_format($w['cost'], 2, ',', ' ') ?></td>
                        <td><?= htmlspecialchars($w['notes']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <p>
        <a class="button" href="index.php?action=masters">Назад к списку мастеров</a>
    </p>

<?php elseif ($action === 'master_schedule'): ?>

    <h1>График работы — <?= htmlspecialchars($masterName) ?></h1>

    <?php if (empty($scheduleRows)): ?>
        <p>Для этого мастера пока нет записей в графике.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Начало</th>
                    <th>Окончание</th>
                    <th>Примечание</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduleRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['work_date']) ?></td>
                        <td><?= htmlspecialchars($row['start_time']) ?></td>
                        <td><?= htmlspecialchars($row['end_time']) ?></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                        <td>
                            <a class="button"
                               href="index.php?action=master_schedule&id=<?= $id ?>&schedule_id=<?= (int)$row['schedule_id'] ?>">
                                Редактировать
                            </a>
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Удалить эту запись графика?')">
                                <input type="hidden" name="form" value="schedule_delete">
                                <input type="hidden" name="schedule_id" value="<?= (int)$row['schedule_id'] ?>">
                                <input type="hidden" name="master_id" value="<?= $id ?>">
                                <button type="submit">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <h2><?= $schedule['schedule_id'] ? 'Редактирование записи' : 'Добавление записи' ?></h2>

    <form method="post">
        <input type="hidden" name="form" value="schedule_save">
        <input type="hidden" name="schedule_id" value="<?= (int)$schedule['schedule_id'] ?>">
        <input type="hidden" name="master_id" value="<?= (int)$schedule['master_id'] ?>">

        <p>
            <label>Дата (ГГГГ-ММ-ДД):<br>
                <input type="date" name="work_date" value="<?= htmlspecialchars($schedule['work_date']) ?>" required>
            </label>
        </p>

        <p>
            <label>Начало (ЧЧ:ММ):<br>
                <input type="time" name="start_time" value="<?= htmlspecialchars($schedule['start_time']) ?>" required>
            </label>
        </p>

        <p>
            <label>Окончание (ЧЧ:ММ):<br>
                <input type="time" name="end_time" value="<?= htmlspecialchars($schedule['end_time']) ?>" required>
            </label>
        </p>

        <p>
            <label>Примечание:<br>
                <input type="text" name="notes" value="<?= htmlspecialchars($schedule['notes']) ?>">
            </label>
        </p>

        <p>
            <button type="submit">Сохранить</button>
            <a class="button" href="index.php?action=master_schedule&id=<?= $id ?>">Сбросить форму</a>
            <a class="button" href="index.php?action=masters">К списку мастеров</a>
        </p>
    </form>

<?php else: ?>

    <p>Неизвестное действие.</p>
    <p><a class="button" href="index.php?action=masters">К списку мастеров</a></p>

<?php endif ?>

</body>
</html>
