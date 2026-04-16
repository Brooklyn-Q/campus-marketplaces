import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { products, orders } from '../services/api';

export default function Product() {
  const { id } = useParams();
  const [product, setProduct] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [ordering, setOrdering] = useState(false);

  useEffect(() => {
    const fetchProduct = async () => {
      try {
        const data = await products.get(parseInt(id || '0', 10));
        setProduct(data.product);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchProduct();
  }, [id]);

  const handleOrder = async () => {
    if (!product) return;
    setOrdering(true);
    try {
      await orders.create(product.id, 1);
      alert('Order placed successfully! Check your dashboard.');
    } catch (err: any) {
      alert(err.message || 'Failed to place order.');
    } finally {
      setOrdering(false);
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

  if (loading) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading...</div>;
  if (!product) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Product not found.</div>;

  return (
    <div className="container" style={{padding:'2rem 0'}}>
      <div style={{display:'flex', flexWrap:'wrap', gap:'2rem'}}>
        {/* Product Image */}
        <div style={{flex:'1 1 400px', borderRadius:'24px', overflow:'hidden', boxShadow:'0 20px 40px rgba(0,0,0,0.1)'}}>
          <img 
            src={product.images && product.images[0] ? assetUrl(product.images[0].image_url) : ''} 
            alt={product.title} 
            style={{width:'100%', height:'100%', objectFit:'cover', minHeight:'300px'}} 
            className="glass"
          />
        </div>

        {/* Product Details */}
        <div style={{flex:'1 1 400px', padding:'1rem 0'}}>
          <div style={{display:'flex', gap:'0.5rem', marginBottom:'1rem'}}>
            <span style={{background:'rgba(0,113,227,0.1)', color:'#0071e3', padding:'4px 12px', borderRadius:'99px', fontSize:'0.75rem', fontWeight:700}}>{product.category}</span>
            {product.condition && (
              <span style={{background:'rgba(0,0,0,0.05)', padding:'4px 12px', borderRadius:'99px', fontSize:'0.75rem', fontWeight:600}}>{product.condition}</span>
            )}
          </div>
          
          <h1 style={{fontSize:'2.5rem', fontWeight:800, marginBottom:'0.5rem', lineHeight:1.1}}>{product.title}</h1>
          
          <div style={{fontSize:'2rem', fontWeight:800, color:'var(--primary)', marginBottom:'1.5rem'}}>
            ₵{Number(product.price).toFixed(2)}
          </div>
          
          <p style={{fontSize:'1rem', lineHeight:1.6, color:'var(--text-muted)', marginBottom:'2rem'}}>
            {product.description}
          </p>
          
          <div className="glass" style={{padding:'1.5rem', borderRadius:'20px', marginBottom:'2rem'}}>
            <h3 style={{fontSize:'1rem', marginBottom:'0.5rem'}}>Seller Information</h3>
            <div style={{display:'flex', alignItems:'center', gap:'1rem'}}>
              <div style={{width:'48px', height:'48px', borderRadius:'50%', background:'var(--border)', display:'flex', alignItems:'center', justifyContent:'center'}}>
                {product.seller_pic ? (
                  <img src={assetUrl('uploads/' + product.seller_pic)} alt="Seller" style={{width:'100%', height:'100%', borderRadius:'50%', objectFit:'cover'}} />
                ) : (
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                )}
              </div>
              <div>
                <p style={{margin:0, fontWeight:'bold', fontSize:'1.1rem'}}>{product.seller_name}</p>
                <p style={{margin:0, fontSize:'0.8rem', color:'var(--text-muted)'}}>Tier: {product.seller_tier}</p>
              </div>
            </div>
          </div>

          <div style={{display:'flex', gap:'1rem'}}>
            <button 
              onClick={handleOrder} 
              disabled={ordering || product.quantity <= 0}
              className="btn btn-primary" 
              style={{flex:1, padding:'1.2rem', fontSize:'1.1rem', borderRadius:'99px'}}
            >
              {product.quantity <= 0 ? 'Out of Stock' : (ordering ? 'Placing Order...' : 'Buy Now (POD)')}
            </button>
            <Link 
              to={`/chat?user=${product.user_id}`} 
              className="btn btn-outline" 
              style={{padding:'1.2rem 2rem', borderRadius:'99px'}}
            >
              Message Seller
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
