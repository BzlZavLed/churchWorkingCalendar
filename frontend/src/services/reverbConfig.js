const resolveBoolean = (value, fallback = false) => {
  if (typeof value === 'boolean') {
    return value
  }

  if (typeof value !== 'string') {
    return fallback
  }

  return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase())
}

export const buildReverbConfig = () => {
  const scheme = import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http')
  const forceTLS = resolveBoolean(import.meta.env.VITE_REVERB_FORCE_TLS, scheme === 'https')
  const wsHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname
  const defaultPort = forceTLS ? 443 : 80
  const configuredPort = Number(import.meta.env.VITE_REVERB_PORT || defaultPort)

  return {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort: configuredPort,
    wssPort: configuredPort,
    forceTLS,
    enabledTransports: forceTLS ? ['wss', 'ws'] : ['ws', 'wss'],
  }
}
