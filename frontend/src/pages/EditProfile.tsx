import React, { useState, useCallback } from 'react';
import Cropper from 'react-easy-crop';
import getCroppedImg from '../utils/cropImage';
import { useAuth } from '../contexts/AuthContext';
import { users } from '../services/api';
import { assetUrl } from '../utils/assetUrl';

export default function EditProfile() {
  const { user, isSeller, refreshUser } = useAuth();
  const [loading, setLoading] = useState(false);
  const [msg, setMsg] = useState({ type: '', text: '' });
  
  // Cropper states
  const [imageSrc, setImageSrc] = useState<string | null>(null);
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
  const [showCropper, setShowCropper] = useState(false);
  const [croppedBlob, setCroppedBlob] = useState<Blob | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);

  const onCropComplete = useCallback((croppedArea: any, croppedAreaPixels: any) => {
    setCroppedAreaPixels(croppedAreaPixels);
  }, []);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      const reader = new FileReader();
      reader.addEventListener('load', () => {
        setImageSrc(reader.result?.toString() || null);
        setShowCropper(true);
      });
      reader.readAsDataURL(file);
    }
  };

  const handleCropSave = async () => {
    try {
      if (imageSrc && croppedAreaPixels) {
        const blob = await getCroppedImg(imageSrc, croppedAreaPixels, 0);
        if (blob) {
          setCroppedBlob(blob);
          setPreviewUrl(URL.createObjectURL(blob));
          setShowCropper(false);
        }
      }
    } catch (e) {
      console.error(e);
      setMsg({ type: 'error', text: 'Failed to crop image.' });
    }
  };

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
      if (croppedBlob) {
        const picFormData = new FormData();
        picFormData.append('profile_pic', croppedBlob, 'profile.jpg');
        await users.uploadProfilePic(picFormData);
      } else {
        const profilePic = formData.get('profile_pic');
        if (profilePic instanceof File && profilePic.size > 0) {
          const picFormData = new FormData();
          picFormData.append('profile_pic', profilePic);
          await users.uploadProfilePic(picFormData);
        }
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
            {previewUrl ? (
                <img src={previewUrl} className="profile-pic profile-pic-lg mb-2 viewable-image" alt="Profile Preview" style={{width:'100px', height:'100px', borderRadius:'50%', objectFit:'cover', margin:'0 auto', cursor:'pointer'}} />
            ) : user.profile_pic ? (
                <img src={assetUrl('uploads/' + user.profile_pic)} className="profile-pic profile-pic-lg mb-2 viewable-image" alt="Profile" style={{width:'100px', height:'100px', borderRadius:'50%', objectFit:'cover', margin:'0 auto', cursor:'pointer'}} />
            ) : (
                <div style={{width:'100px', height:'100px', borderRadius:'50%', background:'var(--border)', margin:'0 auto', display:'flex', alignItems:'center', justifyContent:'center'}}>User</div>
            )}
            <br />
            <label>Profile Photo</label>
            <input type="file" name="profile_pic" className="form-control" accept="image/*" onChange={handleFileChange} onClick={(e) => (e.currentTarget.value = '')} />
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
            All profile changes, including profile pictures, require admin approval before becoming visible.
          </div>

          <button type="submit" disabled={loading} className="btn btn-primary" style={{width:'100%',justifyContent:'center'}}>
            {loading ? 'Submitting...' : 'Submit Changes for Approval'}
          </button>
        </form>
      </div>

      {/* Cropper Modal */}
      {showCropper && (
        <div style={{position:'fixed', inset:0, zIndex:100000, background:'rgba(0,0,0,0.85)', backdropFilter:'blur(8px)', display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center'}}>
          <div style={{position:'relative', width:'90%', maxWidth:'600px', height:'60vh', background:'#111', borderRadius:'16px', overflow:'hidden', boxShadow:'0 24px 60px rgba(0,0,0,0.4)'}}>
            {imageSrc && (
              <Cropper
                image={imageSrc}
                crop={crop}
                zoom={zoom}
                aspect={1}
                cropShape="round"
                showGrid={false}
                onCropChange={setCrop}
                onCropComplete={onCropComplete}
                onZoomChange={setZoom}
              />
            )}
          </div>
          
          <div style={{width:'90%', maxWidth:'600px', background:'#fff', padding:'1.5rem', borderRadius:'0 0 16px 16px', display:'flex', flexDirection:'column', gap:'1rem'}}>
            <div style={{display:'flex', alignItems:'center', gap:'1rem'}}>
              <span style={{fontSize:'1.2rem'}}>🔍</span>
              <input 
                type="range" 
                value={zoom} 
                min={1} 
                max={3} 
                step={0.1} 
                aria-labelledby="Zoom" 
                onChange={(e) => setZoom(Number(e.target.value))} 
                style={{flex:1}} 
              />
            </div>
            <div style={{display:'flex', justifyContent:'flex-end', gap:'0.75rem'}}>
              <button className="btn btn-outline" onClick={() => setShowCropper(false)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleCropSave} style={{fontWeight:700}}>Crop & Save</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
