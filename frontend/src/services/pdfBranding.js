import {churchLogoImage} from './churchLogoImage'
import {apiClient} from './authApi'
import {useAuthStore} from '../stores/authStore'
// Return the vertical space reserved for branding so report content cannot overlap.
export async function addChurchPdfLogo(doc,churchId=useAuthStore().user?.church_id){
 if(!churchId)return 0
 const {data}=await apiClient.get('/public/churches/'+churchId+'/visit')
 if(!data.logo_url)return 0
 const image=await churchLogoImage(churchId,data.logo_url)
 const scale=Math.min(40/image.width,18/image.height)
 doc.addImage(image.png,'PNG',14,8,image.width*scale,image.height*scale)
 return 24
}
