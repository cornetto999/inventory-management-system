import React, { useEffect, useState } from 'react';
import { apiFetch, qs } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Download, Package, AlertTriangle, ArrowDownToLine, ArrowUpFromLine, History } from 'lucide-react';
import { toast } from 'sonner';

const exportToCsv = (data: any[], filename: string) => {
  if (!data.length) { toast.error('No data to export'); return; }
  const headers = Object.keys(data[0]);
  const csv = [headers.join(','), ...data.map(row => headers.map(h => {
    const val = row[h];
    const str = val === null || val === undefined ? '' : String(val);
    return str.includes(',') || str.includes('"') ? `"${str.replace(/"/g, '""')}"` : str;
  }).join(','))].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${filename}_${new Date().toISOString().split('T')[0]}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  toast.success('Exported successfully');
};

const ReportsPage: React.FC = () => {
  return (
    <div>
      <div className="page-header"><h1 className="page-title">Reports</h1></div>
      <Tabs defaultValue="inventory">
        <TabsList className="mb-4">
          <TabsTrigger value="inventory"><Package className="h-4 w-4 mr-1" />Inventory</TabsTrigger>
          <TabsTrigger value="lowstock"><AlertTriangle className="h-4 w-4 mr-1" />Low Stock</TabsTrigger>
          <TabsTrigger value="stockin"><ArrowDownToLine className="h-4 w-4 mr-1" />Stock In</TabsTrigger>
          <TabsTrigger value="stockout"><ArrowUpFromLine className="h-4 w-4 mr-1" />Stock Out</TabsTrigger>
          <TabsTrigger value="movements"><History className="h-4 w-4 mr-1" />Movements</TabsTrigger>
        </TabsList>

        <TabsContent value="inventory"><InventoryReport /></TabsContent>
        <TabsContent value="lowstock"><LowStockReport /></TabsContent>
        <TabsContent value="stockin"><StockInReport /></TabsContent>
        <TabsContent value="stockout"><StockOutReport /></TabsContent>
        <TabsContent value="movements"><MovementsReport /></TabsContent>
      </Tabs>
    </div>
  );
};

const InventoryReport: React.FC = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      setLoading(true);
      const res = await apiFetch<{ items: any[]; total: number }>(`/products.php${qs({ page: 1, per_page: 1000, status: 'all' })}`);
      if (res.ok) {
        const items = res.items || [];
        setData(items.map((p: any) => ({
          SKU: p.sku,
          Name: p.name,
          Category: p.categories?.name || '',
          Unit: p.unit,
          Cost: p.cost_price,
          Price: p.selling_price,
          Stock: p.stock,
          Reorder: p.reorder_level,
          Status: p.status,
          'Inventory Value': (Number(p.cost_price) * Number(p.stock)).toFixed(2),
        })));
      } else {
        setData([]);
      }
      setLoading(false);
    })();
  }, []);

  return (
    <ReportTable title="Inventory List" data={data} loading={loading} filename="inventory_report" />
  );
};

const LowStockReport: React.FC = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      setLoading(true);
      const res = await apiFetch<{ items: any[]; total: number }>(`/products.php${qs({ page: 1, per_page: 1000, status: 'active' })}`);
      if (res.ok) {
        const products = res.items || [];
        const low = products.filter((p: any) => Number(p.stock) <= Number(p.reorder_level));
        setData(low.map((p: any) => ({
          SKU: p.sku,
          Name: p.name,
          Category: p.categories?.name || '',
          Stock: p.stock,
          'Reorder Level': p.reorder_level,
          Deficit: Number(p.reorder_level) - Number(p.stock),
        })));
      } else {
        setData([]);
      }
      setLoading(false);
    })();
  }, []);

  return <ReportTable title="Low Stock Report" data={data} loading={loading} filename="low_stock_report" />;
};

