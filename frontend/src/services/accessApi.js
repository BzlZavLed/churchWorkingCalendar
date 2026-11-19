import { apiClient } from './authApi'
export const accessApi = {
 async current(){return (await apiClient.get('/access')).data},
 async list(church){return (await apiClient.get(`/superadmin/churches/${church}/roles`)).data},
 async create(church,data){return (await apiClient.post(`/superadmin/churches/${church}/roles`,data)).data},
 async update(church,id,data){return (await apiClient.put(`/superadmin/churches/${church}/roles/${id}`,data)).data},
 async assign(church,id,data){return (await apiClient.put(`/superadmin/churches/${church}/users/${id}/role`,data)).data},
}
export const moduleForPath = path => {
 if(path==='/committees')return 'committees'
 if(path==='/calendar')return 'calendar'
 if(path==='/reports')return 'reports'
 if(path==='/objectives')return 'objectives'
 if(path==='/budget')return 'budget'
 if(path==='/inventory')return 'inventory'
 if(path==='/secretary/meetings')return 'meetings'
 if(path==='/meeting-points')return 'meeting_points'
 if(['/greeting','/greeting/attendance'].includes(path))return 'greeting'
 if(path.startsWith('/greeting/'))return 'visitors'
 if(path==='/members')return 'members'
 if(path==='/secretary/departments')return 'departments'
 if(['/secretary/users','/admin/users'].includes(path))return 'users'
 return null
}
