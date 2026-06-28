<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { InfoFilled, WarningFilled } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import { getFullRebateConfig, saveFullRebateConfig } from '@/api/admin'
import type { FullRebateConfig } from '@/types/admin'

const loading = ref(false)
const saving = ref(false)
const activeSection = ref('milestone')

const config = reactive<FullRebateConfig>({
  milestone: { threshold: '100', reward: '15.00', maxTimes: 2 },
  multiLevel: { enabled: true, stageAmount: '100', rewardAmount: '15', decayCoefficient: '0.4', maxDepth: 5, inactiveNodeMode: 'platform' },
  rechargeBonus: [
    { amount: '100', bonus: '5' },
    { amount: '200', bonus: '15' },
    { amount: '500', bonus: '50' },
    { amount: '1000', bonus: '120' },
  ],
  withdrawLimit: { minAmount: '100.00', cooldownHours: 24, dailyLimit: 1 },
  riskControl: { blacklistEnabled: true, autoFreezeThreshold: 50, lieFlatEnabled: true, lieFlatDays: 7, lieFlatRestoreMinRecharge: '10' },
  lastModifiedBy: '',
  lastModifiedAt: '',
})

const sections = [
  { id: 'milestone', label: '里程碑配置' },
  { id: 'multiLevel', label: '多级返利配置' },
  { id: 'rechargeBonus', label: '充值赠送配置' },
  { id: 'withdrawLimit', label: '提现配置' },
  { id: 'riskControl', label: '风控配置' },
]

const milestoneEndAmount = computed(() => {
  const threshold = Number(config.milestone.threshold) || 0
  const times = Number(config.milestone.maxTimes) || 0
  return (threshold * times).toFixed(2)
})

const levelPreview = computed(() => {
  const pool = Number(config.multiLevel.rewardAmount) || 0
  const decay = Number(config.multiLevel.decayCoefficient) || 0
  const depth = Number(config.multiLevel.maxDepth) || 1
  const weights = Array.from({ length: depth }, (_, i) => Math.pow(decay, i))
  const totalWeight = weights.reduce((sum, item) => sum + item, 0)
  const levels = weights.map((weight, index) => {
    const ratio = totalWeight > 0 ? weight / totalWeight : 0
    const amount = pool * ratio
    return {
      level: index + 1,
      ratio: (ratio * 100).toFixed(2),
      amount: amount.toFixed(2),
    }
  })

  return {
    levels,
    total: levels.reduce((sum, item) => sum + Number(item.amount), 0).toFixed(2),
  }
})

