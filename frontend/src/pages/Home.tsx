import React, { useState, useEffect, useMemo } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { products, ads, recommendations } from '../services/api';
import Hero from '../Hero';

export default function Home() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const [productsList, setProductsList] = useState<any[]>([]);
  const [adsList, setAdsList] = useState<any[]>([]);
  const [aiRecs, setAiRecs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);

  const dedupeById = <T extends { id?: string | number }>(items: T[]) => {
    const seen = new Set<string>();
    return items.filter((item, index) => {
      const key = item?.id != null ? String(item.id) : `fallback-${index}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  };

  const search = searchParams.get('search') || '';
  const category = searchParams.get('category') || '';
  const minPrice = searchParams.get('min_price') || '';
  const maxPrice = searchParams.get('max_price') || '';
  const page = parseInt(searchParams.get('page') || '1', 10);

  const categories = [
    'Computer & Accessories',
    'Phone & Accessories',
    'Electrical Appliances',
    'Fashion',
    'Food & Groceries',
    'Education & Books',
    'Hostels for Rent'
  ];

  const base = import.meta.env.BASE_URL || '/';
  const catImages: any = {
    'Computer & Accessories': `${base}IMG_5825.webp`,
    'Phone & Accessories': `${base}IMG_5822.webp`,
    'Electrical Appliances': `${base}IMG_5827.webp`,
    'Fashion': `${base}IMG_5828.webp`,
    'Food & Groceries': `${base}IMG_5830.webp`,
    'Education & Books': `${base}IMG_5831.webp`,
    'Hostels for Rent': `${base}IMG_5833.webp`
  };

  const catDescriptions: any = {
    'Computer & Accessories': 'Laptops, monitors, and all computing essentials.',
    'Phone & Accessories': 'Smartphones, covers, screen protectors, and mobile gear.',
    'Electrical Appliances': 'Home and dorm gadgets, microwaves, fans, and blenders.',
    'Fashion': 'Trendy clothing, shoes, bags, and stylish accessories.',
    'Food & Groceries': 'Snacks, beverages, fresh produce, and daily provisions.',
    'Education & Books': 'Textbooks, notebooks, stationery, and study materials.',
    'Hostels for Rent': 'Accommodation, shared rooms, apartments, and living spaces.'
  };

  useEffect(() => {
    // Check if review required
    if (user?.has_unreviewed_orders) {
      navigate('/dashboard#buyer_orders');
    }
  }, [user, navigate]);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const adLoc = category ? 'category' : 'homepage';
        const [prodRes, adRes] = await Promise.all([
          products.list({ q: search, category, min_price: minPrice, max_price: maxPrice, page: page.toString() }),
          ads.list(adLoc).catch(() => ({ ads: [] }))
        ]);
        setProductsList(dedupeById(prodRes.products || []));
        setTotal(prodRes.total || prodRes.pagination?.total || 0);
        setAdsList(dedupeById((adRes as any).ads || []));

        if (!search && !category && page === 1) {
          const recsRes = await recommendations.get('home').catch(() => ({ recommendations: [] }));
          setAiRecs(dedupeById((recsRes as any).recommendations || []));
        } else {
          setAiRecs([]);
        }
      } catch (err) {
        console.error("Failed to load home data", err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [search, category, minPrice, maxPrice, page]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const form = e.target as HTMLFormElement;
    const s = (form.elements.namedItem('search') as HTMLInputElement).value;
    const c = (form.elements.namedItem('category') as HTMLSelectElement).value;
    const min = (form.elements.namedItem('min_price') as HTMLInputElement).value;
    const max = (form.elements.namedItem('max_price') as HTMLInputElement).value;
    setSearchParams({ search: s, category: c, min_price: min, max_price: max, page: '1' });
  };

  const isDefaultHome = !search && !category && !minPrice && !maxPrice && page === 1;
  const totalPages = Math.ceil(total / 12);
  const stableProducts = useMemo(() => dedupeById(productsList), [productsList]);
  const stableAds = useMemo(() => dedupeById(adsList), [adsList]);
  const stableAiRecs = useMemo(() => dedupeById(aiRecs), [aiRecs]);

  const assetUrl = (path: string) => {
    // If it's already an absolute URL, use as-is
    if (path.startsWith('uploads/http')) return path.substring(8);
    if (path.startsWith('http')) return path;
    // For uploads from the backend (product images etc), prefix with API base
    if (path.startsWith('uploads/')) {
      const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/marketplace/backend/api';
      // Strip '/api' from the end to get the backend root for uploads
      const backendRoot = apiBase.replace(/\/api\/?$/, '');
      return `${backendRoot}/../${path}`;
    }
    // For local public assets, use root path
    return path.startsWith('/') ? path : `/${path}`;
  };

  return (
    <>
      {isDefaultHome ? (
         <Hero />
      ) : category && catImages[category] ? (
        <div style={{position:'relative', width:'100%', height:'40vh', minHeight:'300px', display:'flex', alignItems:'center', justifyContent:'center', overflow:'hidden', marginBottom:'2rem'}}>
          <img src={assetUrl(catImages[category])} alt={category} style={{position:'absolute', inset:0, width:'100%', height:'100%', objectFit:'cover', objectPosition:'center top', zIndex:0}} />
          <div style={{position:'absolute', inset:0, background:'rgba(0,0,0,0.5)', zIndex:1}}></div>
          <div style={{textAlign:'center', zIndex:2, padding:'0 20px'}}>
            <h1 style={{fontSize:'3.5rem', fontWeight:800, color:'#fff', letterSpacing:'-0.04em', textShadow:'0 12px 40px rgba(0,0,0,0.8)'}}>{category}</h1>
            <p style={{color:'#f5f5f7', fontSize:'1.2rem', marginTop:'0.5rem', fontWeight:500}}>{catDescriptions[category]}</p>
            <p style={{color:'#f5f5f7', fontSize:'1rem', marginTop:'0.5rem', fontWeight:400}}>Found {total} products</p>
          </div>
        </div>
      ) : (
        <div style={{padding:'5rem 5% 3rem', background:'linear-gradient(to bottom, rgba(0,113,227,0.06), transparent)', borderBottom:'1px solid rgba(0,0,0,0.05)', textAlign:'center', marginBottom:'2rem', width:'100%'}}>
            <h1 style={{fontSize:'3.5rem', fontWeight:800, color:'var(--text-main)', letterSpacing:'-0.04em'}}>
                {search ? `Search: "${search}"` : 'All Products'}
            </h1>
            <p style={{color:'var(--text-muted)', fontSize:'1.2rem', marginTop:'0.5rem', fontWeight:500}}>Found {total} items</p>
        </div>
      )}

      <div className="container">

      {/* FRAUD NOTICE */}
      <div className="notice-box-inline mb-4" style={{maxWidth:'800px', margin:'20px auto', background:'rgba(255,204,0,0.15)', border:'1px solid rgba(255,204,0,0.5)', padding:'15px', borderRadius:'12px', textAlign:'center'}}>
          <strong><span style={{fontSize:'1.1rem', verticalAlign:'middle', marginRight:'5px'}}>⚠️</span> SAFETY NOTICE:</strong>
          <span>All transactions should be made in person. Sending money online without meeting the seller is at your own risk.</span>
      </div>

      {/* FILTERS */}
      <div className="glass filter-bar" style={{ marginBottom: '2rem' }}>
          <form onSubmit={handleSearch} style={{display:'flex', gap:'0.75rem', width:'100%', flexWrap:'wrap', alignItems:'center'}}>
              <div style={{flex:1, minWidth:'180px', position:'relative'}}>
                  <input type="text" name="search" className="form-control" placeholder="Search products..." defaultValue={search} style={{width:'100%'}} />
              </div>
              <select name="category" className="form-control" defaultValue={category} style={{maxWidth:'160px'}}>
                  <option value="">All Categories</option>
                  {categories.map(c => <option key={c} value={c}>{c}</option>)}
              </select>
              <input type="number" name="min_price" className="form-control" placeholder="Min ₵" defaultValue={minPrice} style={{width:'100px'}} />
              <input type="number" name="max_price" className="form-control" placeholder="Max ₵" defaultValue={maxPrice} style={{width:'100px'}} />
              <button type="submit" className="btn btn-primary">Search</button>
          </form>
      </div>

      {/* AD CAROUSEL */}
      {stableAds.length > 0 && (
         <div style={{marginTop:'2rem', marginBottom:'1.5rem', position:'relative'}}>
         <div id="adCarousel" className="horizontal-scroll-container" style={{scrollSnapType: 'x mandatory', WebkitOverflowScrolling: 'touch', padding: '0 0 10px 0'}}>
             {stableAds.map((ad, index) => (
                 <a key={ad.id ?? `ad-${index}`} href={ad.link_url} target="_blank" rel="noopener noreferrer" onClick={() => ads.click(ad.id).catch(()=>{})} className="fade-in ad-item-link" style={{flex: '0 0 100%', scrollSnapAlign: 'start', minWidth: '100%', textDecoration: 'none'}}>
                     <div className="ad-image-container" style={{borderRadius:'24px', overflow:'hidden', border:'1px solid rgba(0,0,0,0.05)', position:'relative', transition:'all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1)', boxShadow: '0 10px 30px rgba(0,0,0,0.08)'}}>
                         {ad.image_url ? (
                             <img src={ad.image_url} alt={ad.title} className="ad-banner-img" loading="lazy" style={{width:'100%', height:'100%', objectFit:'cover', display:'block'}} />
                         ) : (
                             <div style={{background:'linear-gradient(135deg, #0071e3, #34aaff)', color:'#fff', padding:'2.5rem', textAlign:'center', minHeight:'160px', display:'flex', flexDirection:'column', justifyContent:'center'}}>
                                 <p style={{fontSize:'0.7rem', letterSpacing:'0.15em', textTransform:'uppercase', opacity:0.8, marginBottom:'0.5rem', fontWeight:700}}>Sponsored Content</p>
                                 <p style={{fontSize:'1.5rem', fontWeight:800, letterSpacing:'-0.02em'}}>{ad.title}</p>
                             </div>
                         )}
                         <span style={{position:'absolute', top:'12px', right:'12px', background:'rgba(0,0,0,0.4)', backdropFilter:'blur(10px)', color:'#fff', fontSize:'0.6rem', padding:'4px 10px', borderRadius:'8px', letterSpacing:'0.08em', fontWeight:700, border:'1px solid rgba(255,255,255,0.1)'}}>AD</span>
                     </div>
                 </a>
             ))}
         </div>
     </div>
      )}

      <h2 className="mb-2" style={{fontSize:'1.3rem'}}>Approved Listings <span className="text-muted" style={{fontSize:'0.9rem'}}>({total} items)</span></h2>

      {/* AI RECOMMENDATIONS */}
      {isDefaultHome && stableAiRecs.length > 0 && (
         <div style={{marginBottom:'2rem', position:'relative'}}>
         <h3 className="mb-3" style={{fontSize:'1.4rem', fontWeight:800, display:'flex', alignItems:'center', justifyContent:'space-between'}}>
             <span style={{display:'flex', alignItems:'center', gap:'0.5rem'}}>
                 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#ai-grad)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                     <defs><linearGradient id="ai-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stopColor="#0071e3"/><stop offset="100%" stopColor="#34aaff"/></linearGradient></defs>
                     <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path>
                 </svg>
                 Recommendations
             </span>
             <span style={{fontSize:'0.85rem', color:'var(--text-muted)', fontWeight:600}}>Swipe →</span>
         </h3>
         
         <div className="horizontal-scroll-container" style={{scrollSnapType: 'x mandatory', WebkitOverflowScrolling: 'touch'}}>
             {stableAiRecs.map((mp, index) => (
                 <Link key={mp.id ?? `rec-${index}`} to={`/product/${mp.id}`} className="scroll-card glass fade-in" style={{scrollSnapAlign: 'start', minWidth: '160px', textDecoration:'none'}}>
                     <div className="product-img-wrap" style={{aspectRatio: '4/3', maxHeight: '140px', borderRadius: '12px', overflow:'hidden'}}>
                         {mp.main_image ? (
                             <img src={assetUrl('uploads/' + mp.main_image)} alt={mp.title} className="product-img" loading="lazy" style={{width:'100%', height:'100%', objectFit:'cover'}} />
                         ) : (
                             <div className="product-img" style={{display:'flex',alignItems:'center',justifyContent:'center',color:'#555',background:'rgba(0,0,0,0.1)'}}>No Image</div>
                         )}
                     </div>
                     <div className="product-body" style={{padding:'10px 6px'}}>
                         <p className="product-title" style={{whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis', fontSize:'0.8rem', fontWeight:700, margin:0, color:'var(--text-main)'}}>{mp.title}</p>
                         <p className="product-price" style={{fontSize:'0.9rem', fontWeight:800, color:'var(--primary)', marginTop:'2px'}}>₵{Number(mp.price).toFixed(2)}</p>
                     </div>
                 </Link>
             ))}
         </div>
     </div>
      )}

      {/* PRODUCT GRID */}
      {loading ? (
        <p className="text-center">Loading products...</p>
      ) : stableProducts.length > 0 ? (
        <div className="product-grid">
            {stableProducts.map((p, index) => {
               const promo = p.promo_tag ? p.promo_tag.trim() : '';
               return (
                <Link key={p.id ?? `product-${index}`} to={`/product/${p.id}`} className="glass product-card fade-in" style={{flexDirection:'column', textDecoration:'none', color:'inherit'}}>
                <div className="product-img-wrap" style={{aspectRatio: '1 / 1', borderRadius: '14px', overflow:'hidden'}}>
                    {p.main_image ? (
                        <img src={assetUrl('uploads/' + p.main_image)} alt={p.title} className="product-img" loading="lazy" style={{width:'100%', height:'100%', objectFit:'cover'}} />
                    ) : (
                        <div className="product-img" style={{display:'flex',alignItems:'center',justifyContent:'center',color:'#555',background:'rgba(0,0,0,0.3)'}}>No Image</div>
                    )}

                    {p.boosted_until && new Date(p.boosted_until).getTime() > Date.now() && (
                        <>
                        <span className="boosted-badge">⚡ Boosted</span>
                        <span className="featured-badge">⭐ Featured</span>
                        </>
                    )}
                    {promo && (
                        <span className="boosted-badge" style={{top:'8px', bottom:'auto', left:'8px', background:'rgba(0,0,0,0.75)', backdropFilter:'blur(8px)', WebkitBackdropFilter:'blur(8px)', color:'#fff', fontSize:'0.6rem', padding:'0.3rem 0.6rem', borderRadius:'8px', fontWeight:600, boxShadow:'0 2px 8px rgba(0,0,0,0.2)', border:'1px solid rgba(255,255,255,0.15)', display:'flex', alignItems:'center', gap:'4px', letterSpacing:'0.02em'}}>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#facc15" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            {promo.replace(/[⚡⏳🎓📦🏷️]\s*/g, '')}
                        </span>
                    )}
                </div>
                <div className="product-body">
                    <p className="product-title" style={{fontSize:'0.75rem'}}>{p.title}</p>
                    {(p.original_price_before_discount && parseFloat(p.original_price_before_discount) > parseFloat(p.price)) ? (
                        <p className="product-price">
                            <span style={{textDecoration:'line-through', opacity:0.5, fontSize:'0.75rem', fontWeight:400}}>₵{Number(p.original_price_before_discount).toFixed(2)}</span>
                            ₵{Number(p.price).toFixed(2)}
                            <span style={{background:'rgba(239,68,68,0.12)', color:'#ef4444', fontSize:'0.55rem', fontWeight:700, padding:'0.15rem 0.35rem', borderRadius:'4px', marginLeft:'2px'}}>
                                −{Math.round(100 - (parseFloat(p.price) / parseFloat(p.original_price_before_discount) * 100))}%
                            </span>
                        </p>
                    ) : (
                        <p className="product-price">₵{Number(p.price).toFixed(2)}</p>
                    )}
                    <p className="product-meta">
                        By {p.seller_name}
                        {/* {getBadgeHtml(p.seller_tier)} */}
                    </p>

                    {p.quantity <= 0 ? (
                        <span style={{display:'block', fontSize:'0.65rem', color:'#ef4444', fontWeight:700, marginTop:'0.2rem'}}>🚫 Out of Stock</span>
                    ) : p.quantity <= 5 ? (
                        <span style={{display:'block', fontSize:'0.65rem', color:'#ff9500', fontWeight:700, marginTop:'0.2rem'}}>🔥 Only {p.quantity} left!</span>
                    ) : null}
                    
                    {p.quantity > 0 && (
                        <button
                            type="button"
                            className="quick-add-btn"
                            onClick={(e) => {
                                e.preventDefault(); e.stopPropagation();
                                const img = p.main_image ? assetUrl('uploads/' + p.main_image) : '';
                                (window as any).cmCart?.add(p.id, p.title, p.price, img);
                                const currentTarget = e.currentTarget;
                                currentTarget.textContent = '✓ Added';
                                setTimeout(() => { currentTarget.textContent = '+ Add'; }, 1500);
                            }}
                        >+ Add</button>
                    )}
                </div>
            </Link>
               );
            })}
        </div>
      ) : (
        <p className="text-muted">No products found matching your criteria.</p>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex gap-1 mt-3" style={{justifyContent:'center'}}>
            {Array.from({length: totalPages}, (_, i) => i + 1).map(i => (
                <button 
                  key={i}
                  onClick={() => setSearchParams({ search, category, min_price: minPrice, max_price: maxPrice, page: i.toString() })}
                  className={`btn ${i === page ? 'btn-primary' : 'btn-outline'} btn-sm`}
                >{i}</button>
            ))}
        </div>
      )}
      
      </div> {/* End .container */}
    </>
  );
}
