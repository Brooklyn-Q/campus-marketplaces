import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { products } from '../services/api';

export default function AddProduct() {
  const { user, isSeller } = useAuth();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  
  const maxImages = user?.tier_info?.max_images || 3;

  const categories = [
    'Computer & Accessories', 'Phone & Accessories', 'Electrical Appliances', 
    'Fashion', 'Food & Groceries', 'Education & Books', 'Hostels for Rent'
  ];

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isSeller) return;
    
    setLoading(true);
    setError('');
    
    const form = e.target as HTMLFormElement;
    const formData = new FormData(form);
    
    try {
      await products.create(formData);
      alert('Product submitted for review! It will appear once admin approves.');
      navigate('/dashboard');
    } catch (err: any) {
      setError(err.message || 'Failed to submit product.');
    } finally {
      setLoading(false);
    }
  };

  const handleAI = () => {
    const titleInput = document.getElementById('titleInput') as HTMLInputElement;
    const descArea = document.getElementById('descInput') as HTMLTextAreaElement;
    if (!titleInput.value) {
      alert("Please enter a title first!");
      return;
    }
    descArea.value = "Generating AI description based on title: " + titleInput.value + "...";
    setTimeout(() => {
        descArea.value = `Selling my slightly used ${titleInput.value}. Great condition, carefully maintained with no hidden faults. Perfect for students looking for a reliable deal at an affordable price. Comes from a clean environment. Feel free to message me for negotiations!`;
    }, 1500);
  };

  if (!user || (!isSeller && user.role !== 'admin')) {
    return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>You must be a verified seller to list products.</div>;
  }

  return (
    <div className="container" style={{padding:'2rem 0', display:'flex', justifyContent:'center'}}>
      <div className="glass form-container fade-in" style={{maxWidth:'700px', width:'100%'}}>
        <div style={{display:'flex', justifyContent:'space-between', alignItems:'flex-start', marginBottom:'1.5rem'}}>
            <div>
                <h2 className="mb-1">Add New Product</h2>
                <p className="text-muted" style={{fontSize:'0.85rem'}}>Your product will be reviewed by an admin before appearing live.</p>
            </div>
            <div style={{textAlign:'right'}}>
                <p className="text-muted" style={{fontSize:'0.75rem', marginBottom:'4px'}}>Seller Identity</p>
                <div style={{fontSize:'0.8rem', fontWeight:'bold', background:'var(--border)', padding:'4px 8px', borderRadius:'8px', display:'inline-block'}}>{user.seller_tier}</div>
            </div>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        <form onSubmit={handleSubmit} id="addProductForm">
            <div className="form-group">
                <label>Product Title *</label>
                <input type="text" name="title" id="titleInput" className="form-control" required maxLength={150} />
            </div>
            <div className="form-row">
                <div className="form-group">
                    <label>Category *</label>
                    <select name="category" className="form-control" required>
                        {categories.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                <div className="form-group">
                    <label>Promo Tag <span style={{color:'var(--text-muted)', fontWeight:400, fontSize:'0.8rem'}}>(optional)</span></label>
                    <select name="promo_tag" className="form-control">
                        <option value="">No promo tag</option>
                        <option value="🔥 Hot Deal">🔥 Hot Deal</option>
                        <option value="⚡ Flash Sale">⚡ Flash Sale</option>
                        <option value="⏳ Limited Offer">⏳ Limited Offer</option>
                        <option value="🎓 Student Special">🎓 Student Special</option>
                        <option value="📦 Bundle Deal">📦 Bundle Deal</option>
                    </select>
                </div>
            </div>
            <div className="form-row">
                <div className="form-group">
                    <label>Price (₵) *</label>
                    <input type="number" step="0.01" name="price" className="form-control" required min="0.01" />
                </div>
                <div className="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="quantity" className="form-control" required min="1" defaultValue="1" />
                </div>
            </div>
            <div className="form-row">
                <div className="form-group">
                    <label>Delivery Method *</label>
                    <select name="delivery_method" className="form-control" required>
                        <option value="Pickup">Pickup</option>
                        <option value="Delivery">Delivery</option>
                    </select>
                </div>
                <div className="form-group">
                    <label>Payment Agreement *</label>
                    <select name="payment_agreement" className="form-control" required>
                        <option value="Pay on delivery">Pay on delivery</option>
                        <option value="Pay before delivery">Pay before delivery</option>
                    </select>
                </div>
            </div>
            <div className="form-group">
                <div className="flex-between mb-1">
                    <label style={{margin:0}}>Description *</label>
                    <button type="button" className="btn btn-outline btn-sm" onClick={handleAI}>✨ Generate AI Text</button>
                </div>
                <textarea name="description" id="descInput" className="form-control" rows={5} required></textarea>
            </div>
            <div className="form-group">
                <label>Product Images (max {maxImages})</label>
                <input type="file" name="images[]" className="form-control" accept="image/*" multiple />
            </div>
            
            <button type="submit" disabled={loading} className="btn btn-primary" style={{width:'100%', justifyContent:'center', fontSize:'1rem', padding:'0.85rem'}}>
                {loading ? 'Uploading...' : 'Submit for Review'}
            </button>
        </form>
      </div>
    </div>
  );
}
