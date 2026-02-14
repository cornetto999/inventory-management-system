import React, { useEffect, useState } from 'react';
import { apiFetch } from '@/api/client';
import { Package, Tags, Truck, AlertTriangle, ArrowDownToLine, ArrowUpFromLine, DollarSign, TrendingUp } from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend, PieChart, Pie, Cell } from 'recharts';
import { useAuth } from '@/contexts/AuthContext';

interface Stats {
  totalProducts: number;
  totalCategories: number;
  totalSuppliers: number;
  lowStockCount: number;
  stockInToday: number;
  stockInMonth: number;
  stockOutToday: number;
  stockOutMonth: number;
  inventoryValue: number;
  sellingValue: number;
}

interface DailyTrend {
  date: string;
  stockIn: number;
  stockOut: number;
}

interface CategoryStock {
  name: string;
  value: number;
}

interface Movement {
  id: string;
  movement_type: string;
  qty: number;
  prev_stock: number;
  new_stock: number;
  remarks: string | null;
  created_at: string;
  products: { name: string; sku: string } | null;
}

type DashboardPayload = {
  stats: Stats;
  recentMovements: Movement[];
  dailyTrends: { date: string; stockIn: number; stockOut: number }[];
  categoryStock: CategoryStock[];
};

const DashboardPage: React.FC = () => {
  const { profile } = useAuth();
  const [stats, setStats] = useState<Stats>({
    totalProducts: 0, totalCategories: 0, totalSuppliers: 0, lowStockCount: 0,
    stockInToday: 0, stockInMonth: 0, stockOutToday: 0, stockOutMonth: 0,
    inventoryValue: 0, sellingValue: 0,
  });
  const [recentMovements, setRecentMovements] = useState<Movement[]>([]);
  const [dailyTrends, setDailyTrends] = useState<DailyTrend[]>([]);
  const [categoryStock, setCategoryStock] = useState<CategoryStock[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      const res = await apiFetch<DashboardPayload>('/dashboard.php');
      if (!res.ok) {
        setLoading(false);
        return;
      }

      setStats(res.stats);
      setRecentMovements(res.recentMovements || []);

      // Format date labels for chart
      setDailyTrends(
        (res.dailyTrends || []).map((d) => ({
          date: new Date(d.date).toLocaleDateString('en', { month: 'short', day: 'numeric' }),
          stockIn: d.stockIn,
          stockOut: d.stockOut,
        }))
      );

      setCategoryStock(res.categoryStock || []);
      setLoading(false);
    };
    fetchData();
  }, []);

  if (loading) {
    return <div className="flex items-center justify-center h-64"><div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" /></div>;
  }

  const statCards = [
    { label: 'Total Products', value: stats.totalProducts, icon: Package, color: 'text-primary' },
    { label: 'Categories', value: stats.totalCategories, icon: Tags, color: 'text-accent' },
    { label: 'Suppliers', value: stats.totalSuppliers, icon: Truck, color: 'text-primary' },
    { label: 'Low Stock', value: stats.lowStockCount, icon: AlertTriangle, color: 'text-warning' },
    { label: 'Stock In (Today)', value: stats.stockInToday, icon: ArrowDownToLine, color: 'text-success' },
    { label: 'Stock In (Month)', value: stats.stockInMonth, icon: ArrowDownToLine, color: 'text-success' },
    { label: 'Stock Out (Today)', value: stats.stockOutToday, icon: ArrowUpFromLine, color: 'text-destructive' },
    { label: 'Stock Out (Month)', value: stats.stockOutMonth, icon: ArrowUpFromLine, color: 'text-destructive' },
    { label: 'Inventory Value', value: `₱${stats.inventoryValue.toLocaleString()}`, icon: DollarSign, color: 'text-primary' },
    { label: 'Selling Value', value: `₱${stats.sellingValue.toLocaleString()}`, icon: TrendingUp, color: 'text-accent' },
  ];

  const PIE_COLORS = [
    'hsl(var(--primary))', 'hsl(var(--accent))', 'hsl(var(--warning))',
    'hsl(var(--success))', 'hsl(var(--destructive))', 'hsl(210 60% 55%)',
  ];

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Dashboard</h1>
          <p className="text-sm text-muted-foreground mt-1">Welcome back, {profile?.name || 'User'}</p>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        {statCards.map((card) => (
          <div key={card.label} className="stat-card">
            <div className="flex items-center justify-between mb-3">
              <card.icon className={`h-5 w-5 ${card.color}`} />
            </div>
            <p className="text-2xl font-bold">{card.value}</p>
            <p className="text-xs text-muted-foreground mt-1">{card.label}</p>
          </div>
        ))}
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {/* Stock In/Out Trend */}
        <div className="lg:col-span-2 bg-card border rounded-lg p-5">
          <h2 className="text-lg font-semibold mb-1">Stock In / Out Trend</h2>
          <p className="text-sm text-muted-foreground mb-4">Last 14 days</p>
          <div className="h-[280px]">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={dailyTrends} barGap={2}>
                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }} />
                <YAxis tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }} allowDecimals={false} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: 'hsl(var(--card))',
                    border: '1px solid hsl(var(--border))',
                    borderRadius: '8px',
                    fontSize: '12px',
                  }}
                />
                <Legend wrapperStyle={{ fontSize: '12px' }} />
                <Bar dataKey="stockIn" name="Stock In" fill="hsl(var(--success))" radius={[3, 3, 0, 0]} />
                <Bar dataKey="stockOut" name="Stock Out" fill="hsl(var(--destructive))" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Stock by Category */}
        <div className="bg-card border rounded-lg p-5">
          <h2 className="text-lg font-semibold mb-1">Stock by Category</h2>
          <p className="text-sm text-muted-foreground mb-4">Current distribution</p>
          {categoryStock.length === 0 ? (
            <p className="text-sm text-muted-foreground text-center py-8">No product data</p>
          ) : (
            <div className="h-[280px]">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={categoryStock}
                    cx="50%"
                    cy="50%"
                    innerRadius={50}
                    outerRadius={90}
                    paddingAngle={3}
                    dataKey="value"
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    labelLine={false}
                  >
                    {categoryStock.map((_, i) => (
                      <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip
                    contentStyle={{
                      backgroundColor: 'hsl(var(--card))',
                      border: '1px solid hsl(var(--border))',
                      borderRadius: '8px',
                      fontSize: '12px',
                    }}
                  />
                </PieChart>
              </ResponsiveContainer>
            </div>
          )}
        </div>
      </div>

      {/* Recent Movements */}
      <div className="table-container">
        <div className="p-4 border-b">
          <h2 className="text-lg font-semibold">Recent Activity</h2>
          <p className="text-sm text-muted-foreground">Last 10 stock movements</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50">
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Product</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Qty</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Stock Change</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Remarks</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
              </tr>
            </thead>
            <tbody>
              {recentMovements.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">No movements yet</td></tr>
              ) : (
                recentMovements.map((m) => (
                  <tr key={m.id} className="border-b last:border-0 hover:bg-muted/30">
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        m.movement_type === 'IN' ? 'bg-success/10 text-success' : m.movement_type === 'OUT' ? 'bg-destructive/10 text-destructive' : 'bg-warning/10 text-warning'
                      }`}>
                        {m.movement_type}
                      </span>
                    </td>
                    <td className="px-4 py-3 font-medium">{m.products?.name || '-'}</td>
                    <td className="px-4 py-3">{m.qty}</td>
                    <td className="px-4 py-3 text-muted-foreground">{m.prev_stock} → {m.new_stock}</td>
                    <td className="px-4 py-3 text-muted-foreground truncate max-w-[150px]">{m.remarks || '-'}</td>
                    <td className="px-4 py-3 text-muted-foreground">{new Date(m.created_at).toLocaleDateString()}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default DashboardPage;
