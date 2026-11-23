import {apiClient} from './authApi'

// Fetch through the configured API connection, then decode a local blob.
// Drawing the absolute server URL directly can taint canvas on localhost.
export async function churchLogoImage(churchId, logoUrl) {
 const version=new URL(logoUrl,window.location.origin).searchParams.get('v')
 const {data}=await apiClient.get('/public/churches/'+churchId+'/visitor-logo',{params:version?{v:version}:{},responseType:'blob'})
 const objectUrl=URL.createObjectURL(data)
 try{
  const image=new Image()
  image.src=objectUrl
  await image.decode()
  if(!image.naturalWidth||!image.naturalHeight)throw new Error('Invalid church logo dimensions')
  const canvas=document.createElement('canvas')
  const ratio=Math.min(1,1200/image.naturalWidth,1200/image.naturalHeight)
  canvas.width=Math.max(1,Math.round(image.naturalWidth*ratio))
  canvas.height=Math.max(1,Math.round(image.naturalHeight*ratio))
  canvas.getContext('2d').drawImage(image,0,0,canvas.width,canvas.height)
  return {png:canvas.toDataURL('image/png'),width:canvas.width,height:canvas.height}
 }finally{URL.revokeObjectURL(objectUrl)}
}
