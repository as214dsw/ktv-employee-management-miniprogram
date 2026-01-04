<?php
require_once '../db.php';
$pdo = getPDO();

$data = json_decode(file_get_contents('php://input'), true);
$openid = $data['openid'] ?? '';  // 查询者openid（管理员权限）
$target_openid = $data['target_openid'] ?? '';  // 可选：指定员工
$page = $data['page'] ?? 1;
$limit = $data['limit'] ?? 10;

if (empty($openid)) {
    echo json_encode(['code' => 400, 'msg' => '参数缺失']);
    exit;
}

// 权限检查（假设仅管理员可查询全记录）
$stmt = $pdo->prepare("SELECT role FROM team_members WHERE user_openid = ? LIMIT 1");  // 简化，结合现有表
$stmt->execute([$openid]);
$role = $stmt->fetchColumn();
if ($role !== 'admin') {
    echo json_encode(['code' => 403, 'msg' => '无权限查询']);
    exit;
}

$where = $target_openid ? "AND target_openid = :target_openid" : "";
$params = $target_openid ? [':target_openid' => $target_openid] : [];
$params[':offset'] = ($page - 1) * $limit;
$params[':limit'] = $limit;

// 查询记录（按目标员工分组显示）
$sql = "SELECT initiator_openid, target_openid, amount, recharge_time, note 
        FROM recharge_logs 
        WHERE status = 'success' $where 
        ORDER BY recharge_time DESC 
        LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 总计
$stmt = $pdo->prepare("SELECT COUNT(*) FROM recharge_logs WHERE status = 'success' $where");
$stmt->execute(array_filter($params, fn($k) => $k !== ':offset' && $k !== ':limit', ARRAY_FILTER_USE_KEY));
$total = $stmt->fetchColumn();

echo json_encode(['code' => 0, 'msg' => '查询成功', 'data' => $records, 'total' => $total]);
?>
