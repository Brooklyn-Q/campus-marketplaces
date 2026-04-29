/**
 * AuthContext — Replaces PHP session-based auth ($_SESSION)
 * Manages JWT token storage and user state across the React SPA.
 */
import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { auth as authApi, getToken, setToken, removeToken } from '../services/api';
import { getLegacyLogoutUrl } from '../utils/legacyAuth';

export interface User {
  id: number;
  username: string;
  email: string;
  role: 'buyer' | 'seller' | 'admin';
  seller_tier: string;
  profile_pic: string | null;
  terms_accepted: number;
  referral_code: string;
  balance: number;
  faculty?: string;
  department?: string;
  level?: string;
  hall?: string;
  phone?: string;
  bio?: string;
  vacation_mode?: number;
  vacation_pending?: boolean;
  verified?: number;
  suspended?: number;
  has_unreviewed_orders?: boolean;
  unread_messages?: number;
  unread_notifications?: number;
  wallet_balance?: number;
  badge?: { label: string; color: string; bg: string };
  tier_info?: any;
  last_seen?: string;
  created_at?: string;
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  isLoggedIn: boolean;
  isAdmin: boolean;
  isSeller: boolean;
  isBuyer: boolean;
  login: (identifier: string, password: string) => Promise<{ success: boolean; error?: string }>;
  register: (formData: FormData) => Promise<{ success: boolean; error?: string }>;
  logout: () => void;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Load user from token on mount
  const refreshUser = useCallback(async () => {
    const token = getToken();
    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }

    try {
      const data = await authApi.me();
      setUser(data.user);
    } catch (err: any) {
      // If the error is not a 401, it might be a network error or 502/503 from Render deploying.
      // Do NOT forcefully clear the token on a network error. 
      // The 401 interceptor in api.ts will handle genuine token expirations.
      if (err.message === 'Authentication required') {
        setUser(null);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refreshUser();

    // Listen for 401s from api.ts
    const handleUnauthorized = () => {
      setUser(null);
      setLoading(false);
    };

    document.addEventListener('auth_unauthorized', handleUnauthorized);
    return () => {
      document.removeEventListener('auth_unauthorized', handleUnauthorized);
    };
  }, [refreshUser]);

  const login = async (identifier: string, password: string) => {
    try {
      const data = await authApi.login(identifier, password);
      if (data.success && data.token) {
        setToken(data.token);
        setUser(data.user);
        return { success: true, isAdmin: data.user.role === 'admin', user: data.user };
      }
      return { success: false, error: data.error || 'Login failed' };
    } catch (err: any) {
      return { success: false, error: err.message || 'Login failed' };
    }
  };

  const register = async (formData: FormData) => {
    try {
      const data = await authApi.register(formData);
      if (data.success && data.token) {
        setToken(data.token);
        setUser(data.user);
        return { success: true, isAdmin: data.user.role === 'admin', user: data.user };
      }
      return { success: false, error: data.error || 'Registration failed' };
    } catch (err: any) {
      return { success: false, error: err.message || 'Registration failed' };
    }
  };

  const logout = () => {
    removeToken();
    setUser(null);

    if (typeof window !== 'undefined') {
      window.location.assign(getLegacyLogoutUrl());
    }
  };

  const value: AuthContextType = {
    user,
    loading,
    isLoggedIn: !!user,
    isAdmin: user?.role === 'admin',
    isSeller: user?.role === 'seller' || user?.role === 'admin',
    isBuyer: user?.role === 'buyer',
    login,
    register,
    logout,
    refreshUser,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextType {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

export default AuthContext;
