import React, { useState, useEffect } from 'react';
import { apiFetch } from '../../services/api';

export default function AdminUsers() {
  const [users, setUsers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  const fetchUsers = async () => {
    setLoading(true);
    try {
      const res = await apiFetch(`admin/users?filter=${filter}`);
      if (res.success) {
        setUsers(res.users);
      } else {
        mockUsers(filter);
      }
    } catch (e) {
        mockUsers(filter);
    } finally {
      setLoading(false);
    }
  };

  const mockUsers = (f: string) => {
      const allMocks = [
        { id: 1, username: 'admin', email: 'admin@uni.edu', role: 'admin', tier: null, faculty: 'Engineering', balance: 0, verified: 1, suspended: 0, created_at: '2026-01-10' },
        { id: 2, username: 'seller1', email: 'seller1@uni.edu', role: 'seller', tier: 'premium', faculty: 'Business', balance: 1450, verified: 1, suspended: 0, created_at: '2026-02-14' },
        { id: 3, username: 'buyer1', email: 'buyer@uni.edu', role: 'buyer', tier: null, faculty: 'Arts', balance: 200, verified: 0, suspended: 0, created_at: '2026-03-20' },
        { id: 4, username: 'spammer', email: 'spam@uni.edu', role: 'seller', tier: 'basic', faculty: 'Science', balance: 10, verified: 0, suspended: 1, created_at: '2026-04-10' },
      ];
      if (f === 'sellers') setUsers(allMocks.filter(u => u.role==='seller'));
      else if (f === 'buyers') setUsers(allMocks.filter(u => u.role==='buyer'));
      else if (f === 'admins') setUsers(allMocks.filter(u => u.role==='admin'));
      else setUsers(allMocks);
  }

  useEffect(() => {
    fetchUsers();
  }, [filter]);

  const handleAction = async (id: number, action: string) => {
      if (['delete', 'suspend'].includes(action)) {
          if(!window.confirm(`Are you sure you want to ${action} user #${id}?`)) return;
      }
      try {
          const res = await apiFetch('admin/users/action', { method: 'POST', body: { id, action } });
          if(res.success) {
              alert(res.message);
              fetchUsers();
          } else {
              alert('Action failed. Data may be mocked.');
          }
      } catch (e) {
          alert(`Performing ${action} on user ${id} (Mocked Success)`);
          fetchUsers();
      }
  }

  return (
    <div className="container" style={{padding:'2rem 4%', maxWidth:'none'}}>
      <h2 className="mb-2" style={{display:'flex', alignItems:'center', gap:'0.5rem'}}>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        User Management
      </h2>

      <div style={{display:'flex', gap:'8px', marginBottom:'1.5rem'}}>
        {['all', 'sellers', 'buyers', 'admins'].map(f => (
            <button 
                key={f} 
                onClick={() => setFilter(f)} 
                className={`btn btn-sm ${filter === f ? 'btn-primary' : 'btn-outline'}`}
                style={{textTransform:'capitalize'}}
            >
                {f}
            </button>
        ))}
      </div>

      <div className="glass fade-in" style={{padding:'1.5rem', overflowX:'auto'}}>
          {loading ? (
              <div style={{padding:'2rem', textAlign:'center'}}>Loading users...</div>
          ) : (
            <table style={{width:'100%', textAlign:'left', borderCollapse:'collapse'}}>
                <thead>
                    <tr style={{borderBottom:'2px solid var(--border)', textAlign:'left'}}>
                        <th style={{padding:'0.5rem'}}>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Tier</th>
                        <th>Faculty</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {users.map(u => (
                        <tr key={u.id} style={{borderBottom:'1px solid rgba(0,0,0,0.05)'}}>
                            <td style={{padding:'0.75rem 0.5rem'}}>{u.id}</td>
                            <td style={{fontWeight:600}}>{u.username}</td>
                            <td>{u.email}</td>
                            <td><span className={`badge ${u.role==='admin'?'badge-gold':(u.role==='seller'?'badge-blue':'')}`}>{u.role}</span></td>
                            <td>{u.role === 'seller' ? <span className={`badge ${u.tier==='premium'?'badge-gold':'bg-gray-200 text-black'}`}>{u.tier || 'basic'}</span> : '—'}</td>
                            <td style={{fontSize:'0.8rem', maxWidth:'140px', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap'}}>{u.faculty || '—'}</td>
                            <td>₵{u.balance?.toFixed(2)}</td>
                            <td>
                                {u.suspended ? <span className="badge badge-rejected">⛔ Suspended</span> :
                                 u.verified ? <span style={{color:'var(--success)', fontWeight:'bold'}}>✓ Verified</span> :
                                 <span className="text-muted">—</span>}
                            </td>
                            <td style={{fontSize:'0.8rem'}}>{u.created_at}</td>
                            <td>
                                {u.role !== 'admin' ? (
                                    <div style={{display:'flex', gap:'4px', flexWrap:'wrap'}}>
                                        {!u.verified && <button onClick={()=>handleAction(u.id, 'verify')} className="btn btn-success btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem'}}>Verify</button>}
                                        
                                        {u.role === 'seller' && u.tier === 'basic' && (
                                            <>
                                                <button onClick={()=>handleAction(u.id, 'upgrade_pro')} className="btn btn-outline btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem'}}>🥈 Pro</button>
                                                <button onClick={()=>handleAction(u.id, 'upgrade_premium')} className="btn btn-gold btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem', background:'var(--gold)', color:'#fff', border:'none'}}>⭐ Premium</button>
                                            </>
                                        )}
                                        {u.role === 'seller' && u.tier === 'premium' && (
                                            <button onClick={()=>handleAction(u.id, 'downgrade_basic')} className="btn btn-outline btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem'}}>D-grade</button>
                                        )}

                                        {u.suspended ? (
                                            <button onClick={()=>handleAction(u.id, 'reactivate')} className="btn btn-success btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem'}}>✅ Reactivate</button>
                                        ) : (
                                            <button onClick={()=>handleAction(u.id, 'suspend')} className="btn btn-outline btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem', color:'#ff9500', borderColor:'rgba(255,149,0,0.3)'}}>⏸ Suspend</button>
                                        )}

                                        <button onClick={()=>handleAction(u.id, 'delete')} className="btn btn-danger btn-sm" style={{padding:'2px 8px', fontSize:'0.75rem', background:'var(--danger)', color:'#fff', border:'none'}}>Delete</button>
                                    </div>
                                ) : <span className="text-muted">Protected</span>}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
          )}
      </div>
    </div>
  );
}
