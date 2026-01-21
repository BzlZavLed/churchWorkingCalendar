const bufferToBase64Url = (buffer) => {
  const bytes = new Uint8Array(buffer)
  let binary = ''
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte)
  })
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '')
}

const base64UrlToBuffer = (value) => {
  const padded = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=')
  const binary = atob(padded)
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i)
  }
  return bytes.buffer
}

export const prepareRegistrationOptions = (options) => ({
  ...options,
  challenge: base64UrlToBuffer(options.challenge),
  user: {
    ...options.user,
    id: base64UrlToBuffer(options.user.id),
  },
  excludeCredentials: (options.excludeCredentials || []).map((cred) => ({
    ...cred,
    id: base64UrlToBuffer(cred.id),
  })),
})

export const prepareAuthenticationOptions = (options) => ({
  ...options,
  challenge: base64UrlToBuffer(options.challenge),
  allowCredentials: (options.allowCredentials || []).map((cred) => ({
    ...cred,
    id: base64UrlToBuffer(cred.id),
  })),
})

export const credentialToJSON = (credential) => {
  if (!credential) {
    return null
  }

  const response = credential.response || {}
  const data = {
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: bufferToBase64Url(response.clientDataJSON),
    },
  }

  if (response.attestationObject) {
    data.response.attestationObject = bufferToBase64Url(response.attestationObject)
    if (response.getTransports) {
      data.response.transports = response.getTransports()
    }
  }

  if (response.authenticatorData) {
    data.response.authenticatorData = bufferToBase64Url(response.authenticatorData)
  }

  if (response.signature) {
    data.response.signature = bufferToBase64Url(response.signature)
  }

  if (response.userHandle) {
    data.response.userHandle = bufferToBase64Url(response.userHandle)
  }

  return data
}
