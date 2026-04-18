import React, { useState, useEffect } from 'react';
import api from '../../services/api';

interface Ad {
  id: number;
  title: string;
  image_url: string;
  link_url: string;
  placement: string;
  is_active: number;
  impressions: number;
  clicks: number;
  created_at: string;
}

export default function AdminAds() {
  const [ads, setAds] = useState<Ad[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitLoading, setSubmitLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Form State
  const [title, setTitle] = useState('');
  const [linkUrl, setLinkUrl] = useState('');
  const [imageUrl, setImageUrl] = useState('');
  const [placement, setPlacement] = useState('homepage');
  const [file, setFile] = useState<File | null>(null);

  const fetchAds = async () => {
    try {
      const res = await api.admin.ads.list();
      if (res.success) {
        setAds(res.ads);
      }
    } catch (e: any) {
      setError(e.message || 'Failed to load ads');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAds();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitLoading(true);
    setError('');
    setSuccess('');

    const formData = new FormData();
    formData.append('ad_title', title);
    formData.append('ad_link', linkUrl);
    formData.append('ad_placement', placement);
    if (imageUrl) formData.append('ad_image', imageUrl);
    if (file) formData.append('ad_file', file);

    try {
      const res = await api.admin.ads.create(formData);
      if (res.success) {
        setSuccess('Ad created successfully!');
        setTitle('');
        setLinkUrl('');
        setImageUrl('');
        setFile(null);
        fetchAds();
      } else {
        setError(res.error || 'Failed to create ad');
      }
    } catch (e: any) {
      setError(e.message || 'Error occurred');
    } finally {
        setSubmitLoading(false);
    }
  };

  const handleToggle = async (id: number) => {
    try {
        const res = await api.admin.ads.toggle(id);
        if (res.success) fetchAds();
    } catch (e: any) {
        setError(e.message);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this ad?')) return;
    try {
        const res = await api.admin.ads.delete(id);
        if (res.success) fetchAds();
    } catch (e: any) {
        setError(e.message);
    }
  };

  if (loading) return <div style={{padding:'2rem', textAlign:'center'}}>Loading Ads Manager...</div>;

  return (
    <div className="container" style={{padding:'2rem 4%', maxWidth:'none'}}>
      <h2 className="mb-3" style={{display:'flex', alignItems:'center', gap:'0.5rem'}}>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/>
          </svg>
          Ad Manager
      </h2>

      {error && <div className="alert alert-error fade-in" style={{marginBottom:'1rem'}}>{error}</div>}
      {success && <div className="alert alert-success fade-in" style={{marginBottom:'1rem'}}>{success}</div>}

      <div className="glass fade-in mb-3" style={{padding:'1.5rem'}}>
        <h4 className="mb-2">📢 Create New Ad Placement</h4>
        <form onSubmit={handleSubmit} style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:'1rem'}}>
            <div className="form-group">
                <label>Ad Title *</label>
                <input type="text" className="form-control" required value={title} onChange={e => setTitle(e.target.value)} placeholder="e.g. Back to School Sale" />
            </div>
            <div className="form-group">
                <label>Upload Image</label>
                <input type="file" className="form-control" accept="image/*" onChange={e => setFile(e.target.files?.[0] || null)} />
            </div>
            <div className="form-group">
                <label>Or Image URL</label>
                <input type="url" className="form-control" value={imageUrl} onChange={e => setImageUrl(e.target.value)} placeholder="https://..." />
            </div>
            <div className="form-group">
                <label>Link URL</label>
                <input type="url" className="form-control" value={linkUrl} onChange={e => setLinkUrl(e.target.value)} placeholder="https://..." />
            </div>
            <div className="form-group">
                <label>Placement</label>
                <select className="form-control" value={placement} onChange={e => setPlacement(e.target.value)}>
                    <option value="homepage">Homepage</option>
                    <option value="category">Category Page</option>
                    <option value="product">Product Page</option>
                </select>
            </div>
            <div style={{gridColumn:'1/-1'}}>
                <button type="submit" disabled={submitLoading} className="btn btn-primary">
                    {submitLoading ? 'Creating...' : 'Create Ad'}
                </button>
            </div>
        </form>
      </div>

      <div className="glass fade-in" style={{padding:'1.5rem'}}>
        <h4 className="mb-2">📋 All Ad Placements</h4>
        {ads.length > 0 ? (
          <div style={{overflowX: 'auto'}}>
            <table style={{width:'100%', textAlign:'left', borderCollapse:'collapse'}}>
                <thead>
                    <tr style={{borderBottom:'1px solid var(--border)'}}>
                        <th style={{padding:'1rem'}}>Title</th>
                        <th>Placement</th>
                        <th>Status</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {ads.map(ad => (
                        <tr key={ad.id} style={{borderBottom:'1px solid rgba(0,0,0,0.05)'}}>
                            <td style={{padding:'1rem', fontWeight:600}}>
                                <div style={{display:'flex', alignItems:'center', gap:'10px'}}>
                                    {ad.image_url && (
                                        <img src={ad.image_url.startsWith('http') ? ad.image_url : `${import.meta.env.VITE_API_URL?.replace('/api', '')}/${ad.image_url}`} 
                                             alt={ad.title} 
                                             style={{width:'40px', height:'30px', borderRadius:'4px', objectFit:'cover', border:'1px solid rgba(0,0,0,0.1)'}} 
                                        />
                                    )}
                                    {ad.title}
                                </div>
                            </td>
                            <td><span className="badge badge-blue" style={{fontSize:'0.65rem'}}>{ad.placement}</span></td>
                            <td>
                                {ad.is_active ? 
                                    <span className="badge badge-approved" style={{fontSize:'0.65rem'}}>Active</span> : 
                                    <span className="badge badge-rejected" style={{fontSize:'0.65rem'}}>Paused</span>
                                }
                            </td>
                            <td>{ad.impressions.toLocaleString()}</td>
                            <td>{ad.clicks.toLocaleString()}</td>
                            <td>{ad.impressions > 0 ? ((ad.clicks / ad.impressions) * 100).toFixed(1) + '%' : '0%'}</td>
                            <td style={{display:'flex', gap:'0.4rem', paddingTop:'0.8rem'}}>
                                <button onClick={() => handleToggle(ad.id)} className="btn btn-sm btn-outline">
                                    {ad.is_active ? 'Pause' : 'Activate'}
                                </button>
                                <button onClick={() => handleDelete(ad.id)} className="btn btn-sm btn-danger">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
          </div>
        ) : (
            <p className="text-muted" style={{textAlign:'center', padding:'1.5rem'}}>No ad placements yet. Create your first one above.</p>
        )}
      </div>
    </div>
  );
}
