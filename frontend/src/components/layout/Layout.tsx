/**
 * Layout — Wraps pages with Header + Footer
 * Replaces the PHP pattern of including header.php and footer.php
 * 
 * NOTE: No .container wrapper here — pages like Home need full-bleed 
 * sections (Hero, category cards). Each page manages its own container.
 */
import React from 'react';
import { Outlet } from 'react-router-dom';
import Header from './Header';
import Footer from './Footer';

export default function Layout() {
  return (
    <>
      <Header />
      <main>
        <Outlet />
      </main>
      <Footer />
    </>
  );
}
