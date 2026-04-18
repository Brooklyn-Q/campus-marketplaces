import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { users } from '../services/api';

export default function EditProfile() {
  const { user, isSeller, refreshUser } = useAuth();
  const [loading, setLoading] = useState(false);
  const [msg, setMsg] = useState({ type: '', text: '' });

  const faculties = [
    'Faculty of Applied Arts and Technology',
    'Faculty of Applied Sciences',
    'Faculty of Engineering',
    'Faculty of Business Studies',
    'Faculty of Built and Natural Environment',
    'Faculty of Health and Allied Sciences',
    'Faculty of Maritime and Nautical Studies',
    'Faculty of Media Technology and Liberal Studies',
  ];

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMsg({ type: '', text: '' });

    const form = e.target as HTMLFormElement;
    const formData = new FormData(form);
    const body = {
      faculty: String(formData.get('faculty') || ''),
      bio: String(formData.get('bio') || ''),
      department: String(formData.get('department') || ''),
      level: String(formData.get('level') || ''),
      hall: String(formData.get('hall') || ''),
      phone: String(formData.get('phone') || ''),
    };

    try {
      const profilePic = formData.get('profile_pic');

      if (profilePic instanceof File && profilePic.size > 0) {
        const picFormData = new FormData();
        picFormData.append('profile_pic', profilePic);
        await users.uploadProfilePic(picFormData);
      }

      await users.updateProfile(body);
      await refreshUser();
      setMsg({ type: 'success', text: 'Your profile changes have been submitted for admin approval.' });
    } catch (err: any) {
      setMsg({ type: 'error', text: err.message || 'Failed to update profile.' });
    } finally {
      setLoading(false);
    }
  };

  const assetUrl = (path: string | undefined | null) => {
    if (!path) return '';
    if (path.startsWith('uploads/http')) return path.substring(8);
    if (path.startsWith('http')) return path;
    if (path.startsWith('uploads/')) {
      const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/marketplace/backend/api';
      const backendRoot = apiBase.replace(/\/api\/?$/, '');
      return `${backendRoot}/../${path}`;
    }
    return path.startsWith('/') ? path : `/${path}`;
  };

  if (!user) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading...</div>;

  return (
    <div className="container" style={{padding:'2rem 0', display:'flex', justifyContent:'center'}}>
      <div className="glass form-container fade-in" style={{maxWidth:'650px', width:'100%'}}>
        <h2 className="mb-3">Edit Profile</h2>

        {msg.text && (
          <div className={`alert alert-${msg.type}`} style={{marginBottom:'1rem'}}>
            {msg.text}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="form-group text-center">
            {user.profile_pic ? (
                <img src={assetUrl('uploads/' + user.profile_pic)} className="profile-pic profile-pic-lg mb-2" alt="Profile" style={{width:'100px', height:'100px', borderRadius:'50%', objectFit:'cover', margin:'0 auto'}} />
            ) : (
                <div style={{width:'100px', height:'100px', borderRadius:'50%', background:'var(--border)', margin:'0 auto', display:'flex', alignItems:'center', justifyContent:'center'}}>User</div>
            )}
            <br />
            <label>Profile Photo <small style={{color:'var(--text-muted)'}}>(updates instantly via API)</small></label>
            <input type="file" name="profile_pic" className="form-control" accept="image/*" />
          </div>

          <div className="form-group">
            <label>Faculty *</label>
            <select name="faculty" className="form-control" defaultValue={user.faculty || ''} required>
              <option value="">Choose Faculty</option>
              {faculties.map(f => (
                <option key={f} value={f}>{f}</option>
              ))}
            </select>
          </div>

          <div className="form-group">
            <label>Bio / Slogan</label>
            <textarea name="bio" className="form-control" rows={3} defaultValue={user.bio || ''}></textarea>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>Department</label>
              <input type="text" name="department" className="form-control" defaultValue={user.department || ''} />
            </div>
            <div className="form-group">
              <label>Level</label>
              <select name="level" className="form-control" defaultValue={user.level || ''}>
                <option value="">Select</option>
                {['100','200','300','400','BTech'].map(l => (
                    <option key={l} value={l}>{l}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>Hall / Residence</label>
              <input type="text" name="hall" className="form-control" defaultValue={user.hall || ''} />
            </div>
            <div className="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone" className="form-control" defaultValue={user.phone || ''} />
            </div>
          </div>
          
          <div style={{background:'rgba(0,113,227,0.05)', border:'1px solid rgba(0,113,227,0.15)', borderRadius:'12px', padding:'0.8rem 1rem', marginBottom:'1.25rem', fontSize:'0.82rem', color:'var(--text-muted)', display:'flex', alignItems:'center', gap:'0.5rem'}}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0071e3" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Text field changes require admin approval. Image uploads apply immediately.
          </div>

          <button type="submit" disabled={loading} className="btn btn-primary" style={{width:'100%',justifyContent:'center'}}>
            {loading ? 'Submitting...' : 'Submit Changes for Approval'}
          </button>
        </form>
      </div>
    </div>
  );
}
