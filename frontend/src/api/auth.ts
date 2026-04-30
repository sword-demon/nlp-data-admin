import { http } from './client'
import type { ApiResponse } from './client'

export interface AuthUser {
  id: number
  username: string
  email: string
  avatar: string | null
  role: string
  vip_level: string
  vip_expired_at: string | null
  quota_total: number
  quota_used: number
}

export interface AuthTokenPayload {
  token: string
  token_type: string
  expires_at: number
  ttl: number
  user: AuthUser
}

export interface RegisterDto {
  username: string
  email: string
  password: string
}

export interface LoginDto {
  email: string
  password: string
}

export async function register(dto: RegisterDto): Promise<AuthTokenPayload> {
  const { data } = await http.post<ApiResponse<AuthTokenPayload>>('/auth/register', dto)
  return data.data
}

export async function login(dto: LoginDto): Promise<AuthTokenPayload> {
  const { data } = await http.post<ApiResponse<AuthTokenPayload>>('/auth/login', dto)
  return data.data
}

export async function refreshToken(): Promise<AuthTokenPayload> {
  const { data } = await http.post<ApiResponse<AuthTokenPayload>>('/auth/refresh')
  return data.data
}

export async function logout(): Promise<void> {
  await http.post<ApiResponse<null>>('/auth/logout')
}

export async function getMe(): Promise<AuthUser> {
  const { data } = await http.get<ApiResponse<AuthUser>>('/auth/me')
  return data.data
}
