import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getPromotionSummary, getConversions, getInviteRecords } from '@/api/promotion'
import type { PromotionSummary, ConversionRecord, InviteRecord } from '@/types/rebate'

export const usePromotionStore = defineStore('promotion', () => {
  const summary = ref<PromotionSummary | null>(null)
  const conversions = ref<ConversionRecord[]>([])
  const inviteRecords = ref<InviteRecord[]>([])
  const loading = ref(false)

  const fetchSummary = async () => {
    loading.value = true
    try {
      const res = await getPromotionSummary()
      if (res.code === 0) summary.value = res.data
    } finally {
      loading.value = false
    }
  }

  const fetchConversions = async (page = 1, pageSize = 20) => {
    const res = await getConversions(page, pageSize)
    if (res.code === 0) conversions.value = res.data.list
  }

  const fetchInviteRecords = async (page = 1, pageSize = 20) => {
    const res = await getInviteRecords(page, pageSize)
    if (res.code === 0) inviteRecords.value = res.data.list
  }

  return { summary, conversions, inviteRecords, loading, fetchSummary, fetchConversions, fetchInviteRecords }
})
