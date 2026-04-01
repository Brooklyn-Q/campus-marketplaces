import React, { Suspense, lazy } from 'react';
import { createRoot } from 'react-dom/client';

const Hero = lazy(() => import('./Hero'));
const SwitchToggleThemeDemo = lazy(() => import('@/components/ui/toggle-theme'));
const LiquidMetalButton = lazy(() => import('./components/ui/liquid-metal-button').then(m => ({ default: m.LiquidMetalButton })));

import './globals.css';

console.log('MARKETPLACE_REACT: Bundle loaded. Starting initialization...');

function init() {
  try {
    // 1. Mount original Hero if present
    const heroRoot = document.getElementById('react-hero-root');
    if (heroRoot) {
      console.log('MARKETPLACE_REACT: Mounting Hero...');
      createRoot(heroRoot).render(
        <React.StrictMode>
          <Suspense fallback={null}>
            <Hero />
          </Suspense>
        </React.StrictMode>
      );
    }

    // 2. Mount Theme Toggle if present
    const themeRoot = document.getElementById('react-theme-toggle');
    if (themeRoot) {
      console.log('MARKETPLACE_REACT: Mounting Theme Toggle...');
      createRoot(themeRoot).render(
        <React.StrictMode>
          <Suspense fallback={null}>
            <SwitchToggleThemeDemo />
          </Suspense>
        </React.StrictMode>
      );
    }

    // 3. Inject Liquid Metal Buttons
    const buttons = document.querySelectorAll('.react-liquid-btn');
    if (buttons.length > 0) {
      console.log('MARKETPLACE_REACT: Found', buttons.length, 'liquid buttons to hydrate');
      buttons.forEach((btn) => {
        const label = btn.getAttribute('data-label') || 'Button';
        const href = btn.getAttribute('href') || '#';
        const viewMode = (btn.getAttribute('data-view-mode') as any) || 'text';
        
        const wrapper = document.createElement('div');
        wrapper.style.display = 'inline-block';
        btn.parentNode?.insertBefore(wrapper, btn);
        
        createRoot(wrapper).render(
          <div onClick={() => { if(href !== '#') window.location.href = href; }} style={{ cursor: 'pointer' }}>
            <Suspense fallback={<div style={{ width: 120, height: 40, background: 'rgba(0,0,0,0.05)', borderRadius: 999 }} />}>
              <LiquidMetalButton label={label} viewMode={viewMode} />
            </Suspense>
          </div>
        );
        btn.remove();
      });
    }

    console.log('MARKETPLACE_REACT: Initialization complete.');
  } catch (err) {
    console.error('MARKETPLACE_REACT_FATAL:', err);
  }
}

// Ensure DOM is fully parsed for IDs
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