const scrollTo = (id: string) => {
  activeSection.value = id
  document.getElementById(`section-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const fetchConfig = async () => {
  loading.value = true
  try {
    const res = await getFullRebateConfig()
    if (res.code === 0) Object.assign(config, res.data)
  } finally {
    loading.value = false
  }
}

const onSave = async () => {
  saving.value = true
  try {
    const res = await saveFullRebateConfig(config)
    if (res.code === 0) ElMessage.success('配置已保存')
  } catch {
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(() => fetchConfig())
</script>

<template>
  <div class="flex min-h-[calc(100vh-120px)] gap-6">
    <aside class="hidden w-56 shrink-0 lg:block">
      <div class="sticky top-24 space-y-1">
        <button
          v-for="s in sections"
          :key="s.id"
          class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-left text-sm font-semibold transition"
          :class="activeSection === s.id
            ? 'border-l-4 border-[var(--sr-secondary)] bg-[var(--sr-secondary)]/10 text-[var(--sr-secondary)]'
            : 'text-[var(--sr-muted)] hover:bg-[var(--sr-surface-low)]'"
          @click="scrollTo(s.id)"
        >
          <span>{{ s.label }}</span>
          <span class="text-xs text-[var(--sr-muted)]">•</span>
        </button>

        <div v-if="config.lastModifiedBy" class="mt-6 border-t border-[var(--sr-border)] px-4 pt-4">
          <div class="flex items-center gap-2 text-xs text-[var(--sr-muted)]">
            <el-icon :size="14"><InfoFilled /></el-icon>
            <span>最后修改</span>
          </div>
          <div class="mt-1 text-xs text-[var(--sr-muted)]">管理员 {{ config.lastModifiedBy }}</div>
          <div class="text-xs text-[var(--sr-muted)]">时间：{{ config.lastModifiedAt }}</div>
        </div>
      </div>
    </aside>

    <div v-loading="loading" class="min-w-0 flex-1 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">配置中心</h1>
        <el-button type="primary" :loading="saving" @click="onSave">保存更改</el-button>
      </div>

      <AppCard id="section-milestone">
        <div class="mb-4">
          <h2 class="text-lg font-bold">里程碑配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">下级累计充值达到固定台阶时，优先给直推上级发放个人奖励。</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              下级累充门槛
              <el-tooltip placement="top" content="下级累计充值每达到一次该门槛，触发一次里程碑判断。这里看的是累计充值，不是单笔充值。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.milestone.threshold" placeholder="100">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              每次奖励金额
              <el-tooltip placement="top" content="每次触发里程碑时，发给直推上级的固定奖励金额。里程碑阶段只奖励直推上级。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.milestone.reward" placeholder="15">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
            最多奖励次数
            <el-tooltip placement="top" content="同一个下级最多触发几次里程碑奖励。次数用完后，该下级才进入多级返利阶段。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <el-input-number v-model="config.milestone.maxTimes" :min="1" :max="99" />
        </div>

        <div class="mt-4 rounded-lg bg-blue-50 p-4">
          <div class="flex items-start gap-2">
            <el-icon class="mt-0.5 text-blue-500" :size="16"><InfoFilled /></el-icon>
            <div class="text-sm text-blue-700">
              <p class="font-semibold">当前配置示例</p>
              <p class="mt-1">下级累充满 {{ config.milestone.threshold }} 元，直推上级奖励 {{ config.milestone.reward }} 元。</p>
              <p>共可触发 {{ config.milestone.maxTimes }} 次，里程碑阶段完成点为 {{ milestoneEndAmount }} 元。</p>
            </div>
          </div>
        </div>
      </AppCard>

      <AppCard id="section-multiLevel">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold">多级返利配置</h2>
            <p class="text-sm text-[var(--sr-muted)]">下级完成里程碑个人奖励后，后续累计充值按台阶触发多级返利分配。</p>
          </div>
          <el-tag :type="config.multiLevel.enabled ? 'success' : 'info'" effect="plain">
            {{ config.multiLevel.enabled ? '已启用' : '未启用' }}
          </el-tag>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              下级累充返利门槛
              <el-tooltip placement="top" content="下级完成里程碑阶段后，后续累计充值每满一次该金额，触发一次多级返利分配。不是每充值一笔都返。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.multiLevel.stageAmount" placeholder="100">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              每次分配奖励池
              <el-tooltip placement="top" content="每次触发多级返利时，整条上级链路本次总共分配的奖励金额。系统会按衰减系数和最大返利深度拆分到各级上级。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.multiLevel.rewardAmount" placeholder="15">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              衰减系数
              <el-tooltip placement="top" content="一级权重最高，后续层级按该系数递减。系数越小，奖励越集中在近一级上级。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.multiLevel.decayCoefficient" placeholder="0.4">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">系数</span></template>
            </el-input>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              最大返利深度
              <el-tooltip placement="top" content="每次触发多级返利时，最多向上分配的层级数。超过该深度的上级不参与本次返利。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-select v-model="config.multiLevel.maxDepth" style="width: 200px">
              <el-option v-for="n in 10" :key="n" :label="`${n} 层`" :value="n" />
            </el-select>
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
            失效节点返利处理方式
            <el-tooltip placement="top" content="当某级上级不具备返利资格时，可选择该层奖励归平台，或跳过该节点后对剩余有效上级重新分配。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <el-radio-group v-model="config.multiLevel.inactiveNodeMode">
            <el-radio-button label="platform">归平台</el-radio-button>
            <el-radio-button label="exclude_recalculate">排除后重算</el-radio-button>
          </el-radio-group>
        </div>

        <div class="mt-4 rounded-lg bg-blue-50 p-4">
          <div class="flex items-start gap-2">
            <el-icon class="mt-0.5 text-blue-500" :size="16"><InfoFilled /></el-icon>
            <div class="text-sm text-blue-700">
              <p class="font-semibold">规则说明</p>
              <p class="mt-1">1. 里程碑奖励优先发放给直推上级。</p>
              <p>2. 当下级完成里程碑阶段后，才进入多级返利阶段。</p>
              <p>3. 多级返利按累计充值台阶触发，不按每笔充值实时触发。</p>
              <p>4. 每次触发时，系统按“奖励池 + 衰减系数 + 最大返利深度”完成上级链路分配。</p>
            </div>
          </div>
        </div>

        <div class="mt-4 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface)] p-4">
          <p class="mb-3 text-sm font-bold">实时预览（基于当前配置）</p>
          <div class="mb-4 space-y-1 text-sm text-[var(--sr-muted)]">
            <p>下级累充满 {{ config.milestone.threshold }} 元，直推上级奖励 {{ config.milestone.reward }} 元。</p>
            <p>下级累充满 {{ milestoneEndAmount }} 元后完成里程碑阶段。</p>
            <p>此后每再累充满 {{ config.multiLevel.stageAmount }} 元，触发一次 {{ config.multiLevel.rewardAmount }} 元的多级返利分配。</p>
          </div>

          <div class="flex flex-wrap gap-4">
            <div v-for="item in levelPreview.levels" :key="item.level" class="rounded-lg bg-[var(--sr-surface-low)] px-3 py-2 text-center">
              <div class="text-xs text-[var(--sr-muted)]">L{{ item.level }} 上级</div>
              <div class="text-sm font-bold text-[var(--sr-secondary)]">¥{{ item.amount }}</div>
              <div class="text-[10px] text-[var(--sr-muted)]">({{ item.ratio }}%)</div>
            </div>
          </div>

          <div class="mt-3 flex items-center justify-between border-t border-[var(--sr-border)] pt-2">
            <span class="text-xs text-[var(--sr-muted)]">归一化后总分配：</span>
            <span class="text-sm font-bold text-[var(--sr-secondary)]">¥{{ levelPreview.total }} / ¥{{ config.multiLevel.rewardAmount }}</span>
          </div>
          <p class="mt-2 text-xs text-[var(--sr-muted)]">* 系统会在有效上级范围内按权重归一化分配本次奖励池。</p>
        </div>
      </AppCard>

      <AppCard id="section-rechargeBonus">
        <div class="mb-4">
          <h2 class="text-lg font-bold">充值赠送配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">这里控制充值页每档赠送额度，前端展示和后端入账都会同步生效。</p>
        </div>

        <div class="space-y-3">
          <div
            v-for="item in config.rechargeBonus"
            :key="item.amount"
            class="grid gap-4 rounded-lg border border-[var(--sr-border)] p-4 sm:grid-cols-[180px_minmax(0,1fr)]"
          >
            <div>
              <label class="mb-1 block text-sm font-semibold text-[var(--sr-muted)]">充值金额</label>
              <el-input v-model="item.amount" disabled>
                <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
              </el-input>
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-[var(--sr-muted)]">赠送额度</label>
              <el-input v-model="item.bonus" placeholder="0">
                <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
              </el-input>
            </div>
          </div>
        </div>
      </AppCard>

      <AppCard id="section-withdrawLimit">
        <div class="mb-4">
          <h2 class="text-lg font-bold">提现配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">控制资金流出安全与频率，防止异常提现行为。</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              单次最低提现额
              <el-tooltip placement="top" content="低于该金额的提现申请将被系统拦截。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.withdrawLimit.minAmount" placeholder="100.00">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">CNY</span></template>
            </el-input>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              提现冷却时间
              <el-tooltip placement="top" content="同一用户两次提现申请之间的最小间隔。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input-number v-model="config.withdrawLimit.cooldownHours" :min="1" :max="168" />
            <span class="ml-2 text-xs text-[var(--sr-muted)]">小时</span>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              每日提现次数
              <el-tooltip placement="top" content="同一用户每天最多可以提现的次数。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input-number v-model="config.withdrawLimit.dailyLimit" :min="1" :max="100" />
            <span class="ml-2 text-xs text-[var(--sr-muted)]">次</span>
          </div>
        </div>
      </AppCard>

      <AppCard id="section-riskControl">
        <div class="mb-4">
          <h2 class="text-lg font-bold">风控配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">防止小号自刷、批量注册、小额套利等异常行为。</p>
        </div>

        <div class="rounded-lg border border-[var(--sr-border)] p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <el-icon class="text-red-500" :size="20"><WarningFilled /></el-icon>
              <div>
                <div class="flex items-center gap-1 text-sm font-bold">
                  全局黑名单总开关
                  <el-tooltip placement="top" content="开启后系统将实时检测设备、IP、账户等命中黑名单规则的行为。">
                    <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
                  </el-tooltip>
                </div>
                <div class="text-xs text-[var(--sr-muted)]">开启后会应用实时拦截逻辑。</div>
              </div>
            </div>
            <el-switch v-model="config.riskControl.blacklistEnabled" />
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
            自动冻结阈值
            <el-tooltip placement="top" content="单个账号在短时间内异常操作达到该次数时自动冻结。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <div class="flex items-center gap-2">
            <el-input-number v-model="config.riskControl.autoFreezeThreshold" :min="1" :max="999" />
            <span class="text-xs text-[var(--sr-muted)]">次/小时</span>
          </div>
        </div>

        <div class="mt-4 rounded-lg border border-[var(--sr-border)] p-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-1 text-sm font-bold">
                防躺平检测
                <el-tooltip placement="top" content="连续指定天数无充值、无活跃、无新增下级的用户会被暂停新返利。">
                  <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
                </el-tooltip>
              </div>
              <div class="text-xs text-[var(--sr-muted)]">余额变化只用于判断活跃度，不直接触发返利。</div>
            </div>
            <el-switch v-model="config.riskControl.lieFlatEnabled" />
          </div>
          <div class="mt-4 flex items-center gap-2">
            <span class="text-sm font-semibold text-[var(--sr-muted)]">连续无活跃天数</span>
            <el-input-number v-model="config.riskControl.lieFlatDays" :min="1" :max="365" :disabled="!config.riskControl.lieFlatEnabled" />
            <span class="text-xs text-[var(--sr-muted)]">天</span>
          </div>
          <div class="mt-4">
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              置灰恢复最低充值金额
              <el-tooltip placement="top" content="用户被置灰后，单次成功充值达到该金额才会恢复返利资格。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.riskControl.lieFlatRestoreMinRecharge" :disabled="!config.riskControl.lieFlatEnabled" placeholder="10">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
        </div>
      </AppCard>

      <div class="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3">
        <div class="flex items-center gap-2">
          <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
          <span class="text-sm text-green-700">所有配置变更都会自动记录到审计日志中，便于回溯。</span>
        </div>
        <router-link to="/admin/audit-log">
          <el-button type="primary" text size="small">查看完整日志</el-button>
        </router-link>
      </div>
    </div>
  </div>
</template>
