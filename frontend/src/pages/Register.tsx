import React, { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const initialMode = searchParams.get('mode') === 'seller' ? 'seller' : 'buyer';
  
  const [mode, setMode] = useState(initialMode);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    
    if (!termsAccepted) {
      setError("You must accept the Terms & Conditions.");
      return;
    }

    setLoading(true);
    const form = e.target as HTMLFormElement;
    const formData = new FormData(form);
    formData.append('mode', mode);

    const hallResidence = String(formData.get('hall_residence') || '').trim();
    if (hallResidence) {
      formData.set('hall', hallResidence);
    }

    // Basic validation
    const p1 = formData.get('password') as string;
    if (p1.length < 6) {
      setError("Password must be at least 6 characters.");
      setLoading(false);
      return;
    }

    const res = await register(formData);
    if (res.success) {
      if ((res as any).isAdmin) {
        navigate('/admin');
      } else {
        navigate('/dashboard');
      }
    } else {
      setError(res.error || 'Registration failed');
    }
    setLoading(false);
  };

  return (
    <div className="auth-wrapper" style={{minHeight: 'calc(100vh - 100px)', display:'flex', alignItems:'center', justifyContent:'center', padding: '20px'}}>
      <div className="glass form-container fade-in" style={{width:'100%', maxWidth:'680px', boxShadow:'0 32px 80px rgba(0,0,0,0.12)', borderRadius:'32px'}}>
        
        <div className="text-center" style={{marginBottom:'2rem'}}>
          <div style={{display:'inline-flex', alignItems:'center', justifyContent:'center', width:'64px', height:'64px', borderRadius:'22px', background:'linear-gradient(135deg, rgba(0,113,227,0.12), rgba(0,113,227,0.06))', marginBottom:'1.25rem', border:'1px solid rgba(0,113,227,0.1)'}}>
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0071e3" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
          </div>
          <h1 style={{fontSize:'2rem', fontWeight:800, letterSpacing:'-0.03em', margin:0}}>Create Account</h1>
          <p style={{color:'var(--text-muted)', fontSize:'1.05rem', marginTop:'0.4rem', fontWeight:500}}>Join your university marketplace today</p>
        </div>

        {/* Mode Tabs */}
        <div style={{display:'flex', gap:'0.5rem', marginBottom:'2.5rem', background:'rgba(0,0,0,0.04)', padding:'6px', borderRadius:'18px', border:'1px solid rgba(0,0,0,0.04)'}}>
          <button type="button" onClick={() => setMode('buyer')} style={{flex:1, borderRadius:'14px', padding:'0.75rem', textAlign:'center', fontWeight:700, fontSize:'0.9rem', transition:'all 0.25s cubic-bezier(0.2, 0, 0, 1)', border:'none', background: mode==='buyer' ? '#fff' : 'transparent', color: mode==='buyer' ? '#0071e3' : 'var(--text-muted)', boxShadow: mode==='buyer' ? '0 4px 12px rgba(0,0,0,0.1)' : 'none', cursor:'pointer'}}>
            🛒 Buyer
          </button>
          <button type="button" onClick={() => setMode('seller')} style={{flex:1, borderRadius:'14px', padding:'0.75rem', textAlign:'center', fontWeight:700, fontSize:'0.9rem', transition:'all 0.25s cubic-bezier(0.2, 0, 0, 1)', border:'none', background: mode==='seller' ? '#fff' : 'transparent', color: mode==='seller' ? '#0071e3' : 'var(--text-muted)', boxShadow: mode==='seller' ? '0 4px 12px rgba(0,0,0,0.1)' : 'none', cursor:'pointer'}}>
            🏪 Seller
          </button>
        </div>

        {error && (
          <div className="alert alert-error fade-in" style={{textAlign:'center', marginBottom:'2rem'}}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{verticalAlign:'middle',marginRight:'4px'}}><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <input type="hidden" name="role" value={mode} />
          <div style={{display:'none'}}><input type="text" name="website" tabIndex={-1} /></div>

          <div className="form-row">
            <div className="form-group">
              <label>Username *</label>
              <input type="text" name="username" className="form-control" required />
            </div>
            <div className="form-group">
              <label>Email Address *</label>
              <input type="email" name="email" className="form-control" required />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>Password *</label>
              <input type="password" name="password" className="form-control" required minLength={6} />
            </div>
            <div className="form-group">
              <label>Referral Code (Optional)</label>
              <input type="text" name="referral_code" className="form-control" placeholder="Code" />
            </div>
          </div>

          <div className="form-group" style={{position:'relative'}}>
            <label>Select Your Faculty *</label>
            <select name="faculty" className="form-control" required>
                <option value="">Select Faculty...</option>
                <option value="Faculty of Applied Arts and Technology">Faculty of Applied Arts and Technology</option>
                <option value="Faculty of Applied Sciences">Faculty of Applied Sciences</option>
                <option value="Faculty of Engineering">Faculty of Engineering</option>
                <option value="Faculty of Business Studies">Faculty of Business Studies</option>
                <option value="Faculty of Built and Natural Environment">Faculty of Built and Natural Environment</option>
                <option value="Faculty of Health and Allied Sciences">Faculty of Health and Allied Sciences</option>
                <option value="Faculty of Maritime and Nautical Studies">Faculty of Maritime and Nautical Studies</option>
                <option value="Faculty of Media Technology and Liberal Studies">Faculty of Media Technology and Liberal Studies</option>
            </select>
          </div>

          {mode === 'seller' && (
            <>
              <div className="form-row">
                <div className="form-group" style={{position:'relative'}}>
                  <label>Department *</label>
                  <input type="text" name="department" className="form-control" required placeholder="Your department" />
                </div>
                <div className="form-group">
                  <label>Academic Level *</label>
                  <select name="level" className="form-control" required>
                    <option value="">Select Level</option>
                    <option value="100">Level 100</option>
                    <option value="200">Level 200</option>
                    <option value="300">Level 300</option>
                    <option value="400">Level 400</option>
                    <option value="BTech">BTech</option>
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group" style={{position:'relative'}}>
                  <label>Hall / Residence</label>
                  <input type="text" name="hall_residence" className="form-control" placeholder="Your residence" />
                </div>
                <div className="form-group">
                  <label>Phone Number *</label>
                  <input type="tel" name="phone" className="form-control" required placeholder="024XXXXXXX" pattern="(0[0-9]{9}|\+233[0-9]{9})" />
                </div>
              </div>
            </>
          )}

          <div className="form-group" style={{display:'flex', gap:'0.75rem', alignItems:'flex-start', margin:'2rem 0', background:'rgba(0,113,227,0.03)', padding:'1.25rem', borderRadius:'20px', border:'1px solid rgba(0,113,227,0.08)'}}>
            <input type="checkbox" checked={termsAccepted} onChange={e => setTermsAccepted(e.target.checked)} required style={{width:'20px', height:'20px', marginTop:'3px', cursor:'pointer'}} />
            <label style={{fontSize:'0.9rem', color:'var(--text-main)', lineHeight:1.5, fontWeight:500, margin:0}}>
              I have read and agree to the <a href="#" onClick={(e) => { e.preventDefault(); (window as any).openTermsModal?.(); }} style={{color:'var(--primary)', fontWeight:800, textDecoration:'underline'}}>Terms & Conditions</a>.
            </label>
          </div>

          <button type="submit" disabled={!termsAccepted || loading} className="btn btn-primary" style={{width:'100%', justifyContent:'center', padding:'1.1rem', fontSize:'1.1rem', fontWeight:700, opacity: (!termsAccepted || loading) ? 0.5 : 1, cursor: (!termsAccepted || loading) ? 'not-allowed' : 'pointer'}}>
            {loading ? 'Creating Account...' : 'Create Account'}
          </button>
        </form>
        
        <div style={{marginTop:'2rem', paddingTop:'1.5rem', borderTop:'1px solid rgba(0,0,0,0.06)', textAlign:'center'}}>
          <p style={{fontSize:'0.95rem', color:'var(--text-muted)', margin:0}}>
            Already have an account? <Link to="/login" style={{color:'var(--primary)', fontWeight:700}}>Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
