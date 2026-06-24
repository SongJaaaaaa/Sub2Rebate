<script setup lang="ts">
import { onMounted, ref, reactive, computed } from 'vue'
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
  multiLevel: { enabled: true, totalPoolRate: '15', decayCoefficient: '0.4', maxDepth: 5, inactiveNodeMode: 'platform' },
  withdrawLimit: { minAmount: '100.00', cooldownHours: 24 },
  riskControl: { blacklistEnabled: true, autoFreezeThreshold: 50, lieFlatEnabled: true, lieFlatDays: 7, lieFlatRestoreMinRecharge: '10' },
  lastModifiedBy: '',
  lastModifiedAt: '',
})

const sections = [
  { id: 'milestone', label: '里程碑配置' },
  { id: 'multiLevel', label: '多级返利配置' },
  { id: 'withdrawLimit', label: '提现配置' },
  { id: 'riskControl', label: '风控配置' },
]

// 计算结果示例
const levelPreview = computed(() => {
  const pool = parseFloat(config.multiLevel.totalPoolRate) || 0
  const decay = parseFloat(config.multiLevel.decayCoefficient) || 0
  const depth = config.multiLevel.maxDepth || 1
  const levels: { level: number; rate: string }[] = []
  let total = 0
  for (let i = 0; i < depth; i++) {
    const rate = (pool / 100) * Math.pow(decay, i) * 100
    levels.push({ level: i + 1, rate: rate.toFixed(2) })
    total += rate
  }
  return { levels, total: total.toFixed(2) }
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
    <!-- 左侧锚点导航 -->
    <aside class="hidden w-56 shrink-0 lg:block">
      <div class="sticky top-24 space-y-1">
        <button
          v-for="s in sections"
          :key="s.id"
          class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-left text-sm font-semibold transition"
          :class="activeSection === s.id
            ? 'bg-[var(--sr-secondary)]/10 text-[var(--sr-secondary)] border-l-4 border-[var(--sr-secondary)]'
            : 'text-[var(--sr-muted)] hover:bg-[var(--sr-surface-low)]'"
          @click="scrollTo(s.id)"
        >
          <span>{{ s.label }}</span>
          <span class="text-xs text-[var(--sr-muted)]">›</span>
        </button>

        <!-- 最后修改记录 -->
        <div v-if="config.lastModifiedBy" class="mt-6 border-t border-[var(--sr-border)] px-4 pt-4">
          <div class="flex items-center gap-2 text-xs text-[var(--sr-muted)]">
            <el-icon :size="14"><InfoFilled /></el-icon>
            <span>最后修改</span>
          </div>
          <div class="mt-1 text-xs text-[var(--sr-muted)]">管理员: {{ config.lastModifiedBy }}</div>
          <div class="text-xs text-[var(--sr-muted)]">时间: {{ config.lastModifiedAt }}</div>
        </div>
      </div>
    </aside>

    <!-- 右侧内容区 -->
    <div v-loading="loading" class="min-w-0 flex-1 space-y-6">
      <!-- 顶部保存按钮 -->
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">配置中心</h1>
        <el-button type="primary" :loading="saving" @click="onSave">保存更改</el-button>
      </div>

      <!-- 里程碑配置 -->
      <AppCard id="section-milestone">
        <div class="mb-4">
          <h2 class="text-lg font-bold">里程碑配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">新人累计充值达到里程碑时触发奖励，只奖励直接上级</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              里程碑金额 (CNY)
              <el-tooltip placement="top" content="新人累计充值达到此金额的整数倍时触发一次奖励。例如设100，则累计充值100/200/300各触发一次。是累计充值而非单笔。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.milestone.threshold" placeholder="100">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              每次奖励金额 (CNY)
              <el-tooltip placement="top" content="每次触发里程碑时给直接上级发放的奖励金额。注意：只奖励直接上级，第2级及以上不参与里程碑阶段。">
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
            奖励次数上限
            <el-tooltip placement="top" content="同一个新人最多触发几次里程碑奖励。次数用完后该新人的后续充值进入正常多级分发阶段。默认3次意味着累计充值达到3倍里程碑金额后结束里程碑阶段。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <el-input-number v-model="config.milestone.maxTimes" :min="1" :max="99" />
        </div>

        <div class="mt-4 rounded-lg bg-blue-50 p-4">
          <div class="flex items-start gap-2">
            <el-icon class="mt-0.5 text-blue-500" :size="16"><InfoFilled /></el-icon>
            <div class="text-sm text-blue-700">
              <p class="font-semibold">运行示例（当前配置）：</p>
              <p class="mt-1">里程碑={{ config.milestone.threshold }}元，奖励={{ config.milestone.reward }}元，次数={{ config.milestone.maxTimes }}</p>
              <p class="mt-1">新人累计充值 {{ config.milestone.threshold }} → 上级 +{{ config.milestone.reward }}元（第1次）</p>
              <p>新人累计充值 {{ Number(config.milestone.threshold) * 2 }} → 上级 +{{ config.milestone.reward }}元（第2次）</p>
              <p>…次数用完后 → 进入正常多级分发</p>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- 多级返利配置 -->
      <AppCard id="section-multiLevel">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold">多级返利配置</h2>
            <p class="text-sm text-[var(--sr-muted)]">里程碑结束后，后续充值按多级公式分发，第1级拿最多，逐级衰减</p>
          </div>
          <el-tag :type="config.multiLevel.enabled ? 'success' : 'info'" effect="plain">
            {{ config.multiLevel.enabled ? '已启用' : '未启用' }}
          </el-tag>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              总返利池比例
              <el-tooltip placement="top" content="每笔充值中抽取多少比例进入返利池。例如15%表示用户充值100元，其中15元进入返利池用于分发给上级链路。池子越大推广激励越强，但平台利润越少。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.multiLevel.totalPoolRate" placeholder="15">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">%</span></template>
            </el-input>
          </div>
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              衰减系数
              <el-tooltip placement="top" content="每增加一级，分配比例按此系数递减。0.5表示每级拿上一级的50%。系数越小层级间差距越大，直接上级激励越强；系数越大分配越均匀。最终会做归一化确保总额不超返利池。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.multiLevel.decayCoefficient" placeholder="0.5">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">系数</span></template>
            </el-input>
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
            最大返利深度
            <el-tooltip placement="top" content="向上追溯多少层上级进行分发。例如5层表示充值用户的第1~5级上级都能获得返利。层数越多激励越广，但每层金额越少。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <el-select v-model="config.multiLevel.maxDepth" style="width: 200px">
            <el-option v-for="n in 10" :key="n" :label="`${n} 层`" :value="n" />
          </el-select>
        </div>

        <div class="mt-4">
          <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
            失效节点返利处理方式
            <el-tooltip placement="top" content="归平台：失效节点那份不发，也不会转给其他上级；排除后重算：跳过失效节点并按剩余有效上级重新归一化。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <el-radio-group v-model="config.multiLevel.inactiveNodeMode">
            <el-radio-button label="platform">归平台</el-radio-button>
            <el-radio-button label="exclude_recalculate">排除后重算</el-radio-button>
          </el-radio-group>
        </div>

        <!-- 计算结果示例 -->
        <div class="mt-4 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface)] p-4">
          <p class="mb-3 text-sm font-bold">实时预览（基于当前配置，充值100元时各层级分得金额）：</p>
          <div class="flex flex-wrap gap-4">
            <div v-for="item in levelPreview.levels" :key="item.level" class="rounded-lg bg-[var(--sr-surface-low)] px-3 py-2 text-center">
              <div class="text-xs text-[var(--sr-muted)]">L{{ item.level }} 上级</div>
              <div class="text-sm font-bold text-[var(--sr-secondary)]">¥{{ (parseFloat(item.rate) / 100 * 100).toFixed(2) }}</div>
              <div class="text-[10px] text-[var(--sr-muted)]">({{ item.rate }}%)</div>
            </div>
          </div>
          <div class="mt-3 flex items-center justify-between border-t border-[var(--sr-border)] pt-2">
            <span class="text-xs text-[var(--sr-muted)]">归一化后总分发：</span>
            <span class="text-sm font-bold text-[var(--sr-secondary)]">¥{{ (parseFloat(levelPreview.total) / 100 * 100).toFixed(2) }} / ¥{{ config.multiLevel.totalPoolRate }}</span>
          </div>
          <p class="mt-2 text-xs text-[var(--sr-muted)]">* 归一化确保实际分发总额永远不超过返利池（{{ config.multiLevel.totalPoolRate }}元/100元充值）</p>
        </div>
      </AppCard>

      <!-- 提现配置 -->
      <AppCard id="section-withdrawLimit">
        <div class="mb-4">
          <h2 class="text-lg font-bold">提现配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">控制资金流出安全与频率，防止异常提现行为</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
              单次最低提现额
              <el-tooltip placement="top" content="低于此金额的提现申请将被系统自动拒绝。设置过低可能增加小额套利风险和审核压力；设置过高影响用户体验。建议50~200元。">
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
              <el-tooltip placement="top" content="同一用户两次提现申请之间的最小间隔。防止频繁提现增加运营压力。建议24~72小时。设置168小时=7天。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input-number v-model="config.withdrawLimit.cooldownHours" :min="1" :max="168" />
            <span class="ml-2 text-xs text-[var(--sr-muted)]">小时</span>
          </div>
        </div>

        <div class="mt-4 rounded-lg bg-gray-50 p-3">
          <p class="text-xs text-[var(--sr-muted)]">💡 第一版采用人工审核 + 人工支付宝打款模式。冷却时间设置过短会增加审核频率，过长则影响推广员积极性。</p>
        </div>
      </AppCard>

      <!-- 风控配置 -->
      <AppCard id="section-riskControl">
        <div class="mb-4">
          <h2 class="text-lg font-bold">风控配置</h2>
          <p class="text-sm text-[var(--sr-muted)]">防止小号自刷、批量注册、小额套利等异常行为</p>
        </div>

        <div class="rounded-lg border border-[var(--sr-border)] p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <el-icon class="text-red-500" :size="20"><WarningFilled /></el-icon>
              <div>
                <div class="flex items-center gap-1 text-sm font-bold">
                  全局黑名单总开关
                  <el-tooltip placement="top" content="开启后系统将实时检测同设备/IP/支付宝账号，匹配黑名单的用户将被拦截注册、充值和提现操作。关闭后所有黑名单规则暂停。">
                    <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
                  </el-tooltip>
                </div>
                <div class="text-xs text-[var(--sr-muted)]">开启后将应用实时拦截逻辑（设备/IP/账号检测）</div>
              </div>
            </div>
            <el-switch v-model="config.riskControl.blacklistEnabled" />
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-1 flex items-center gap-1 text-sm font-semibold text-[var(--sr-muted)]">
            自动冻结阈值
            <el-tooltip placement="top" content="单个账号在1小时内异常操作次数达到此值时自动冻结。冻结后该账号所有功能暂停，需管理员人工审核解除。建议30~100次/小时。设置过低可能误伤正常用户。">
              <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
            </el-tooltip>
          </label>
          <div class="flex items-center gap-2">
            <el-input-number v-model="config.riskControl.autoFreezeThreshold" :min="1" :max="999" />
            <span class="text-xs text-[var(--sr-muted)]">次/小时</span>
          </div>
          <p class="mt-1 text-xs text-orange-600">⚠ 达到阈值后账号立即锁定，所有返利和提现冻结，需人工审核解除。</p>
        </div>

        <div class="mt-4 rounded-lg border border-[var(--sr-border)] p-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-1 text-sm font-bold">
                防躺平检测
                <el-tooltip placement="top" content="开启后，连续指定天数无充值、余额无减少、无新增下级的用户会被置灰并暂停获得新返利；后续有充值会自动恢复。">
                  <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
                </el-tooltip>
              </div>
              <div class="text-xs text-[var(--sr-muted)]">余额变化只用于判断活跃度，不作为自动发放返利的依据</div>
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
              <el-tooltip placement="top" content="用户被防躺平置灰后，充值成功回调确认的单次金额必须大于等于该值才会恢复返利资格。余额监控只记录活跃，不恢复资格。默认10元。">
                <el-icon class="cursor-help text-blue-400" :size="14"><InfoFilled /></el-icon>
              </el-tooltip>
            </label>
            <el-input v-model="config.riskControl.lieFlatRestoreMinRecharge" :disabled="!config.riskControl.lieFlatEnabled" placeholder="10">
              <template #suffix><span class="text-xs text-[var(--sr-muted)]">元</span></template>
            </el-input>
          </div>
        </div>
      </AppCard>

      <!-- 审计日志提示 -->
      <div class="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3">
        <div class="flex items-center gap-2">
          <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
          <span class="text-sm text-green-700">所有配置更改将自动记录在审计日志中，以供回溯。</span>
        </div>
        <router-link to="/admin/audit-log">
          <el-button type="primary" text size="small">查看完整日志</el-button>
        </router-link>
      </div>
    </div>
  </div>
</template>
