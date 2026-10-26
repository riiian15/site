<?php
/**
 * Orders API: создание заявок (покупка/аренда/ремонт/консультация) и список.
 * Улучшения: серверная валидация, транзакция + блокировка строк товара,
 * server-side расчёт суммы, honeypot-поле против ботов, rate-limit, CSRF.
 */

require_once __DIR__ . '/middleware/helpers.php';

handleCors();
startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    // Список заказов — только для авторизованных сотрудников
    if (empty($_SESSION['user_id'])) {
        jsonError('Требуется авторизация', 401);
    }
    getOrders();
} elseif ($method === 'POST' && $action === 'create') {
    enforceRateLimit('order_create');
    verifyCsrf();
    createOrder();
} else {
    jsonError('Некорректный запрос', 405);
}

function getOrders(): void
{
    $db = (new Database())->getConnection();
    [$page, $perPage, $offset] = pagination(25, 100);

    $stmt = $db->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    $total = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    if ($orders) {
        $ids = implode(',', array_map('intval', array_column($orders, 'id')));
        $items = $db->query(
            "SELECT oi.*, p.name AS product_name FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id IN ($ids)"
        )->fetchAll();
        $map = [];
        foreach ($items as $it) $map[$it['order_id']][] = $it;
        foreach ($orders as &$o) $o['items'] = $map[$o['id']] ?? [];
    }

    jsonOut(['data' => $orders, 'page' => $page, 'total' => $total,
             'pages' => (int)ceil($total / $perPage)]);
}

function createOrder(): void
{
    $db = (new Database())->getConnection();
    $data = readJsonBody();

    // Honeypot: боты заполняют скрытое поле — тихо отбрасываем
    if (!empty($data['website'])) {
        jsonOut(['success' => true]); // притворяемся успешным
    }

    need($data, 'name', 'phone');

    $name  = sanitizeText($data['name'], 100);
    $phone = sanitizeText($data['phone'], 20);
    $email = sanitizeText($data['email'] ?? '', 100);
    $comment = sanitizeText($data['comment'] ?? '', 2000);

    if (!textInRange($name, 2, 100))   jsonError('Укажите имя (2–100 символов)', 422);
    if (!validPhone($phone))      jsonError('Некорректный номер телефона', 422);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Некорректный e-mail', 422);
    }

    $type = oneOf($data['order_type'] ?? 'purchase',
                  ['purchase', 'rental', 'repair', 'consult'], 'order_type');

    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if ($type !== 'consult' && count($items) > 50) {
        jsonError('Слишком много позиций в заявке', 422);
    }

    try {
        $db->beginTransaction();

        // Цены берём ТОЛЬКО из БД (server-side), клиентским игнорируем
        $totalAmount = 0.0;
        $checkedItems = [];
        if ($items) {
            $ids = array_filter(array_map('intval', array_column($items, 'product_id')));
            if ($ids) {
                $in = implode(',', $ids);
                // FETCH_KEY_PAIR нельзя передать в query() — используем fetchAll() без аргументов
                // и индексируем массив вручную.
                $rows = [];
                foreach ($db->query("SELECT id, price FROM products WHERE id IN ($in) FOR UPDATE")->fetchAll() as $row) {
                    $rows[(int)$row['id']] = $row;
                }

                foreach ($items as $it) {
                    $pid = (int)($it['product_id'] ?? 0);
                    $qty = max(1, min(99, (int)($it['quantity'] ?? 1)));
                    if (!isset($rows[$pid])) continue;          // товар удалён — пропускаем
                    $row = $rows[$pid];
                    $itemType = oneOf($it['type'] ?? 'purchase',
                                      ['purchase', 'rental', 'repair'], 'item.type');
                    $price = (float)$row['price'];
                    $totalAmount += $price * $qty;
                    $checkedItems[] = [
                        'pid' => $pid, 'type' => $itemType, 'qty' => $qty, 'price' => $price,
                    ];
                }
            }
        }

        $stmt = $db->prepare("INSERT INTO orders
            (name, phone, email, comment, order_type, total_amount)
            VALUES (:name, :phone, :email, :comment, :order_type, :total_amount)");
        $stmt->execute([
            ':name' => $name, ':phone' => $phone, ':email' => $email,
            ':comment' => $comment, ':order_type' => $type,
            ':total_amount' => round($totalAmount, 2),
        ]);
        $orderId = (int)$db->lastInsertId();

        if ($checkedItems) {
            $ins = $db->prepare("INSERT INTO order_items
                (order_id, product_id, item_type, quantity, price)
                VALUES (:oid, :pid, :type, :qty, :price)");
            foreach ($checkedItems as $ci) {
                $ins->execute([
                    ':oid' => $orderId, ':pid' => $ci['pid'],
                    ':type' => $ci['type'], ':qty' => $ci['qty'], ':price' => $ci['price'],
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('createOrder: ' . $e->getMessage());
        jsonError('Не удалось отправить заявку. Попробуйте ещё раз.', 500);
    }

    jsonOut(['success' => true, 'id' => $orderId,
             'total_amount' => round($totalAmount, 2)], 201);
}
