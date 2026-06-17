import type { AuditLogItem, BalanceAdjustRecord, UserRebateOverride } from '@/types/admin'

export const mockAuditLogs: AuditLogItem[] = [
  {
    id: 'AUD-001',
    datetime: '2023-10-27 14:20:45',
    operator: '张三 (Admin_01)',
    operatorAvatar: 'ZS',
    actionType: '配置更改',
    status: '成功',
    target: 'Global_Config_V2',
    ip: '192.168.1.105 (北京)',
    device: 'macOS / Chrome',
    transactionId: 'TRX-99201-AX',
    changes: [
      { field: 'milestone_rebate', fieldLabel: 'Milestone 返现比例调整', oldValue: '5.00%', newValue: '6.00%' },
    ],
    remark: '根据 Q4 季度激励计划上调基础返现比例，已通过合规部审核。',
    reviewer: 'Supervisor_Chen',
    reviewStatus: '已通过复核',
    events: [
      { text: '由 Admin_01 提交更改', time: '14:20:45', status: 'done' },
      { text: '系统自动校验通过', time: '14:20:46', status: 'done' },
      { text: '邮件通知所有受影响用户', time: '14:21:00 (计划中)', status: 'planned' },
    ],
  },
  {
    id: 'AUD-002',
    datetime: '2023-10-27 13:15:22',
    operator: '李四 (Admin_05)',
    operatorAvatar: 'LS',
    actionType: '手动余额调整',
    status: '成功',
    target: 'User_882091',
    ip: '192.168.1.108 (上海)',
    device: 'Windows / Edge',
    transactionId: 'TRX-99202-BX',
    changes: [
      { field: 'balance', fieldLabel: '用户余额', oldValue: '¥11,950.00', newValue: '¥12,450.00' },
    ],
    remark: '由于系统返利延迟到账，经沟通后手动补发差额部分。',
    reviewer: 'Admin_01',
    reviewStatus: '已通过复核',
    events: [
      { text: '由 Admin_05 提交调整', time: '13:15:22', status: 'done' },
      { text: '系统自动校验通过', time: '13:15:23', status: 'done' },
      { text: '余额已更新', time: '13:15:24', status: 'done' },
    ],
  },
  {
    id: 'AUD-003',
    datetime: '2023-10-27 11:05:10',
    operator: '系统自动 (System)',
    operatorAvatar: 'SYS',
    actionType: '用户冻结',
    status: '成功',
    target: 'User_991002',
    ip: '-',
    device: 'System',
    transactionId: 'TRX-99203-CX',
    changes: [
      { field: 'status', fieldLabel: '用户状态', oldValue: 'active', newValue: 'frozen' },
    ],
    remark: '异常操作触发自动冻结规则（50次/小时）',
    events: [
      { text: '系统检测到异常操作', time: '11:05:10', status: 'done' },
      { text: '自动执行冻结', time: '11:05:10', status: 'done' },
      { text: '通知管理员审核', time: '11:05:11', status: 'done' },
    ],
  },
  {
    id: 'AUD-004',
    datetime: '2023-10-26 18:44:30',
    operator: '王五 (Admin_02)',
    operatorAvatar: 'WW',
    actionType: '提现审批',
    status: '失败',
    target: 'Withdraw_7005',
    ip: '192.168.1.110 (深圳)',
    device: 'macOS / Safari',
    transactionId: 'TRX-99204-DX',
    changes: [],
    remark: '用户账号信息不完整，审批拒绝。',
    events: [
      { text: '由 Admin_02 执行审批', time: '18:44:30', status: 'done' },
      { text: '系统校验失败：账号信息缺失', time: '18:44:31', status: 'done' },
    ],
  },
]

export const mockBalanceAdjustRecords: BalanceAdjustRecord[] = [
  { id: 1, type: 'add', amount: '500.00', reason: '手动补偿', remark: '由于系统返利延迟到账，经沟通后手动补发差额部分。', operator: 'Admin_01', createdAt: '2024.03.12 14:20', tag: '手动补偿', tagColor: 'warning' },
  { id: 2, type: 'subtract', amount: '1200.00', reason: '违规扣除', remark: '账号存在异常刷单行为，根据服务条款扣除违规所得收益。', operator: 'Security_Mgr', createdAt: '2024.01.05 09:15', tag: '违规扣除', tagColor: 'danger' },
  { id: 3, type: 'add', amount: '2000.00', reason: '活动奖励', remark: '完成双11年度活跃任务，一次性额外奖励发放。', operator: 'System', createdAt: '2023.11.11 00:01', tag: '活动奖励', tagColor: 'success' },
]

export const mockUserRebateOverrides: UserRebateOverride[] = [
  { userId: 1001, username: 'demo', nickname: '演示用户', customRates: [{ level: 1, rate: '0.12' }, { level: 2, rate: '0.06' }, { level: 3, rate: '0.03' }], enabled: true, updatedAt: '2024-03-10 16:00:00' },
  { userId: 8840, username: 'Chloe_VIP', nickname: 'Chloe_VIP', customRates: [{ level: 1, rate: '0.15' }, { level: 2, rate: '0.08' }, { level: 3, rate: '0.04' }], enabled: true, updatedAt: '2024-02-20 11:30:00' },
]
