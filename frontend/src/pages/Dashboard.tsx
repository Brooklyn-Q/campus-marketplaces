import React from 'react';
import { useAuth } from '../contexts/AuthContext';
import SellerDashboard from './SellerDashboard';
import UserDashboard from './UserDashboard';
import AdminDashboard from './AdminDashboard';

export default function Dashboard() {
  const { user } = useAuth();

  if (!user) {
    return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading...</div>;
  }

  if (user.role === 'admin') {
    return <AdminDashboard />;
  }

  if (user.role === 'seller') {
    return <SellerDashboard />;
  }

  return <UserDashboard />;
}
