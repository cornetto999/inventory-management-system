import React, { useEffect, useState } from 'react';
import { apiFetch, qs } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { ArrowDownToLine, ChevronLeft, ChevronRight } from 'lucide-react';

interface Product { id: string; name: string; sku: string; stock: number; }
interface Supplier { id: string; name: string; }
interface StockInRecord { id: string; qty: number; cost_per_unit: number; remarks: string | null; created_at: string; products: { name: string; sku: string } | null; suppliers: { name: string } | null; }

const ITEMS_PER_PAGE = 10;

const StockInPage: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [records, setRecords] = useState<StockInRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [form, setForm] = useState({ product_id: '', qty: '', cost_per_unit: '0', supplier_id: '', remarks: '' });
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchLookups = async () => {
    const res = await apiFetch<{ productsActive: Product[]; suppliers: Supplier[] }>('/lookups.php');
    if (res.ok) {
      setProducts(res.productsActive || []);
      setSuppliers(res.suppliers || []);
    } else {
      setProducts([]);
      setSuppliers([]);
    }
  };

  const fetchRecords = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: StockInRecord[]; total: number }>(
      `/stock_in.php${qs({ page: page + 1, per_page: ITEMS_PER_PAGE })}`
    );
    if (res.ok) {
      setRecords(res.items || []);
      setTotal(res.total || 0);
    } else {
      setRecords([]);
      setTotal(0);
    }
    setLoading(false);
  };

  useEffect(() => { fetchLookups(); }, []);
  useEffect(() => { fetchRecords(); }, [page]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    if (!form.product_id || !form.qty) { setFormError('Product and quantity are required.'); return; }
    const qty = parseInt(form.qty);
    if (isNaN(qty) || qty <= 0) { setFormError('Quantity must be a positive integer.'); return; }
    const costPerUnit = parseFloat(form.cost_per_unit) || 0;
    if (costPerUnit < 0) { setFormError('Cost must be >= 0.'); return; }

    setSubmitting(true);
    const res = await apiFetch<{ id: string }>('/stock_in.php', {
      method: 'POST',
      body: JSON.stringify({
        product_id: form.product_id,
        qty,
        cost_per_unit: costPerUnit,
        supplier_id: form.supplier_id || null,
        remarks: form.remarks.trim() || null,
      }),
    });

    if (!res.ok) {
      setFormError(res.error || 'Failed to record stock in');
      setSubmitting(false);
      return;
    }

    toast.success(`Stock in: +${qty} units`);
    setForm({ product_id: '', qty: '', cost_per_unit: '0', supplier_id: '', remarks: '' });
    fetchRecords();
    fetchLookups();
    setSubmitting(false);
  };

  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);

  return (
    <div>
      <div className="page-header">
        <h1 className="page-title">Stock In</h1>
      </div>

      {/* Form */}
      <div className="bg-card border rounded-lg p-5 mb-6">
        <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
          <ArrowDownToLine className="h-5 w-5 text-success" />
          Record Stock In
        </h2>
        {formError && <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive mb-4">{formError}</div>}
        <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <Label>Product *</Label>
            <Select value={form.product_id} onValueChange={(v) => setForm({ ...form, product_id: v })}>
              <SelectTrigger className="mt-1"><SelectValue placeholder="Select product" /></SelectTrigger>
              <SelectContent>{products.map(p => <SelectItem key={p.id} value={p.id}>{p.name} ({p.sku})</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div>
            <Label>Quantity *</Label>
            <Input type="number" min="1" value={form.qty} onChange={(e) => setForm({ ...form, qty: e.target.value })} className="mt-1" placeholder="Enter quantity" />
          </div>
          <div>
            <Label>Cost per Unit</Label>
            <Input type="number" min="0" step="0.01" value={form.cost_per_unit} onChange={(e) => setForm({ ...form, cost_per_unit: e.target.value })} className="mt-1" />
          </div>
          <div>
            <Label>Supplier</Label>
            <Select value={form.supplier_id || 'none'} onValueChange={(v) => setForm({ ...form, supplier_id: v === 'none' ? '' : v })}>
              <SelectTrigger className="mt-1"><SelectValue placeholder="Optional" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">None</SelectItem>
                {suppliers.map(s => <SelectItem key={s.id} value={s.id}>{s.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="md:col-span-2">
            <Label>Remarks</Label>
            <Textarea value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} maxLength={500} className="mt-1" rows={1} placeholder="Optional notes" />
          </div>
          <div className="flex items-end">
            <Button type="submit" disabled={submitting} className="w-full">{submitting ? 'Processing...' : 'Submit Stock In'}</Button>
          </div>
        </form>
      </div>

      {/* History */}
      <div className="table-container">
        <div className="p-4 border-b"><h2 className="text-lg font-semibold">Stock In History</h2></div>
        <table className="w-full text-sm">
          <thead><tr className="border-b bg-muted/50">
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Product</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Qty</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Cost/Unit</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Supplier</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Remarks</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
          </tr></thead>
          <tbody>
            {loading ? <tr><td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">Loading...</td></tr>
              : records.length === 0 ? <tr><td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">No records</td></tr>
              : records.map(r => (
                <tr key={r.id} className="border-b last:border-0 hover:bg-muted/30">
                  <td className="px-4 py-3 font-medium">{r.products?.name || '-'}</td>
                  <td className="px-4 py-3 text-success font-semibold">+{r.qty}</td>
                  <td className="px-4 py-3">₱{Number(r.cost_per_unit).toFixed(2)}</td>
                  <td className="px-4 py-3 text-muted-foreground">{r.suppliers?.name || '-'}</td>
                  <td className="px-4 py-3 text-muted-foreground truncate max-w-[150px]">{r.remarks || '-'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(r.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
          </tbody>
        </table>
        {totalPages > 1 && (
          <div className="flex items-center justify-between p-4 border-t">
            <p className="text-sm text-muted-foreground">Page {page + 1} of {totalPages}</p>
            <div className="flex gap-1">
              <Button size="sm" variant="outline" disabled={page === 0} onClick={() => setPage(p => p - 1)}><ChevronLeft className="h-4 w-4" /></Button>
              <Button size="sm" variant="outline" disabled={page >= totalPages - 1} onClick={() => setPage(p => p + 1)}><ChevronRight className="h-4 w-4" /></Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default StockInPage;
