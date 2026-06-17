/**
 * Mock 开关
 * VITE_USE_MOCK=true 时启用，前端不依赖后端即可完成所有页面开发。
 */
export const useMock = import.meta.env.VITE_USE_MOCK === 'true'

/**
 * 模拟异步延迟，让 loading 状态可见。
 */
export const delay = (ms = 300) => new Promise((resolve) => setTimeout(resolve, ms))
