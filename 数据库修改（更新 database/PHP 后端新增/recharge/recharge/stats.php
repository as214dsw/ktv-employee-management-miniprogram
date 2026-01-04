<?php
require_once '../db.php';
$pdo = getPDO();

$data = json_decode(file_get_contents('php://input'), true);
$openid = $data['openid'] ?? '';  // 管理员openid
$start_date = $data['start_date'] ?? '';  // 可选时间范围
$end_date = $data['end_date'] ?? '';

if (empty($openid)) {
    echo json_encode(['code' => 400, 'msg' => '参数缺失']);
    exit;
}

// 权限检查（仅管理员）
$stmt = $pdo->prepare("SELECT role FROM team_members WHERE user_openid = ? LIMIT 1");
$stmt->execute([$openid]);
$role = $stmt->fetchColumn();
if ($role !== 'admin') {
    echo json_encode(['code' => 403, 'msg' => '无权限访问统计']);
    exit;
}

$where = "";
$params = [];
if ($start_date && $end_date) {
    $where = "WHERE recharge_time BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
}

// 统计查询
$stats = [
    'total_recharge' => 0,      // 总充值金额
    'member_count' => 0,        // 活跃会员数
    'avg_recharge_times' => 0,  // 平均充值次数
    'top_recharged_members' => []  // 前5充值员工
];

$stmt = $pdo->prepare("SELECT SUM(amount) FROM recharge_logs $where");
$stmt->execute($params);
$stats['total_recharge'] = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT target_openid) FROM recharge_logs $where");
$stmt->execute($params);
$stats['member_count'] = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT AVG(recharge_count) FROM (SELECT COUNT(*) AS recharge_count FROM recharge_logs GROUP BY target_openid) AS sub");
$stmt->execute();
$stats['avg_recharge_times'] = round($stmt->fetchColumn() ?? 0, 2);

$stmt = $pdo->prepare("SELECT target_openid, SUM(amount) AS total FROM recharge_logs $where GROUP BY target_openid ORDER BY total DESC LIMIT 5");
$stmt->execute($params);
$stats['top_recharged_members'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['code' => 0, 'msg' => '统计成功', 'data' => $stats]);
?>
