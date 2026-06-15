export interface UserDisplayLike {
  id?: number | string
  userId?: number | string
  username?: string | null
  nickname?: string | null
  email?: string | null
}

export const userName = (user: UserDisplayLike | null | undefined) => {
  const nickname = String(user?.nickname || '').trim()
  if (nickname) return nickname

  const username = String(user?.username || '').trim()
  if (username) return username

  const email = String(user?.email || '').trim()
  if (email) return email.includes('@') ? email.split('@')[0] : email

  const id = user?.id || user?.userId
  return id ? `user_${id}` : '用户'
}

export const userAccount = (user: UserDisplayLike | null | undefined) => {
  const username = String(user?.username || '').trim()
  if (username) return username

  const email = String(user?.email || '').trim()
  if (email) return email

  const id = user?.id || user?.userId
  return id ? `user_${id}` : 'unknown'
}

export const userInitial = (user: UserDisplayLike | null | undefined) => userName(user).charAt(0).toUpperCase()
