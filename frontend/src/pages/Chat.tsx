import React, { useState, useEffect, useRef } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { messages } from '../services/api';

export default function Chat() {
  const { user } = useAuth();
  const [searchParams] = useSearchParams();
  const [conversations, setConversations] = useState<any[]>([]);
  const [activeUser, setActiveUser] = useState<any>(null);
  const [activeThread, setActiveThread] = useState<any[]>([]);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  
  const threadEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const fetchConvos = async () => {
      try {
        const res = await messages.conversations();
        setConversations(res.conversations || []);
        
        // Auto select if user param is passed
        const userId = searchParams.get('user');
        if (userId) {
          const selectedId = parseInt(userId, 10);
          handleSelectUser(selectedId, res.conversations);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchConvos();
    const interval = setInterval(fetchConvos, 5000); // basic polling
    return () => clearInterval(interval);
  }, [searchParams]);

  const handleSelectUser = async (userId: number, currentList: any[] = conversations) => {
    let u = currentList.find((c: any) => c.id === userId);
    if (!u) {
      // Create temporary user shell just to chat
      u = { id: userId, username: 'User ' + userId, profile_pic: null, last_seen: null };
    }
    setActiveUser(u);
    fetchThread(userId);
  };

  const fetchThread = async (userId: number) => {
    try {
      const res = await messages.getThread(userId);
      setActiveThread(res.messages || []);
      setTimeout(() => threadEndRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
    } catch (err) {
      console.error(err);
    }
  };

  const handleSend = async () => {
    if (!message.trim() || !activeUser) return;
    setSending(true);
    try {
      await messages.send(activeUser.id, message);
      setMessage('');
      fetchThread(activeUser.id);
    } catch (err) {
      alert("Failed to send message");
    } finally {
      setSending(false);
    }
  };

  const assetUrl = (path: string) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    if (path.startsWith('uploads/')) {
      const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/marketplace/backend/api';
      const backendRoot = apiBase.replace(/\/api\/?$/, '');
      return `${backendRoot}/../${path}`;
    }
    return path.startsWith('/') ? path : `/${path}`;
  };

  if (loading) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading messages...</div>;

  return (
    <div className="container" style={{padding:'2rem 0', height:'calc(100vh - 150px)'}}>
      <div className="glass chat-container fade-in" style={{height:'100%', display:'flex'}}>
        
        {/* Sidebar */}
        <div className="chat-users" style={{width:'300px', borderRight:'1px solid var(--border)', overflowY:'auto'}}>
          <div style={{padding:'1rem', borderBottom:'1px solid var(--border)', fontWeight:700}}>💬 Conversations</div>
          {conversations.length === 0 ? (
            <p className="text-muted" style={{padding:'1rem', fontSize:'0.85rem'}}>No conversations yet.</p>
          ) : (
            conversations.map((u: any) => {
              const isOnline = u.last_seen && (Date.now() - new Date(u.last_seen).getTime()) < 300000;
              return (
                <div key={u.id} className={`chat-user-item ${activeUser?.id === u.id ? 'active' : ''}`} onClick={() => handleSelectUser(u.id)} style={{cursor:'pointer', padding:'1rem', display:'flex', alignItems:'center', gap:'10px', borderBottom:'1px solid var(--border)', background: activeUser?.id === u.id ? 'rgba(0,113,227,0.05)' : 'transparent'}}>
                  {u.profile_pic ? (
                    <img src={assetUrl('uploads/' + u.profile_pic)} style={{width:'36px', height:'36px', borderRadius:'50%', objectFit:'cover'}} alt="Profile" />
                  ) : (
                    <div style={{width:'36px', height:'36px', borderRadius:'50%', background:'rgba(99,102,241,0.2)', display:'flex', alignItems:'center', justifyContent:'center', color:'var(--primary)', fontWeight:700, flexShrink:0}}>
                      {u.username.substring(0,1).toUpperCase()}
                    </div>
                  )}
                  <div style={{flex:1, overflow:'hidden'}}>
                    <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                      <strong style={{fontSize:'0.9rem', color:'var(--text-main)'}}>{u.username}</strong>
                      <span className="online-dot" style={{width:'8px', height:'8px', borderRadius:'50%', background: isOnline ? 'var(--success)' : '#555'}}></span>
                    </div>
                    {u.last_msg && <p style={{fontSize:'0.75rem', color:'var(--text-muted)', whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis', margin:0}}>{u.last_msg}</p>}
                  </div>
                  {u.unread > 0 && <span className="notif-badge" style={{position:'static'}}>{u.unread}</span>}
                </div>
              );
            })
          )}
        </div>

        {/* Chat Area */}
        <div className="chat-window" style={{flex:1, display:'flex', flexDirection:'column'}}>
          {activeUser ? (
            <>
              <div className="chat-header" style={{padding:'1rem', borderBottom:'1px solid var(--border)', fontWeight:700, display:'flex', alignItems:'center', gap:'10px'}}>
                {activeUser.username}
                <span style={{fontSize:'0.75rem', color:'var(--text-muted)'}}>
                  {(activeUser.last_seen && (Date.now() - new Date(activeUser.last_seen).getTime() < 300000)) ? '🟢 Online' : '⚫ Offline'}
                </span>
              </div>
              
              <div className="chat-messages" style={{flex:1, overflowY:'auto', padding:'1rem', display:'flex', flexDirection:'column', gap:'10px'}}>
                {activeThread.map((msg: any) => {
                  const isMine = msg.sender_id === user?.id;
                  return (
                    <div key={msg.id} style={{alignSelf: isMine ? 'flex-end' : 'flex-start', maxWidth:'70%'}}>
                      <div style={{background: isMine ? '#0071e3' : 'var(--card-bg)', color: isMine ? '#fff' : 'var(--text-main)', padding:'0.75rem 1rem', borderRadius: isMine ? '14px 14px 2px 14px' : '14px 14px 14px 2px', border: isMine ? 'none' : '1px solid var(--border)', fontSize:'0.9rem'}}>
                        {msg.message}
                      </div>
                      <div style={{fontSize:'0.65rem', color:'var(--text-muted)', marginTop:'4px', textAlign: isMine?'right':'left'}}>
                         {new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                         {isMine && <span style={{marginLeft:'4px'}}>{msg.is_read ? '✓✓' : '✓'}</span>}
                      </div>
                    </div>
                  );
                })}
                <div ref={threadEndRef} />
              </div>

              <div className="chat-input" style={{padding:'1.25rem 1.5rem', borderTop:'1px solid var(--border)', background:'rgba(255,255,255,0.02)', display:'flex', gap:'0.75rem'}}>
                <input 
                  type="text" 
                  value={message}
                  onChange={e => setMessage(e.target.value)}
                  onKeyPress={e => e.key === 'Enter' && handleSend()}
                  className="form-control" 
                  placeholder="Type a message..." 
                  style={{flex:1, borderRadius:'14px', background:'rgba(0,0,0,0.03)', border:'1px solid rgba(0,0,0,0.05)', padding:'10px 16px'}}
                />
                <button 
                  onClick={handleSend} 
                  disabled={sending || !message.trim()}
                  className="btn btn-primary" 
                  style={{padding:'0 1.5rem', borderRadius:'14px', fontWeight:700, height:'40px', boxShadow:'0 4px 12px rgba(0,113,227,0.25)'}}
                >
                  {sending ? '...' : 'Send'}
                </button>
              </div>
            </>
          ) : (
            <div style={{display:'flex', alignItems:'center', justifyContent:'center', height:'100%', color:'var(--text-muted)', flexDirection:'column', gap:'1rem'}}>
              <span style={{fontSize:'2rem'}}>💬</span>
              <p>Select a conversation to start chatting.</p>
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