const StockInReport: React.FC = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const fetchData = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: any[]; total: number }>(
      `/stock_in.php${qs({ page: 1, per_page: 2000, date_from: dateFrom, date_to: dateTo })}`
    );
    const items = res.ok ? (res.items || []) : [];
    setData(items.map((r: any) => ({
      Product: r.products?.name || '',
      SKU: r.products?.sku || '',
      Qty: r.qty,
      'Cost/Unit': r.cost_per_unit,
      Total: (Number(r.qty) * Number(r.cost_per_unit)).toFixed(2),
      Supplier: r.suppliers?.name || '',
      Remarks: r.remarks || '',
      Date: new Date(r.created_at).toLocaleDateString(),
    })));
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, [dateFrom, dateTo]);

  return (
    <div>
      <div className="flex gap-3 mb-4">
        <div className="flex items-center gap-2"><Label className="text-sm">From</Label><Input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="w-[160px]" /></div>
        <div className="flex items-center gap-2"><Label className="text-sm">To</Label><Input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="w-[160px]" /></div>
      </div>
      <ReportTable title="Stock In Report" data={data} loading={loading} filename="stock_in_report" />
    </div>
  );
};

const StockOutReport: React.FC = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const fetchData = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: any[]; total: number }>(
      `/stock_out.php${qs({ page: 1, per_page: 2000, date_from: dateFrom, date_to: dateTo })}`
    );
    const items = res.ok ? (res.items || []) : [];
    setData(items.map((r: any) => ({
      Product: r.products?.name || '',
      SKU: r.products?.sku || '',
      Qty: r.qty,
      Customer: r.customer || '',
      Remarks: r.remarks || '',
      Date: new Date(r.created_at).toLocaleDateString(),
    })));
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, [dateFrom, dateTo]);

  return (
    <div>
      <div className="flex gap-3 mb-4">
        <div className="flex items-center gap-2"><Label className="text-sm">From</Label><Input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="w-[160px]" /></div>
        <div className="flex items-center gap-2"><Label className="text-sm">To</Label><Input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="w-[160px]" /></div>
      </div>
      <ReportTable title="Stock Out Report" data={data} loading={loading} filename="stock_out_report" />
    </div>
  );
};

const MovementsReport: React.FC = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const fetchData = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: any[]; total: number }>(
      `/movements.php${qs({ page: 1, per_page: 5000, date_from: dateFrom, date_to: dateTo })}`
    );
    const items = res.ok ? (res.items || []) : [];
    setData(items.map((m: any) => ({
      Type: m.movement_type,
      Product: m.products?.name || '',
      SKU: m.products?.sku || '',
      Qty: m.qty,
      'Prev Stock': m.prev_stock,
      'New Stock': m.new_stock,
      Remarks: m.remarks || '',
      Date: new Date(m.created_at).toLocaleString(),
    })));
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, [dateFrom, dateTo]);

  return (
    <div>
      <div className="flex gap-3 mb-4">
        <div className="flex items-center gap-2"><Label className="text-sm">From</Label><Input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="w-[160px]" /></div>
        <div className="flex items-center gap-2"><Label className="text-sm">To</Label><Input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="w-[160px]" /></div>
      </div>
      <ReportTable title="Movements Report" data={data} loading={loading} filename="movements_report" />
    </div>
  );
};

const ReportTable: React.FC<{ title: string; data: any[]; loading: boolean; filename: string }> = ({ title, data, loading, filename }) => {
  if (loading) return <div className="flex items-center justify-center h-32"><div className="animate-spin h-6 w-6 border-4 border-primary border-t-transparent rounded-full" /></div>;
  const headers = data.length > 0 ? Object.keys(data[0]) : [];

  return (
    <div className="table-container">
      <div className="flex items-center justify-between p-4 border-b">
        <div>
          <h2 className="text-lg font-semibold">{title}</h2>
          <p className="text-sm text-muted-foreground">{data.length} records</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => exportToCsv(data, filename)} disabled={data.length === 0}>
          <Download className="h-4 w-4 mr-2" />Export CSV
        </Button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50">
              {headers.map(h => <th key={h} className="px-4 py-3 text-left font-medium text-muted-foreground whitespace-nowrap">{h}</th>)}
            </tr>
          </thead>
          <tbody>
            {data.length === 0 ? (
              <tr><td colSpan={headers.length || 1} className="px-4 py-8 text-center text-muted-foreground">No data</td></tr>
            ) : data.map((row, i) => (
              <tr key={i} className="border-b last:border-0 hover:bg-muted/30">
                {headers.map(h => <td key={h} className="px-4 py-3 whitespace-nowrap">{row[h]}</td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default ReportsPage;
