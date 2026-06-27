import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getWithdrawConfig, getWithdrawAccount, getWithdrawRecords, applyWithdraw, transferToApiQuota } from '@/api/withdraw'
import type { WithdrawConfig, WithdrawAccount, WithdrawRecord } from '@/types/withdraw'
import type { Balance, Sub2ApiBalance } from '@/types/user'

export const useWithdrawStore = defineStore('withdraw', () => {
  const config = ref<WithdrawConfig | null>(null)
  const account = ref<WithdrawAccount | null>(null)
  const records = ref<WithdrawRecord[]>([])
  const total = ref(0)
  const loading = ref(false)
  const sub2ApiBalance = ref<Sub2ApiBalance | null>(null)

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
      if (res.code === 0) {
        records.value = res.data.list
        total.value = res.data.total
      }
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
        sub2ApiBalance.value = res.data.sub2ApiBalance || null
        return res.data.balance
      }
      return null
    } finally {
      loading.value = false
    }
  }

  const submitToApiQuota = async (amount: string, remark?: string): Promise<Balance | null> => {
    loading.value = true
    try {
      const res = await transferToApiQuota({ amount, remark })
      if (res.code === 0) {
        records.value.unshift(res.data.record)
        sub2ApiBalance.value = res.data.sub2ApiBalance || null
        return res.data.balance
      }
      return null
    } finally {
      loading.value = false
    }
  }

  return { config, account, records, total, loading, sub2ApiBalance, fetchConfig, fetchAccount, fetchRecords, submitApply, submitToApiQuota }
})
