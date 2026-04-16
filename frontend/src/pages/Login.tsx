import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    const res = await login(identifier, password);
    if (res.success) {
      navigate('/dashboard');
    } else {
      setError(res.error || 'Login failed');
    }
    setLoading(false);
  };

  return (
    <div className="auth-wrapper" style={{minHeight: 'calc(100vh - 100px)', display:'flex', alignItems:'center', justifyContent:'center', padding: '20px'}}>
      <div className="glass form-container fade-in" style={{width:'100%', maxWidth:'480px', boxShadow:'0 32px 80px rgba(0,0,0,0.12)'}}>
        <div className="text-center" style={{marginBottom:'2.5rem'}}>
          <div style={{display:'inline-flex', alignItems:'center', justifyContent:'center', width:'64px', height:'64px', borderRadius:'22px', background:'linear-gradient(135deg, rgba(0,113,227,0.12), rgba(0,113,227,0.06))', marginBottom:'1.25rem', border:'1px solid rgba(0,113,227,0.1)'}}>
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0071e3" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          </div>
          <h1 style={{fontSize:'2rem', fontWeight:800, letterSpacing:'-0.03em', margin:0}}>Welcome Back</h1>
          <p style={{color:'var(--text-muted)', fontSize:'1.05rem', marginTop:'0.5rem', fontWeight:500}}>Access your safe campus marketplace</p>
        </div>

        {error && (
          <div className="alert alert-error fade-in" style={{textAlign:'center', marginBottom:'1.5rem'}}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{verticalAlign:'middle',marginRight:'4px'}}><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Email or Username</label>
            <input type="text" className="form-control" placeholder="Enter your identifier" required autoComplete="username" value={identifier} onChange={e => setIdentifier(e.target.value)} />
          </div>
          <div className="form-group" style={{marginBottom:'2rem'}}>
            <label>Password</label>
            <input type="password" className="form-control" placeholder="••••••••" required value={password} onChange={e => setPassword(e.target.value)} />
          </div>
          <button type="submit" className="btn btn-primary" disabled={loading} style={{width:'100%', justifyContent:'center', padding:'1.1rem', fontSize:'1.05rem', fontWeight:700, boxShadow:'0 10px 30px rgba(0,113,227,0.2)'}}>
            {loading ? 'Signing In...' : 'Sign In'}
          </button>
        </form>

        <div style={{marginTop:'2rem', paddingTop:'1.5rem', borderTop:'1px solid rgba(0,0,0,0.06)', textAlign:'center'}}>
          <p style={{fontSize:'0.95rem', color:'var(--text-muted)', margin:0}}>
            Don't have an account? <Link to="/register" style={{color:'var(--primary)', fontWeight:700}}>Join now</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
