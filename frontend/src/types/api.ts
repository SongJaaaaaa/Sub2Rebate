export interface ApiRes<T> {
  code: number
  message: string
  data: T
}

export interface PageRes<T> {
  list: T[]
  page: number
  pageSize: number
  total: number
}

export interface ApiError {
  code: number
  message: string
}
