<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { message } from 'ant-design-vue'
import type { Rule } from 'ant-design-vue/es/form'
import { useAuthStore } from '@/stores/auth'

interface FormState {
  username: string
  email: string
  password: string
  passwordConfirm: string
}

const auth = useAuthStore()
const router = useRouter()

const form = reactive<FormState>({ username: '', email: '', password: '', passwordConfirm: '' })
const loading = ref(false)

const validateConfirm = async (_rule: Rule, value: string): Promise<void> => {
  if (value !== form.password) {
    return Promise.reject(new Error('两次密码不一致'))
  }
  return Promise.resolve()
}

const rules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 1, max: 50, message: '用户名长度 1-50', trigger: 'blur' },
  ],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, max: 64, message: '密码长度 6-64', trigger: 'blur' },
  ],
  passwordConfirm: [
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    { validator: validateConfirm, trigger: 'blur' },
  ],
}

async function onSubmit(): Promise<void> {
  loading.value = true
  try {
    await auth.register(form.username, form.email, form.password)
    message.success('注册成功，已自动登录')
    await router.replace('/')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    message.error(err.response?.data?.message || err.message || '注册失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper">
    <a-card class="auth-card" title="注册 NLP 创作平台">
      <a-form :model="form" :rules="rules" layout="vertical" @finish="onSubmit">
        <a-form-item label="用户名" name="username">
          <a-input v-model:value="form.username" placeholder="1-50 字符" autocomplete="username" />
        </a-form-item>
        <a-form-item label="邮箱" name="email">
          <a-input v-model:value="form.email" placeholder="you@example.com" autocomplete="email" />
        </a-form-item>
        <a-form-item label="密码" name="password">
          <a-input-password v-model:value="form.password" placeholder="至少 6 位" autocomplete="new-password" />
        </a-form-item>
        <a-form-item label="确认密码" name="passwordConfirm">
          <a-input-password v-model:value="form.passwordConfirm" placeholder="再次输入" autocomplete="new-password" />
        </a-form-item>
        <a-form-item>
          <a-button type="primary" html-type="submit" block :loading="loading">注册</a-button>
        </a-form-item>
        <div class="auth-footer">
          已有账号？
          <router-link to="/login">去登录</router-link>
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
  width: 460px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.auth-footer {
  text-align: center;
  color: rgba(0, 0, 0, 0.45);
}
</style>
