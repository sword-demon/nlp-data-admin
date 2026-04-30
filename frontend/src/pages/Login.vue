<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { message } from 'ant-design-vue'
import { useAuthStore } from '@/stores/auth'

interface FormState {
  email: string
  password: string
}

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive<FormState>({ email: '', password: '' })
const loading = ref(false)

const rules = {
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, max: 64, message: '密码长度 6-64', trigger: 'blur' },
  ],
}

async function onSubmit(): Promise<void> {
  loading.value = true
  try {
    await auth.login(form.email, form.password)
    message.success('登录成功')
    const redirect = (route.query.redirect as string) || '/'
    await router.replace(redirect)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    message.error(err.response?.data?.message || err.message || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper">
    <a-card class="auth-card" title="登录 NLP 创作平台">
      <a-form :model="form" :rules="rules" layout="vertical" @finish="onSubmit">
        <a-form-item label="邮箱" name="email">
          <a-input v-model:value="form.email" placeholder="you@example.com" autocomplete="email" />
        </a-form-item>
        <a-form-item label="密码" name="password">
          <a-input-password v-model:value="form.password" placeholder="至少 6 位" autocomplete="current-password" />
        </a-form-item>
        <a-form-item>
          <a-button type="primary" html-type="submit" block :loading="loading">登录</a-button>
        </a-form-item>
        <div class="auth-footer">
          还没有账号？
          <router-link to="/register">去注册</router-link>
        </div>
      </a-form>
    </a-card>
  </div>
</template>

<style scoped>
.auth-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f0f5ff 0%, #e6fffb 100%);
}
.auth-card {
  width: 420px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.auth-footer {
  text-align: center;
  color: rgba(0, 0, 0, 0.45);
}
</style>
