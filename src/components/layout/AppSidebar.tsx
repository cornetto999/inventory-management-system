import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import {
  LayoutDashboard, Package, Tags, Truck, ArrowDownToLine, ArrowUpFromLine,
  History, BarChart3, Users, LogOut, Box,
} from 'lucide-react';

const navItems = [
  { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/products', label: 'Products', icon: Package },
  { to: '/categories', label: 'Categories', icon: Tags },
  { to: '/suppliers', label: 'Suppliers', icon: Truck },
  { to: '/stock-in', label: 'Stock In', icon: ArrowDownToLine },
  { to: '/stock-out', label: 'Stock Out', icon: ArrowUpFromLine },
  { to: '/movements', label: 'Movements', icon: History },
  { to: '/reports', label: 'Reports', icon: BarChart3 },
];

const adminItems = [
  { to: '/users', label: 'Users', icon: Users },
];

export const AppSidebar: React.FC = () => {
  const { profile, role, signOut } = useAuth();
  const location = useLocation();

  return (
    <aside className="fixed left-0 top-0 z-40 flex h-screen w-64 flex-col bg-sidebar text-sidebar-foreground">
      {/* Logo */}
      <div className="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary">
          <Box className="h-5 w-5 text-primary-foreground" />
        </div>
        <div>
          <h1 className="text-sm font-bold text-sidebar-accent-foreground">Inventory</h1>
          <p className="text-[10px] uppercase tracking-wider text-sidebar-foreground/60">Management System</p>
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <p className="px-3 mb-2 text-[10px] font-semibold uppercase tracking-wider text-sidebar-foreground/50">Main</p>
        {navItems.map(({ to, label, icon: Icon }) => {
          const isActive = location.pathname === to || location.pathname.startsWith(to + '/');
          return (
            <NavLink
              key={to}
              to={to}
              className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-sidebar-accent text-sidebar-primary'
                  : 'text-sidebar-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground'
              }`}
            >
              <Icon className="h-4 w-4" />
              {label}
            </NavLink>
          );
        })}

        {role === 'admin' && (
          <>
            <p className="px-3 mt-5 mb-2 text-[10px] font-semibold uppercase tracking-wider text-sidebar-foreground/50">Admin</p>
            {adminItems.map(({ to, label, icon: Icon }) => {
              const isActive = location.pathname === to;
              return (
                <NavLink
                  key={to}
                  to={to}
                  className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-sidebar-accent text-sidebar-primary'
                      : 'text-sidebar-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground'
                  }`}
                >
                  <Icon className="h-4 w-4" />
                  {label}
                </NavLink>
              );
            })}
          </>
        )}
      </nav>

      {/* User section */}
      <div className="border-t border-sidebar-border p-4">
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-sidebar-accent text-xs font-bold text-sidebar-primary">
            {profile?.name?.charAt(0)?.toUpperCase() || '?'}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-sidebar-accent-foreground truncate">{profile?.name || 'User'}</p>
            <p className="text-[10px] capitalize text-sidebar-foreground/60">{role || 'user'}</p>
          </div>
          <button onClick={signOut} className="text-sidebar-foreground/60 hover:text-destructive transition-colors" title="Sign out">
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </aside>
  );
};
