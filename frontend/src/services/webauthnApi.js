import { apiClient } from './authApi'

export const webauthnApi = {
  async registerOptions(payload) {
    const { data } = await apiClient.post('/auth/webauthn/register/options', payload)
    return data
  },
  async registerVerify(payload) {
    const { data } = await apiClient.post('/auth/webauthn/register/verify', payload)
    return data
  },
  async authenticateOptions(payload) {
    const { data } = await apiClient.post('/auth/webauthn/authenticate/options', payload)
    return data
  },
  async authenticateVerify(payload) {
    const { data } = await apiClient.post('/auth/webauthn/authenticate/verify', payload)
    return data
  },
}
