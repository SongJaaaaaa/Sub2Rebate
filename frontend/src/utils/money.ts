/**
 * 格式化金额为 ¥x,xxx.xx 形式
 */
export const money = (val?: string | number): string => {
  if (val === undefined || val === null || val === '') return '¥0.00'
  const num = typeof val === 'string' ? parseFloat(val) : val
  if (isNaN(num)) return '¥0.00'
  return `¥${num.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

/**
 * 纯数字格式化（不带¥符号）
 */
export const formatAmount = (val?: string | number): string => {
  if (val === undefined || val === null || val === '') return '0.00'
  const num = typeof val === 'string' ? parseFloat(val) : val
  if (isNaN(num)) return '0.00'
  return num.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
