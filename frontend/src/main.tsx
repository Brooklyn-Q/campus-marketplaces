import React, { Suspense, lazy } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const Hero = lazy(() => import('./Hero'));
const SwitchToggleThemeDemo = lazy(() => import('@/components/ui/toggle-theme'));
const LiquidMetalButton = lazy(() => import('./components/ui/liquid-metal-button').then(m => ({ default: m.LiquidMetalButton })));

import './globals.css';

// console.log('MARKETPLACE_REACT: Bundle loaded. Starting initialization...');

function mountSpaApp(root: HTMLElement) {
  if (root.hasAttribute('data-react-mounted')) return;

  root.setAttribute('data-react-mounted', 'app');
  // console.log('MARKETPLACE_REACT: Mounting full SPA...');
  createRoot(root).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}

function mountLegacyIslands() {
  const heroRoot = document.getElementById('react-hero-root');
  if (heroRoot && heroRoot.getAttribute('data-react-mount') === 'hero' && !heroRoot.hasAttribute('data-react-mounted')) {
    heroRoot.setAttribute('data-react-mounted', 'true');
    // console.log('MARKETPLACE_REACT: Mounting Hero...');
    createRoot(heroRoot).render(
      <React.StrictMode>
        <Suspense fallback={null}>
          <Hero />
        </Suspense>
      </React.StrictMode>
    );
  }

  const themeRoot = document.getElementById('react-theme-toggle');
  if (themeRoot && !themeRoot.hasAttribute('data-react-mounted')) {
    themeRoot.setAttribute('data-react-mounted', 'true');
    // console.log('MARKETPLACE_REACT: Mounting Theme Toggle...');
    createRoot(themeRoot).render(
      <React.StrictMode>
        <Suspense fallback={null}>
          <SwitchToggleThemeDemo />
        </Suspense>
      </React.StrictMode>
    );
  }

  const buttons = document.querySelectorAll('.react-liquid-btn');
  if (buttons.length > 0) {
    // console.log('MARKETPLACE_REACT: Found', buttons.length, 'liquid buttons to hydrate');
    buttons.forEach((btn) => {
      if (btn.hasAttribute('data-react-mounted')) return;

      btn.setAttribute('data-react-mounted', 'true');
      const label = btn.getAttribute('data-label') || 'Button';
      const href = btn.getAttribute('href') || '#';
      const viewMode = (btn.getAttribute('data-view-mode') as any) || 'text';

      const wrapper = document.createElement('div');
      wrapper.className = 'react-liquid-btn-wrapper';
      wrapper.style.display = 'flex';
      wrapper.style.alignItems = 'center';
      wrapper.style.flexShrink = '0';
      btn.parentNode?.insertBefore(wrapper, btn);

      createRoot(wrapper).render(
        <div onClick={() => { if (href !== '#') window.location.href = href; }} style={{ cursor: 'pointer' }}>
          <Suspense fallback={<div style={{ width: 120, height: 40, background: 'rgba(0,0,0,0.05)', borderRadius: 999 }} />}>
            <LiquidMetalButton label={label} viewMode={viewMode} />
          </Suspense>
        </div>
      );
      btn.remove();
    });
  }
}

function init() {
  try {
    const root = document.getElementById('root');
    if (root) {
      mountSpaApp(root);
      return;
    }

    mountLegacyIslands();
    // console.log('MARKETPLACE_REACT: Initialization complete.');
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
