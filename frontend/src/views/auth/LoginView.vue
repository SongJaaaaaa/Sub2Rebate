<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const formRef = ref<FormInstance>()
const form = reactive({ account: '', password: '' })
const submitting = ref(false)

const rules: FormRules = {
  account: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

const onSubmit = async () => {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    await auth.login(form)
    const defaultPath = auth.user?.role === 'admin' ? '/admin/dashboard' : '/dashboard'
    const redirect = (route.query.redirect as string) || defaultPath
    await router.replace(redirect)
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : '登录失败，请重试'
    ElMessage.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="w-full max-w-md rounded-2xl border border-[var(--sr-border)] bg-white p-8 shadow-card">
    <div class="mb-8">
      <h1 class="text-3xl font-bold">Sub2Rebate</h1>
      <p class="mt-2 text-sm text-[var(--sr-muted)]">登录分销返利用户中心</p>
    </div>

    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
      <el-form-item label="账号" prop="account">
        <el-input
          v-model="form.account"
          placeholder="用户名或邮箱"
          autocomplete="username"
          :disabled="submitting"
        />
      </el-form-item>
      <el-form-item label="密码" prop="password">
        <el-input
          v-model="form.password"
          type="password"
          placeholder="请输入密码"
          autocomplete="current-password"
          show-password
          :disabled="submitting"
        />
      </el-form-item>
      <el-button
        native-type="submit"
        class="mt-2 w-full"
        type="primary"
        size="large"
        :loading="submitting"
      >
        {{ submitting ? '登录中...' : '登录' }}
      </el-button>
    </el-form>
  </section>
</template>
