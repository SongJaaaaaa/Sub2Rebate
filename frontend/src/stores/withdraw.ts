import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getWithdrawConfig, getWithdrawAccount, getWithdrawRecords, applyWithdraw } from '@/api/withdraw'
import type { WithdrawConfig, WithdrawAccount, WithdrawRecord } from '@/types/withdraw'
import type { Balance } from '@/types/user'

export const useWithdrawStore = defineStore('withdraw', () => {
  const config = ref<WithdrawConfig | null>(null)
  const account = ref<WithdrawAccount | null>(null)
  const records = ref<WithdrawRecord[]>([])
  const loading = ref(false)

  const fetchConfig = async () => {
    const res = await getWithdrawConfig()
    if (res.code === 0) config.value = res.data
  }

  const fetchAccount = async () => {
    const res = await getWithdrawAccount()
    if (res.code === 0) account.value = res.data.account
  }

  const fetchRecords = async (page = 1, pageSize = 20, status?: string) => {
    loading.value = true
    try {
      const res = await getWithdrawRecords(page, pageSize, status)
      if (res.code === 0) records.value = res.data.list
    } finally {
      loading.value = false
    }
  }

  const submitApply = async (amount: string, remark?: string): Promise<Balance | null> => {
    loading.value = true
    try {
      const res = await applyWithdraw({ amount, remark })
      if (res.code === 0) {
        records.value.unshift(res.data.record)
        return res.data.balance
      }
      return null
    } finally {
      loading.value = false
    }
  }

  return { config, account, records, loading, fetchConfig, fetchAccount, fetchRecords, submitApply }
})
