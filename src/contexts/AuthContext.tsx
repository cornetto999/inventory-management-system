import React, { createContext, useContext, useEffect, useState } from 'react';
import { apiLogin, apiLogout, apiMe, type ApiUser, type AppRole } from '@/api/auth';

interface AuthContextType {
  user: ApiUser | null;
  profile: { name: string; email: string; status: string } | null;
  role: AppRole | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<{ error: any }>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
};

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<ApiUser | null>(null);
  const [profile, setProfile] = useState<{ name: string; email: string; status: string } | null>(null);
  const [role, setRole] = useState<AppRole | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const res = await apiMe();
      if (res.ok) {
        setUser(res.user);
        setProfile({ name: res.user.name, email: res.user.email, status: res.user.status });
        setRole(res.user.role);
      } else {
        setUser(null);
        setProfile(null);
        setRole(null);
      }
      setLoading(false);
    })();
  }, []);

  const signIn = async (email: string, password: string) => {
    const res = await apiLogin(email, password);
    if (res.ok) {
      setUser(res.user);
      setProfile({ name: res.user.name, email: res.user.email, status: res.user.status });
      setRole(res.user.role);
      return { error: null };
    }
    return { error: { message: res.error } };
  };

  const signOut = async () => {
    await apiLogout();
    setUser(null);
    setProfile(null);
    setRole(null);
  };

  return (
    <AuthContext.Provider value={{ user, profile, role, loading, signIn, signOut }}>
      {children}
    </AuthContext.Provider>
  );
};
