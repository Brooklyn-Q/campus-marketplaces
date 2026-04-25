export const assetUrl = (path: string | undefined | null) => {
  if (!path) return '';
  
  // Already a full URL (Cloudinary, etc)
  if (path.startsWith('http://') || path.startsWith('https://')) {
    let url = path;
    const apiBase = import.meta.env.VITE_API_URL || '';
    const backendRoot = apiBase ? apiBase.replace(/\/api\/?$/, '') : '';

    // Rewrite broken localhost URLs → production backend
    if (url.includes('localhost') && backendRoot) {
      const match = url.match(/\/uploads\/.+$/);
      if (match) url = `${backendRoot}${match[0]}`;
    }

    // Upgrade http → https (GitHub Pages blocks mixed content)
    if (url.startsWith('http://')) {
      url = url.replace('http://', 'https://');
    }
    return url;
  }
  
  // Relative upload path from the backend
  if (path.startsWith('uploads/') || path.includes('/uploads/')) {
    const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/marketplace/backend/api';
    const backendRoot = apiBase.replace(/\/api\/?$/, '');
    return `${backendRoot}/${path.replace(/^\/+/, '')}`;
  }
  
  // Local public assets
  const base = import.meta.env.BASE_URL || '/';
  
  // Ensure we don't double up slashes if base is '/' and path is '/image.jpg'
  const cleanPath = path.startsWith('/') ? path.slice(1) : path;
  const cleanBase = base.endsWith('/') ? base : `${base}/`;
  
  // If the path already starts with the base URL, return it directly
  if (base !== '/' && path.startsWith(base)) {
    return path;
  }
  
  return `${cleanBase}${cleanPath}`;
};
