<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { getProfile, changePassword } from '@/api/account'
import { saveWithdrawAccount, getWithdrawAccount } from '@/api/withdraw'
import { money } from '@/utils/money'
import type { AccountProfile } from '@/types/user'
import type { WithdrawAccount } from '@/types/withdraw'

const profile = ref<AccountProfile | null>(null)
const loading = ref(false)

// 支付宝绑定
const alipayAccount = ref<WithdrawAccount | null>(null)
const alipayFormRef = ref<FormInstance>()
const alipayForm = reactive({ realName: '', accountNo: '' })
const alipaySubmitting = ref(false)
const showAlipayForm = ref(false)

const alipayRules: FormRules = {
  realName: [{ required: true, message: '请输入真实姓名', trigger: 'blur' }],
  accountNo: [{ required: true, message: '请输入支付宝账号', trigger: 'blur' }],
}

// 修改密码
const pwdFormRef = ref<FormInstance>()
const pwdForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' })
const pwdSubmitting = ref(false)

const pwdRules: FormRules = {
  oldPassword: [{ required: true, message: '请输入当前密码', trigger: 'blur' }],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码至少 6 位', trigger: 'blur' },
  ],
  confirmPassword: [
    { required: true, message: '请确认新密码', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        if (value !== pwdForm.newPassword) {
          callback(new Error('两次密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur',
    },
  ],
}

const onSaveAlipay = async () => {
  if (!alipayFormRef.value) return
  const valid = await alipayFormRef.value.validate().catch(() => false)
  if (!valid) return

  alipaySubmitting.value = true
  try {
    const res = await saveWithdrawAccount({
      type: 'alipay',
      realName: alipayForm.realName,
      accountNo: alipayForm.accountNo,
    })
    if (res.code === 0) {
      alipayAccount.value = res.data.account
      showAlipayForm.value = false
      ElMessage.success('支付宝账号保存成功')
    }
  } catch {
    ElMessage.error('保存失败，请重试')
  } finally {
    alipaySubmitting.value = false
  }
}

const onEditAlipay = () => {
  if (alipayAccount.value) {
    alipayForm.realName = alipayAccount.value.realName
    alipayForm.accountNo = alipayAccount.value.accountNo
  }
  showAlipayForm.value = true
}

const onChangePassword = async () => {
  if (!pwdFormRef.value) return
  const valid = await pwdFormRef.value.validate().catch(() => false)
  if (!valid) return

  pwdSubmitting.value = true
  try {
    const res = await changePassword({
      oldPassword: pwdForm.oldPassword,
      newPassword: pwdForm.newPassword,
    })
    if (res.code === 0) {
      ElMessage.success('密码修改成功')
      pwdForm.oldPassword = ''
      pwdForm.newPassword = ''
      pwdForm.confirmPassword = ''
      pwdFormRef.value.resetFields()
    }
  } catch {
    ElMessage.error('修改失败，请检查当前密码是否正确')
  } finally {
    pwdSubmitting.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    const [profileRes, accountRes] = await Promise.all([getProfile(), getWithdrawAccount()])
    if (profileRes.code === 0) profile.value = profileRes.data
    if (accountRes.code === 0) alipayAccount.value = accountRes.data.account
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="账户设置" description="管理个人信息、提现账号和账户安全。" />

    <div v-loading="loading" class="grid gap-6 lg:grid-cols-2">
      <!-- 个人信息 -->
      <AppCard>
        <h2 class="mb-4 text-lg font-bold">个人信息</h2>
        <el-descriptions :column="1" border v-if="profile">
          <el-descriptions-item label="用户名">{{ profile.user.username }}</el-descriptions-item>
          <el-descriptions-item label="昵称">{{ profile.user.nickname }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ profile.user.email }}</el-descriptions-item>
          <el-descriptions-item label="角色">{{ profile.user.role === 'admin' ? '管理员' : '普通用户' }}</el-descriptions-item>
          <el-descriptions-item label="注册时间">{{ profile.user.createdAt }}</el-descriptions-item>
        </el-descriptions>
      </AppCard>

      <!-- 邀请信息 -->
      <AppCard>
        <h2 class="mb-4 text-lg font-bold">邀请关系</h2>
        <el-descriptions :column="1" border v-if="profile">
          <el-descriptions-item label="Sub2API 邀请码">
            <span class="font-bold text-[var(--sr-secondary)]">{{ profile.invite.sub2ApiAffCode || '-' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="Sub2API 邀请链接">
            <a
              v-if="profile.invite.sub2ApiInviteUrl"
              class="break-all text-[var(--sr-primary)] hover:underline"
              :href="profile.invite.sub2ApiInviteUrl"
              target="_blank"
              rel="noreferrer"
            >
              {{ profile.invite.sub2ApiInviteUrl }}
            </a>
            <span v-else>-</span>
          </el-descriptions-item>
          <el-descriptions-item label="Sub2API 页面">
            <a
              class="break-all text-[var(--sr-primary)] hover:underline"
              :href="profile.invite.sub2ApiAffiliatePageUrl"
              target="_blank"
              rel="noreferrer"
            >
              {{ profile.invite.sub2ApiAffiliatePageUrl }}
            </a>
          </el-descriptions-item>
          <el-descriptions-item label="上级用户">{{ profile.invite.parentNickname || '无' }}</el-descriptions-item>
          <el-descriptions-item label="层级深度">{{ profile.invite.depth }}</el-descriptions-item>
        </el-descriptions>
      </AppCard>

      <!-- 余额 -->
      <AppCard class="lg:col-span-2">
        <h2 class="mb-4 text-lg font-bold">余额概览</h2>
        <div v-if="profile" class="grid gap-4 md:grid-cols-4">
          <div class="rounded-lg bg-[var(--sr-surface-low)] p-4 text-center">
            <div class="text-xs font-semibold text-[var(--sr-muted)]">可提现</div>
            <div class="mt-2 text-xl font-bold">{{ money(profile.balance.availableAmount) }}</div>
          </div>
          <div class="rounded-lg bg-[var(--sr-surface-low)] p-4 text-center">
            <div class="text-xs font-semibold text-[var(--sr-muted)]">冻结中</div>
            <div class="mt-2 text-xl font-bold">{{ money(profile.balance.frozenAmount) }}</div>
          </div>
          <div class="rounded-lg bg-[var(--sr-surface-low)] p-4 text-center">
            <div class="text-xs font-semibold text-[var(--sr-muted)]">累计总额</div>
            <div class="mt-2 text-xl font-bold">{{ money(profile.balance.totalAmount) }}</div>
          </div>
          <div class="rounded-lg bg-[var(--sr-surface-low)] p-4 text-center">
            <div class="text-xs font-semibold text-[var(--sr-muted)]">已提现</div>
            <div class="mt-2 text-xl font-bold">{{ money(profile.balance.withdrawnAmount) }}</div>
          </div>
        </div>
      </AppCard>

      <!-- 提现账号 -->
      <AppCard>
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-bold">提现账号</h2>
          <el-button v-if="alipayAccount && !showAlipayForm" type="primary" text size="small" @click="onEditAlipay">
            修改
          </el-button>
        </div>

        <!-- 已绑定展示 -->
        <div v-if="alipayAccount && !showAlipayForm">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="类型">支付宝</el-descriptions-item>
            <el-descriptions-item label="真实姓名">{{ alipayAccount.realName }}</el-descriptions-item>
            <el-descriptions-item label="账号">{{ alipayAccount.accountNo }}</el-descriptions-item>
            <el-descriptions-item label="更新时间">{{ alipayAccount.updatedAt }}</el-descriptions-item>
          </el-descriptions>
        </div>

        <!-- 绑定/编辑表单 -->
        <div v-else>
          <p class="mb-4 text-sm text-[var(--sr-muted)]">绑定支付宝账号后可发起提现，请确保姓名与支付宝实名一致。</p>
          <el-form ref="alipayFormRef" :model="alipayForm" :rules="alipayRules" label-position="top">
            <el-form-item label="真实姓名" prop="realName">
              <el-input v-model="alipayForm.realName" placeholder="请输入支付宝实名" :disabled="alipaySubmitting" />
            </el-form-item>
            <el-form-item label="支付宝账号" prop="accountNo">
              <el-input v-model="alipayForm.accountNo" placeholder="手机号或邮箱" :disabled="alipaySubmitting" />
            </el-form-item>
            <div class="flex gap-2">
              <el-button type="primary" :loading="alipaySubmitting" @click="onSaveAlipay">保存</el-button>
              <el-button v-if="alipayAccount" @click="showAlipayForm = false">取消</el-button>
            </div>
          </el-form>
        </div>
      </AppCard>

      <!-- 修改密码 -->
      <AppCard>
        <h2 class="mb-4 text-lg font-bold">修改密码</h2>
        <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-position="top">
          <el-form-item label="当前密码" prop="oldPassword">
            <el-input v-model="pwdForm.oldPassword" type="password" show-password placeholder="请输入当前密码" :disabled="pwdSubmitting" />
          </el-form-item>
          <el-form-item label="新密码" prop="newPassword">
            <el-input v-model="pwdForm.newPassword" type="password" show-password placeholder="至少 6 位" :disabled="pwdSubmitting" />
          </el-form-item>
          <el-form-item label="确认新密码" prop="confirmPassword">
            <el-input v-model="pwdForm.confirmPassword" type="password" show-password placeholder="再次输入新密码" :disabled="pwdSubmitting" />
          </el-form-item>
          <el-button type="primary" :loading="pwdSubmitting" @click="onChangePassword">确认修改</el-button>
        </el-form>
      </AppCard>
    </div>
  </div>
</template>
